<?php

namespace App\Console\Commands;

use App\Services\Erp\ErpCatalogSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncErpCatalogCommand extends Command
{
    protected $signature = 'erp:sync-catalog
        {--dry-run : não grava nada, apenas mostra o que aconteceria}
        {--only= : marcas|produtos, padrão: ambos}';

    protected $description = 'Sincroniza marcas e produtos a partir do ERP Consoft (MARCAS_MAR/PRODUTO_PRO)';

    public function handle(ErpCatalogSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only');

        if ($only !== null && !in_array($only, ['marcas', 'produtos'], true)) {
            $this->error('Opção --only inválida. Use "marcas" ou "produtos".');
            return self::FAILURE;
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}Iniciando sincronização com o ERP (Consoft)...");

        $rows = [];

        try {
            if ($only === null || $only === 'marcas') {
                $this->info("{$prefix}Sincronizando marcas (MARCAS_MAR)...");
                $result = $service->syncBrands($dryRun);
                $rows[] = ['Marcas', $result['created'], $result['updated'], $result['skipped']];
            }

            if ($only === null || $only === 'produtos') {
                $this->info("{$prefix}Sincronizando produtos (PRODUTO_PRO)...");
                $result = $service->syncProducts($dryRun);
                $rows[] = ['Produtos', $result['created'], $result['updated'], $result['skipped']];
            }
        } catch (\Throwable $e) {
            $this->error("Erro durante a sincronização com o ERP: {$e->getMessage()}");

            Log::error('erp:sync-catalog: falha inesperada', [
                'error' => $e->getMessage(),
                'only' => $only,
                'dry_run' => $dryRun,
            ]);

            return self::FAILURE;
        }

        $this->table(['Entidade', 'Criados', 'Atualizados', 'Ignorados'], $rows);

        $this->info("{$prefix}Sincronização concluída.");

        return self::SUCCESS;
    }
}
