<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappNodeService
{
    public function __construct(private string $configKey = 'whatsapp_node') {}

    protected function client()
    {
        return Http::baseUrl(rtrim((string) config("services.{$this->configKey}.url"), '/'))
            ->withHeaders([
                'x-api-key' => (string) config("services.{$this->configKey}.api_key"),
                'Accept' => 'application/json',
            ])
            ->timeout((int) config("services.{$this->configKey}.timeout", 60));
    }

    public function health(): array
    {
        try {
            $res = $this->client()->get('/api/health');

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Falha no health check do whatsapp-node.',
                ];
            }

            return [
                'success' => true,
                'data' => $res->json(),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.health error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function status(): array
    {
        try {
            $res = $this->client()->get('/api/session/status');

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível consultar status do WhatsApp.',
                    'body' => $res->json(),
                ];
            }

            $json = $res->json();
            $rawStatus = $json['data']['status'] ?? 'unknown';

            $stateMap = [
                'connected' => 'connected',
                'disconnected' => 'disconnected',
                'idle' => 'disconnected',
                'auth_failure' => 'disconnected',
                'error' => 'disconnected',
            ];
            $state = $stateMap[$rawStatus] ?? 'connecting';

            return [
                'success' => true,
                'data' => [
                    'state' => $state,
                    'raw_status' => $rawStatus,
                    'sending' => false,
                    'messagesSent' => 0,
                    'lastReport' => null,
                    'has_qr' => $json['data']['has_qr'] ?? false,
                    'me' => $json['data']['me'] ?? null,
                    'last_error' => $json['data']['last_error'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.status error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function start(): array
    {
        try {
            $res = $this->client()->post('/api/session/start');

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível iniciar a sessão.',
                    'body' => $res->json(),
                ];
            }

            return [
                'success' => true,
                'data' => $res->json()['data'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.start error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function disconnect(): array
    {
        try {
            $res = $this->client()->post('/api/session/logout');

            return [
                'success' => $res->successful(),
                'data' => $res->json(),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.disconnect error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function qr(): array
    {
        try {
            $res = $this->client()->get('/api/session/qr');

            if ($res->status() === 404) {
                return [
                    'success' => true,
                    'data' => [
                        'available' => false,
                        'qr_data_url' => null,
                        'timestamp' => null,
                        'message' => 'QR não disponível. Já autenticado ou aguardando.',
                    ],
                ];
            }

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível consultar QR do WhatsApp.',
                    'body' => $res->json(),
                ];
            }

            $json = $res->json();
            $qrDataUrl = $json['data']['qr_data_url'] ?? null;

            return [
                'success' => true,
                'data' => [
                    'available' => !empty($qrDataUrl),
                    'qr_data_url' => $qrDataUrl,
                    'timestamp' => now()->toIso8601String(),
                    'message' => null,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.qr error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function qrImage(): array
    {
        try {
            $res = $this->client()->get('/api/session/qr.png');

            if ($res->status() === 404) {
                return [
                    'success' => false,
                    'available' => false,
                    'message' => 'Nenhum QR code disponível. Já autenticado ou aguardando.',
                ];
            }

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível obter a imagem do QR.',
                    'body' => $res->body(),
                ];
            }

            return [
                'success' => true,
                'png' => $res->body(),
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.qrImage error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function pairingCode(string $phone): array
    {
        try {
            $res = $this->client()->post('/api/session/pairing-code', [
                'phone' => $phone,
            ]);

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => $res->json()['message'] ?? 'Não foi possível gerar o código de pareamento.',
                    'body' => $res->json(),
                ];
            }

            $json = $res->json();

            return [
                'success' => true,
                'data' => $json['data'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.pairingCode error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function groups(): array
    {
        try {
            $res = $this->client()->timeout(60)->get('/api/chats/groups');

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível listar os grupos do WhatsApp.',
                    'body' => $res->json(),
                ];
            }

            $items = $res->json()['data'] ?? [];

            $groups = array_map(fn ($g) => [
                'chatId' => $g['id'] ?? '',
                'name' => $g['name'] ?? '',
                'participantsCount' => $g['participants_count'] ?? null,
                'timestamp' => $g['timestamp'] ?? null,
            ], $items);

            return [
                'success' => true,
                'total' => count($groups),
                'groups' => $groups,
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.groups error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendText(string $phone, string $message): array
    {
        try {
            $res = $this->client()->post('/api/messages/send-text', [
                'phone' => $phone,
                'message' => $message,
            ]);

            if (!$res->successful()) {
                Log::warning('WhatsappNodeService.sendText failed response', [
                    'phone' => $phone,
                    'status' => $res->status(),
                    'body' => $res->json(),
                ]);

                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Falha ao enviar mensagem de texto.',
                    'body' => $res->json(),
                ];
            }

            return [
                'success' => true,
                'data' => $res->json()['data'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.sendText error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendMedia(string $phone, string $mediaDataUri, string $mediaType, ?string $message = null): array
    {
        try {
            $body = ['phone' => $phone];

            if (str_starts_with($mediaDataUri, 'data:') && preg_match('/^data:([^;]+);base64,(.+)$/s', $mediaDataUri, $matches)) {
                $body['mimetype'] = $matches[1];
                $body['base64'] = $matches[2];
            } elseif (str_starts_with($mediaDataUri, 'http://') || str_starts_with($mediaDataUri, 'https://')) {
                $body['imageUrl'] = $mediaDataUri;
                $body['mimetype'] = $mediaType;
            } else {
                $body['base64'] = $mediaDataUri;
                $body['mimetype'] = $mediaType;
            }

            if ($message !== null && $message !== '') {
                $body['caption'] = $message;
            }

            $res = $this->client()->post('/api/messages/send-image', $body);

            if (!$res->successful()) {
                Log::warning('WhatsappNodeService.sendMedia failed response', [
                    'phone' => $phone,
                    'status' => $res->status(),
                    'body' => $res->json(),
                ]);

                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Falha ao enviar mídia.',
                    'body' => $res->json(),
                ];
            }

            return [
                'success' => true,
                'data' => $res->json()['data'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsappNodeService.sendMedia error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
