<?php

namespace App\Http\Controllers;

use App\Services\WhatsappNodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WhatsappController extends Controller
{
    public function __construct(protected WhatsappNodeService $whatsappNodeService) {}

    public function index()
    {
        $health = $this->whatsappNodeService->health();
        $status = $this->whatsappNodeService->status();

        return view('admin.whatsapp.index', [
            'nodeHealth' => $health,
            'initialStatus' => $status,
        ]);
    }

    public function envio()
    {
        $clients = DB::table('clients')
            ->select(['id', 'name', 'phone'])
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

        return view('admin.whatsapp.envio', [
            'clients' => $clients,
            'groups' => $groups,
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

        return response($qrImage['svg'], 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    public function connect(): JsonResponse
    {
        $status = $this->whatsappNodeService->status();
        $qr = $this->whatsappNodeService->qr();

        return response()->json([
            'success' => ($status['success'] ?? false) && ($qr['success'] ?? false),
            'status' => $status,
            'qr' => $qr,
            'message' => 'Conexão iniciada no processo do Node. Escaneie o QR abaixo se disponível.',
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
            $groupIds = (array) $request->input('group_ids', []);
            $message = trim((string) $request->input('message', ''));
            $hasAttachment = $request->hasFile('attachment');

            if (empty($clientIds) && empty($groupIds)) {
                $validator->errors()->add('targets', 'Selecione ao menos um cliente ou grupo.');
            }

            if ($message === '' && !$hasAttachment) {
                $validator->errors()->add('message', 'Informe uma mensagem ou anexe um arquivo/imagem.');
            }
        });

        $validated = $validator->validate();

        $clientIds = collect($validated['client_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $groupIds = collect($validated['group_ids'] ?? [])->map(fn ($id) => (int) $id)->all();

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

        $recipients = $selectedClients
            ->unique('id')
            ->filter(fn ($client) => !empty($client->phone))
            ->values();

        if ($recipients->isEmpty()) {
            return back()->withInput()->with('error', 'Nenhum destinatário válido com telefone foi encontrado.');
        }

        $message = trim((string) ($validated['message'] ?? ''));
        $attachment = $request->file('attachment');
        $attachmentDataUri = null;
        $attachmentMime = null;

        if ($attachment) {
            $attachmentMime = (string) ($attachment->getMimeType() ?: 'application/octet-stream');
            $attachmentBase64 = base64_encode((string) file_get_contents($attachment->getRealPath()));
            $attachmentDataUri = 'data:' . $attachmentMime . ';base64,' . $attachmentBase64;
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $recipient) {
            $phone = $this->normalizePhone((string) $recipient->phone);

            if (!$phone) {
                $failed++;
                $errors[] = $recipient->name . ': telefone inválido';
                continue;
            }

            $result = $attachmentDataUri
                ? $this->whatsappNodeService->sendMedia($phone, $attachmentDataUri, (string) $attachmentMime, $message !== '' ? $message : null)
                : $this->whatsappNodeService->sendText($phone, $message);

            if ($result['success'] ?? false) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $recipient->name . ': ' . ($result['error'] ?? 'falha no envio');
            }
        }

        if ($sent > 0 && $failed === 0) {
            return back()->with('success', "Disparo concluído com sucesso. Enviadas: {$sent}.");
        }

        if ($sent > 0 && $failed > 0) {
            return back()->with('warning', "Disparo parcial. Enviadas: {$sent}. Falhas: {$failed}. Detalhes: " . implode(' | ', array_slice($errors, 0, 5)));
        }

        return back()->withInput()->with('error', 'Não foi possível enviar as mensagens. ' . implode(' | ', array_slice($errors, 0, 5)));
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
