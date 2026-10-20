<?php

namespace App\Console\Commands;

use App\Services\Feed\ComprasPyFeedService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Substitui o cron legado `php .../compraspy/gerar_feed_compraspy.php`. Não
 * grava arquivo: só remonta o XML e reaquece o cache, pra que a requisição do
 * ComprasPY sempre pegue algo pronto.
 */
class GenerateComprasPyFeedCommand extends Command
{
    protected $signature = 'compraspy:feed
        {--dry-run : monta o XML e mostra o resumo sem gravar no cache}';

    protected $description = 'Regenera o feed XML do ComprasPY e reaquece o cache servido em /compraspy/feedseshop.xml';

    public function handle(ComprasPyFeedService $feed): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $startedAt = microtime(true);

        try {
            $xml = $dryRun ? $feed->xml() : $feed->refresh();
        } catch (Throwable $e) {
            Log::error('Falha ao gerar o feed ComprasPY', ['exception' => $e]);
            $this->error('ERRO ao gerar o feed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $elapsed = microtime(true) - $startedAt;

        $this->info(sprintf(
            '%sFeed ComprasPY gerado. Produtos: %d. Tamanho: %s KB. Tempo: %ss.',
            $dryRun ? '[DRY-RUN] ' : '',
            $feed->itemCount(),
            number_format(strlen($xml) / 1024, 1, ',', '.'),
            number_format($elapsed, 2, ',', '.')
        ));

        return self::SUCCESS;
    }
}
