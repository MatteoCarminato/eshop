<?php

namespace App\Jobs;

use App\Services\WhatsappNodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendWhatsappBroadcastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 7200;

    /**
     * Intervalo aleatório (em segundos) entre cada envio do disparo em massa,
     * pra reduzir o risco de o número ser marcado como spam/banido.
     */
    private const MIN_DELAY_SECONDS = 3;
    private const MAX_DELAY_SECONDS = 9;

    /**
     * @param array<int, array{name:string, phone:string}> $recipients
     */
    public function __construct(
        public array $recipients,
        public string $message,
        public ?string $attachmentPath = null,
        public ?string $attachmentMime = null,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(WhatsappNodeService $whatsappNodeService): void
    {
        $sent = 0;
        $failed = 0;
        $errors = [];
        $mediaDataUri = null;

        if ($this->attachmentPath) {
            $mediaDataUri = $this->buildMediaDataUri();
        }

        try {
            $total = count($this->recipients);

            foreach ($this->recipients as $index => $recipient) {
                $name = (string) ($recipient['name'] ?? 'Contato');
                $phone = (string) ($recipient['phone'] ?? '');

                if ($phone === '') {
                    $failed++;
                    $errors[] = $name . ': telefone vazio';
                    continue;
                }

                $result = $mediaDataUri
                    ? $this->sendMedia($whatsappNodeService, $phone, $mediaDataUri)
                    : $whatsappNodeService->sendText($phone, $this->message);

                if ($result['success'] ?? false) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = $name . ': ' . ($result['error'] ?? 'falha no envio');
                    Log::warning('❌ Broadcast WhatsApp falhou', [
                        'name' => $name,
                        'phone' => $phone,
                        'status' => $result['status'] ?? null,
                        'error' => $result['error'] ?? null,
                        'body' => $result['body'] ?? null,
                    ]);
                }

                Log::info('📨 Broadcast WhatsApp processado', [
                    'name' => $name,
                    'phone' => $phone,
                    'success' => (bool) ($result['success'] ?? false),
                ]);

                if ($index < $total - 1) {
                    sleep(random_int(self::MIN_DELAY_SECONDS, self::MAX_DELAY_SECONDS));
                }
            }

            Log::info('🏁 Broadcast WhatsApp concluído', [
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($this->recipients),
                'errors' => array_slice($errors, 0, 10),
            ]);
        } finally {
            if ($this->attachmentPath && Storage::disk('local')->exists($this->attachmentPath)) {
                Storage::disk('local')->delete($this->attachmentPath);
            }
        }
    }

    private function sendMedia(WhatsappNodeService $whatsappNodeService, string $phone, string $mediaDataUri): array
    {
        return $whatsappNodeService->sendMedia(
            $phone,
            $mediaDataUri,
            (string) ($this->attachmentMime ?: 'application/octet-stream'),
            $this->message !== '' ? $this->message : null
        );
    }

    private function buildMediaDataUri(): string
    {
        $content = Storage::disk('local')->get($this->attachmentPath);
        $attachmentBase64 = base64_encode($content);

        return 'data:' . ($this->attachmentMime ?: 'application/octet-stream') . ';base64,' . $attachmentBase64;
    }
}