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

    public int $timeout = 3600;

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

        try {
            foreach ($this->recipients as $recipient) {
                $name = (string) ($recipient['name'] ?? 'Contato');
                $phone = (string) ($recipient['phone'] ?? '');

                if ($phone === '') {
                    $failed++;
                    $errors[] = $name . ': telefone vazio';
                    continue;
                }

                $result = $this->attachmentPath
                    ? $this->sendMedia($whatsappNodeService, $phone)
                    : $whatsappNodeService->sendText($phone, $this->message);

                if ($result['success'] ?? false) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = $name . ': ' . ($result['error'] ?? 'falha no envio');
                }

                Log::info('📨 Broadcast WhatsApp processado', [
                    'name' => $name,
                    'phone' => $phone,
                    'success' => (bool) ($result['success'] ?? false),
                ]);
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

    private function sendMedia(WhatsappNodeService $whatsappNodeService, string $phone): array
    {
        $content = Storage::disk('local')->get($this->attachmentPath);
        $attachmentBase64 = base64_encode($content);
        $attachmentDataUri = 'data:' . ($this->attachmentMime ?: 'application/octet-stream') . ';base64,' . $attachmentBase64;

        return $whatsappNodeService->sendMedia($phone, $attachmentDataUri, (string) ($this->attachmentMime ?: 'application/octet-stream'), $this->message !== '' ? $this->message : null);
    }
}