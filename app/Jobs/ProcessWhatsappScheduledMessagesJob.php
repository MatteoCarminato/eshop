<?php

namespace App\Jobs;

use App\Services\WhatsappNodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWhatsappScheduledMessagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public function __construct()
    {
        $this->onQueue('whatsapp');
    }

    public function handle(WhatsappNodeService $whatsappNodeService): void
    {
        $status = $whatsappNodeService->status();
        if (!($status['success'] ?? false)) {
            Log::warning('Nao foi possivel validar status do WhatsApp para disparos agendados.', [
                'status' => $status,
            ]);
            return;
        }

        $nowSp = Carbon::now('America/Sao_Paulo');
        $weekday = $nowSp->dayOfWeek;

        $schedules = DB::table('whatsapp_scheduled_messages')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($schedules as $schedule) {
            $weekdays = collect(json_decode((string) ($schedule->weekdays ?? '[]'), true) ?: [])
                ->map(fn ($v) => (int) $v)
                ->all();
            if (!in_array($weekday, $weekdays, true)) {
                continue;
            }

            $lastRunAt = !empty($schedule->last_run_at) ? Carbon::parse((string) $schedule->last_run_at) : null;
            if ($lastRunAt && $lastRunAt->copy()->timezone('America/Sao_Paulo')->isSameDay($nowSp)) {
                continue;
            }

            $clientIds = collect(json_decode((string) ($schedule->client_ids ?? '[]'), true) ?: [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();
            $groupIds = collect(json_decode((string) ($schedule->group_ids ?? '[]'), true) ?: [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            $recipients = $this->resolveRecipients($clientIds, $groupIds);

            if ($recipients->isEmpty()) {
                Log::info('Disparo agendado sem destinatarios validos.', [
                    'schedule_id' => $schedule->id,
                ]);
                DB::table('whatsapp_scheduled_messages')
                    ->where('id', $schedule->id)
                    ->update(['last_run_at' => now()]);
                continue;
            }

            SendWhatsappBroadcastJob::dispatch(
                $recipients->all(),
                (string) $schedule->message,
                null,
                null
            );

            DB::table('whatsapp_scheduled_messages')
                ->where('id', $schedule->id)
                ->update(['last_run_at' => now()]);

            Log::info('Disparo agendado enfileirado.', [
                'schedule_id' => $schedule->id,
                'recipients' => $recipients->count(),
            ]);
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{name:string, phone:string}>
     */
    private function resolveRecipients(array $clientIds, array $groupIds)
    {
        $selectedClients = collect();

        if (!empty($clientIds)) {
            $selectedClients = $selectedClients->merge(
                DB::table('clients')
                    ->whereIn('id', $clientIds)
                    ->whereNull('deleted_at')
                    ->select(['id', 'name', 'phone'])
                    ->get()
            );
        }

        if (!empty($groupIds)) {
            $groupClients = DB::table('clients')
                ->join('group_client', 'clients.id', '=', 'group_client.client_id')
                ->whereIn('group_client.group_id', $groupIds)
                ->whereNull('clients.deleted_at')
                ->select(['clients.id', 'clients.name', 'clients.phone'])
                ->get();

            $selectedClients = $selectedClients->merge($groupClients);
        }

        return $selectedClients
            ->unique('id')
            ->map(function ($client) {
                $phone = $this->normalizePhone((string) ($client->phone ?? ''));

                return [
                    'name' => (string) ($client->name ?? 'Contato'),
                    'phone' => $phone,
                ];
            })
            ->filter(fn ($client) => !empty($client['phone']))
            ->values();
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (!str_starts_with($digits, '55') && in_array(strlen($digits), [10, 11], true)) {
            $digits = '55' . $digits;
        }

        if (strlen($digits) < 12 || strlen($digits) > 13) {
            return null;
        }

        return $digits;
    }
}
