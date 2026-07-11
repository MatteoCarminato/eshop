<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappBroadcastJob;
use App\Models\WhatsappGroup;
use App\Models\WhatsappPixExtraction;
use App\Models\WhatsappScheduledMessage;
use App\Services\WhatsappNodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WhatsappController extends Controller
{
    public function __construct(protected WhatsappNodeService $whatsappNodeService) {}

    private function gruposService(): WhatsappNodeService
    {
        return new WhatsappNodeService('whatsapp_node_grupos');
    }

    public function index()
    {
        return view('admin.whatsapp.index', [
            'nodeHealth'         => $this->whatsappNodeService->health(),
            'nodeHealthGrupos'   => $this->gruposService()->health(),
            'initialStatus'      => $this->whatsappNodeService->status(),
            'initialStatusGrupos'=> $this->gruposService()->status(),
        ]);
    }

    public function wppGroups()
    {
        $result = $this->gruposService()->groups();

        $error = null;
        if (!($result['success'] ?? false)) {
            $raw = $result['error'] ?? '';
            $error = str_contains($raw, 'cURL') || str_contains($raw, 'timed out') || str_contains($raw, 'Connection refused')
                ? 'Não foi possível conectar ao serviço WhatsApp. Verifique se ele está rodando na porta 3000.'
                : ($raw ?: 'Erro ao carregar grupos.');
        }

        $saved = WhatsappGroup::all()->keyBy('chat_id');

        return view('admin.whatsapp.grupos', [
            'groups' => $result['groups'] ?? [],
            'saved' => $saved,
            'error' => $error,
        ]);
    }

    public function saveWppGroups(Request $request): RedirectResponse
    {
        $selected = collect($request->input('groups', []))->filter()->values();

        // Desativa todos, depois ativa os selecionados
        WhatsappGroup::query()->update(['ai_active' => false]);

        foreach ($selected as $item) {
            WhatsappGroup::updateOrCreate(
                ['chat_id' => $item['chat_id']],
                [
                    'name' => $item['name'] ?? $item['chat_id'],
                    'participants_count' => $item['participants_count'] ? (int) $item['participants_count'] : null,
                    'ai_active' => true,
                ]
            );
        }

        return back()->with('success', count($selected) . ' grupo(s) salvos para resposta com IA.');
    }

    public function envio()
    {
        $data = $this->loadTargetData();

        return view('admin.whatsapp.envio', [
            'clients' => $data['clients'],
            'groups' => $data['groups'],
            'groupClients' => $data['groupClients'],
        ]);
    }

    public function schedules()
    {
        $data = $this->loadTargetData();

        $schedules = WhatsappScheduledMessage::query()
            ->orderByDesc('id')
            ->get();

        return view('admin.whatsapp.schedules', [
            'clients' => $data['clients'],
            'groups' => $data['groups'],
            'groupClients' => $data['groupClients'],
            'schedules' => $schedules,
        ]);
    }

    public function status(): JsonResponse
    {
        $status = $this->whatsappNodeService->status();

        if (!($status['success'] ?? false)) {
            return response()->json($status, 502);
        }

        return response()->json($status);
    }

    public function qr(): JsonResponse
    {
        $qr = $this->whatsappNodeService->qr();

        if (!($qr['success'] ?? false)) {
            return response()->json($qr, 502);
        }

        return response()->json($qr);
    }

    public function qrImage(): Response|JsonResponse
    {
        $qrImage = $this->whatsappNodeService->qrImage();

        if (!($qrImage['success'] ?? false)) {
            return response()->json($qrImage, 502);
        }

        return response($qrImage['png'], 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    public function pairingCode(Request $request): JsonResponse
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', ''));

        if ($phone === '') {
            return response()->json(['success' => false, 'error' => 'Número de telefone obrigatório.'], 422);
        }

        $this->whatsappNodeService->start();

        $result = $this->whatsappNodeService->pairingCode($phone);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Erro ao gerar código.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'pairing_code' => $result['data']['pairing_code'] ?? null,
            'expires_in_ms' => $result['data']['expires_in_ms'] ?? null,
        ]);
    }

    public function disconnect(): JsonResponse
    {
        $result = $this->whatsappNodeService->disconnect();

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['success']
                ? 'WhatsApp desconectado com sucesso.'
                : ($result['error'] ?? 'Erro ao desconectar.'),
        ], $result['success'] ? 200 : 502);
    }

    // ── Instância Grupos ────────────────────────────────────────────────────

    public function statusGrupos(): JsonResponse
    {
        $status = $this->gruposService()->status();
        return ($status['success'] ?? false)
            ? response()->json($status)
            : response()->json($status, 502);
    }

    public function qrGrupos(): JsonResponse
    {
        $qr = $this->gruposService()->qr();
        return ($qr['success'] ?? false)
            ? response()->json($qr)
            : response()->json($qr, 502);
    }

    public function connectGrupos(): JsonResponse
    {
        $svc = $this->gruposService();
        $svc->start();
        $status = $svc->status();
        $qr     = $svc->qr();

        return response()->json([
            'success' => ($status['success'] ?? false),
            'status'  => $status,
            'qr'      => $qr,
            'message' => 'Sessão grupos iniciada.',
        ]);
    }

    public function disconnectGrupos(): JsonResponse
    {
        $result = $this->gruposService()->disconnect();

        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => $result['success']
                ? 'Instância grupos desconectada.'
                : ($result['error'] ?? 'Erro ao desconectar grupos.'),
        ], $result['success'] ? 200 : 502);
    }

    public function pairingCodeGrupos(Request $request): JsonResponse
    {
        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', ''));

        if ($phone === '') {
            return response()->json(['success' => false, 'error' => 'Número de telefone obrigatório.'], 422);
        }

        $svc = $this->gruposService();
        $svc->start();
        $result = $svc->pairingCode($phone);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'] ?? 'Erro ao gerar código.',
            ], 502);
        }

        return response()->json([
            'success'        => true,
            'pairing_code'   => $result['data']['pairing_code'] ?? null,
            'expires_in_ms'  => $result['data']['expires_in_ms'] ?? null,
        ]);
    }

    public function connect(): JsonResponse
    {
        $this->whatsappNodeService->start();
        $status = $this->whatsappNodeService->status();
        $qr = $this->whatsappNodeService->qr();

        return response()->json([
            'success' => ($status['success'] ?? false),
            'status' => $status,
            'qr' => $qr,
            'message' => 'Sessão iniciada. Escaneie o QR abaixo se disponível.',
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'message' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'max:15360'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $clientIds = (array) $request->input('client_ids', []);
            $message = trim((string) $request->input('message', ''));
            $hasAttachment = $request->hasFile('attachment');

            if (empty($clientIds)) {
                $validator->errors()->add('targets', 'Selecione ao menos um cliente.');
            }

            if ($message === '' && !$hasAttachment) {
                $validator->errors()->add('message', 'Informe uma mensagem ou anexe um arquivo/imagem.');
            }
        });

        $validated = $validator->validate();

        $clientIds = collect($validated['client_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $message = trim((string) ($validated['message'] ?? ''));

        // group_ids are expanded into client_ids by the frontend — re-expanding server-side
        // would include clients the user explicitly unchecked.
        $recipients = $this->resolveRecipients($clientIds, []);

        if ($recipients->isEmpty()) {
            return back()->withInput()->with('error', 'Nenhum destinatário válido com telefone foi encontrado.');
        }

        $attachment = $request->file('attachment');
        $attachmentMime = null;
        $attachmentPath = null;

        if ($attachment) {
            $attachmentMime = (string) ($attachment->getMimeType() ?: 'application/octet-stream');
            $attachmentPath = $attachment->storeAs(
                'whatsapp-attachments',
                Str::uuid()->toString() . '.' . $attachment->getClientOriginalExtension(),
                'local'
            );
        }

        SendWhatsappBroadcastJob::dispatch(
            $recipients->all(),
            $message,
            $attachmentPath,
            $attachmentMime
        );

        return back()->with('success', 'Disparo enfileirado com sucesso. O envio será processado em segundo plano.');
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'schedule_name' => ['nullable', 'string', 'max:120'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $clientIds = (array) $request->input('client_ids', []);
            $weekdays = (array) $request->input('weekdays', []);

            if (empty($clientIds)) {
                $validator->errors()->add('targets', 'Selecione ao menos um cliente.');
            }

            if (empty($weekdays)) {
                $validator->errors()->add('weekdays', 'Selecione ao menos um dia da semana para o envio.');
            }
        });

        $validated = $validator->validate();

        // group_ids are expanded into client_ids by the frontend — saving group_ids and
        // re-expanding them at job runtime would re-include clients the user unchecked.
        $clientIds = collect($validated['client_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $weekdays = collect($validated['weekdays'] ?? [])->map(fn ($day) => (int) $day)->unique()->values()->all();

        WhatsappScheduledMessage::query()->create([
            'name' => trim((string) ($validated['schedule_name'] ?? '')) ?: null,
            'message' => trim((string) $validated['message']),
            'client_ids' => $clientIds,
            'group_ids' => [],
            'weekdays' => $weekdays,
            'is_active' => true,
            'created_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Configuração de disparo agendado salva com sucesso. O sistema processa diariamente às 07:30 (Brasil).');
    }

    public function toggleSchedule(WhatsappScheduledMessage $scheduledMessage): RedirectResponse
    {
        $newState = !$scheduledMessage->is_active;

        DB::table('whatsapp_scheduled_messages')
            ->where('id', $scheduledMessage->id)
            ->update(['is_active' => $newState]);

        return back()->with('success', $newState
            ? 'Disparo agendado ativado.'
            : 'Disparo agendado pausado.');
    }

    public function destroySchedule(WhatsappScheduledMessage $scheduledMessage): RedirectResponse
    {
        DB::table('whatsapp_scheduled_messages')
            ->where('id', $scheduledMessage->id)
            ->delete();

        return back()->with('success', 'Disparo agendado removido com sucesso.');
    }

    public function updateSchedule(Request $request, WhatsappScheduledMessage $scheduledMessage): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'schedule_name' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:4000'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'between:0,6'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $clientIds = (array) $request->input('client_ids', []);
            $groupIds = (array) $request->input('group_ids', []);
            $weekdays = (array) $request->input('weekdays', []);

            if (empty($clientIds) && empty($groupIds)) {
                $validator->errors()->add('targets', 'Selecione ao menos um cliente ou grupo.');
            }

            if (empty($weekdays)) {
                $validator->errors()->add('weekdays', 'Selecione ao menos um dia da semana para o envio.');
            }
        });

        $validated = $validator->validate();

        $clientIds = collect($validated['client_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $groupIds = collect($validated['group_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $weekdays = collect($validated['weekdays'] ?? [])->map(fn ($day) => (int) $day)->unique()->values()->all();

        DB::table('whatsapp_scheduled_messages')
            ->where('id', $scheduledMessage->id)
            ->update([
                'name' => trim((string) ($validated['schedule_name'] ?? '')) ?: null,
                'message' => trim((string) $validated['message']),
                'client_ids' => json_encode($clientIds),
                'group_ids' => json_encode($groupIds),
                'weekdays' => json_encode($weekdays),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Disparo agendado atualizado com sucesso.');
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
                    ->select(['id', 'name', 'phone'])
                    ->get()
            );
        }

        if (!empty($groupIds)) {
            $groupClients = DB::table('clients')
                ->join('group_client', 'clients.id', '=', 'group_client.client_id')
                ->whereIn('group_client.group_id', $groupIds)
                ->select(['clients.id', 'clients.name', 'clients.phone'])
                ->get()
                ->values();

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

        return $digits;
    }

    /**
     * @return array{clients:\Illuminate\Support\Collection,groups:\Illuminate\Support\Collection,groupClients:\Illuminate\Support\Collection}
     */
    private function loadTargetData(): array
    {
        $clients = DB::table('clients')
            ->select(['id', 'name', 'phone'])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $groups = DB::table('groups')
            ->leftJoin('group_client', 'groups.id', '=', 'group_client.group_id')
            ->select([
                'groups.id',
                'groups.name',
                'groups.description',
                DB::raw('COUNT(group_client.client_id) as clients_count'),
            ])
            ->groupBy('groups.id', 'groups.name', 'groups.description')
            ->orderBy('groups.name')
            ->get();

        $groupClients = DB::table('group_client')
            ->select(['group_id', 'client_id'])
            ->get()
            ->groupBy('group_id')
            ->map(fn ($items) => $items->pluck('client_id')->values()->all());

        return [
            'clients' => $clients,
            'groups' => $groups,
            'groupClients' => $groupClients,
        ];
    }

    public function extracoes(Request $request)
    {
        $query = WhatsappPixExtraction::with('group')->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($groupId = $request->input('group_id')) {
            $query->where('whatsapp_group_id', $groupId);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pix_nome', 'like', "%{$search}%")
                  ->orWhere('pix_valor', 'like', "%{$search}%")
                  ->orWhere('numero_transacao', 'like', "%{$search}%")
                  ->orWhere('from', 'like', "%{$search}%");
            });
        }

        if ($from = $request->input('data_inicio')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('data_fim')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $extractions = $query->paginate(20)->withQueryString();
        $groups      = \App\Models\WhatsappGroup::orderBy('name')->get(['id', 'name']);

        return view('admin.whatsapp.extracoes', compact('extractions', 'groups'));
    }

    public function extracoesImagem(WhatsappPixExtraction $extraction)
    {
        if (!Storage::disk('do_spaces')->exists($extraction->image_path)) {
            abort(404);
        }

        $content  = Storage::disk('do_spaces')->get($extraction->image_path);
        $mimetype = $extraction->mimetype ?? 'application/octet-stream';

        return response($content, 200)
            ->header('Content-Type', $mimetype)
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
