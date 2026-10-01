<?php

namespace App\Http\Controllers;

use App\Services\Erp\ErpCatalogSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Dispara a sincronização de marcas/produtos com o ERP Consoft a partir do
 * admin do site (botões "Sincronizar com ERP" nas telas de Marcas/Produtos).
 *
 * Roda de forma síncrona dentro do próprio request — são tabelas pequenas
 * (dezenas/centenas de linhas), então não há necessidade de fila aqui.
 */
class CatalogSyncController extends Controller
{
    public function __construct(protected ErpCatalogSyncService $syncService)
    {
    }

    public function syncBrands(Request $request): RedirectResponse
    {
        try {
            $result = $this->syncService->syncBrands();

            return redirect()->route('brands.index')->with(
                'success',
                "Sincronização concluída: {$result['created']} criados, {$result['updated']} atualizados, {$result['skipped']} ignorados."
            );
        } catch (\Throwable $e) {
            Log::error('CatalogSyncController: falha ao sincronizar marcas com o ERP', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('brands.index')->with(
                'error',
                'Falha ao sincronizar marcas com o ERP: ' . $e->getMessage()
            );
        }
    }

    public function syncProducts(Request $request): RedirectResponse
    {
        try {
            $result = $this->syncService->syncProducts();

            return redirect()->route('products.index')->with(
                'success',
                "Sincronização concluída: {$result['created']} criados, {$result['updated']} atualizados, {$result['skipped']} ignorados."
            );
        } catch (\Throwable $e) {
            Log::error('CatalogSyncController: falha ao sincronizar produtos com o ERP', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('products.index')->with(
                'error',
                'Falha ao sincronizar produtos com o ERP: ' . $e->getMessage()
            );
        }
    }
}
