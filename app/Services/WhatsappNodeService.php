<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappNodeService
{
    protected function client()
    {
        return Http::baseUrl(rtrim((string) config('services.whatsapp_node.url'), '/'))
            ->withHeaders([
                'x-api-key' => (string) config('services.whatsapp_node.api_key'),
                'Accept' => 'application/json',
            ])
            ->timeout((int) config('services.whatsapp_node.timeout', 60));
    }

    public function health(): array
    {
        try {
            $res = $this->client()->get('/health');

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
            $res = $this->client()->get('/api/status');

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível consultar status do WhatsApp.',
                    'body' => $res->json(),
                ];
            }

            $data = $res->json();

            return [
                'success' => true,
                'data' => [
                    'state' => $data['state'] ?? 'unknown',
                    'sending' => (bool) ($data['sending'] ?? false),
                    'messagesSent' => (int) ($data['messagesSent'] ?? 0),
                    'lastReport' => $data['lastReport'] ?? null,
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

    public function qr(): array
    {
        try {
            $res = $this->client()->get('/api/qr');

            if (!$res->successful()) {
                return [
                    'success' => false,
                    'status' => $res->status(),
                    'error' => 'Não foi possível consultar QR do WhatsApp.',
                    'body' => $res->json(),
                ];
            }

            $data = $res->json();

            return [
                'success' => true,
                'data' => [
                    'available' => (bool) ($data['available'] ?? false),
                    'qr' => $data['qr'] ?? null,
                    'timestamp' => $data['timestamp'] ?? null,
                    'message' => $data['message'] ?? null,
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
            $res = $this->client()->get('/api/qr-image');

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
                    'error' => 'Não foi possível consultar a imagem do QR.',
                    'body' => $res->body(),
                ];
            }

            return [
                'success' => true,
                'svg' => $res->body(),
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

    public function sendText(string $phone, string $message): array
    {
        try {
            $res = $this->client()->post('/api/send-text', [
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
                'data' => $res->json(),
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
            $res = $this->client()->post('/api/send-media', [
                'phone' => $phone,
                'mediaUrl' => $mediaDataUri,
                'mediaType' => $mediaType,
                'message' => $message,
            ]);

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
                'data' => $res->json(),
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
