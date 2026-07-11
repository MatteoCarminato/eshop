<?php

namespace App\Jobs;

use App\Services\WhatsappNodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class FetchWhatsappGroupsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 90;

    public function __construct()
    {
        $this->onQueue('whatsapp');
    }

    public function handle(): void
    {
        try {
            $result = (new WhatsappNodeService('whatsapp_node_grupos'))->groups();

            Cache::put('whatsapp:grupos:result', $result, now()->addHours(6));
        } finally {
            Cache::forget('whatsapp:grupos:loading');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Cache::put('whatsapp:grupos:result', [
            'success' => false,
            'error' => $exception->getMessage(),
        ], now()->addHours(6));

        Cache::forget('whatsapp:grupos:loading');
    }
}
