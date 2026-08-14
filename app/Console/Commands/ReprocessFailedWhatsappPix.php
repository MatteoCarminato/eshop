<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsappWebhookController;
use App\Models\WhatsappPixExtraction;
use Illuminate\Console\Command;

class ReprocessFailedWhatsappPix extends Command
{
    protected $signature = 'whatsapp:reprocess-failed-pix
        {--ids= : IDs específicos separados por vírgula (padrão: todos com ai_data nulo)}
        {--notify : Envia a resposta no grupo do WhatsApp para cada comprovante reprocessado}
        {--dry-run : Não grava nada nem credita carteiras, apenas mostra o que aconteceria}';

    protected $description = 'Reprocessa comprovantes PIX do WhatsApp que falharam por erro da OpenAI (ai_data nulo)';

    public function handle(WhatsappWebhookController $controller): int
    {
        $query = WhatsappPixExtraction::whereNull('ai_data')->orderBy('id');

        if ($ids = $this->option('ids')) {
            $query->whereIn('id', array_map('intval', explode(',', $ids)));
        }

        $extractions = $query->get();

        if ($extractions->isEmpty()) {
            $this->info('Nenhum comprovante pendente de reprocessamento.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $notify = (bool) $this->option('notify');

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Reprocessando {$extractions->count()} comprovante(s)...");

        $rows = [];

        foreach ($extractions as $extraction) {
            try {
                $result = $controller->reprocess($extraction, persist: !$dryRun, notify: $notify);

                $rows[] = [
                    $extraction->id,
                    $result['status'] ?? '-',
                    $result['pix_nome'] ?? '-',
                    $result['pix_valor'] ?? '-',
                    $result['bank_transaction_id'] ?? '-',
                ];
            } catch (\Throwable $e) {
                $rows[] = [$extraction->id, 'ERRO', $e->getMessage(), '-', '-'];
            }
        }

        $this->table(['ID', 'Status', 'Nome', 'Valor', 'Bank TX'], $rows);

        return self::SUCCESS;
    }
}
