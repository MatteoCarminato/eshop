<?php

namespace App\Http\Controllers;

use App\Services\Feed\ComprasPyFeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Feed XML do ComprasPY — ver docs/ComprasPy-Feed.md.
 *
 * As duas rotas têm públicos opostos de propósito: `show` é anônima (o
 * agregador busca o XML sem credencial, igual ao arquivo estático que o cron
 * legado publicava no public_html) e `refresh` é o botão do admin, atrás de
 * auth + products.manage.
 */
class ComprasPyFeedController extends Controller
{
    public function show(ComprasPyFeedService $feed): Response
    {
        return response($feed->cachedXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // Mesmo TTL do cache do lado do servidor: não faz sentido o
            // ComprasPY (ou um CDN no meio) revalidar mais vezes do que o
            // conteúdo pode mudar.
            'Cache-Control' => 'public, max-age=' . max(0, (int) config('feeds.compraspy.cache_ttl')),
        ]);
    }

    /**
     * Regenera o feed na hora, a partir do botão "Atualizar XML" da grade
     * Produtos × Preços — pra quem acabou de mexer em preço/estoque não ter
     * que esperar o ciclo de uma hora do scheduler.
     *
     * Síncrono dentro do request: a montagem é uma query e um DOM em memória
     * (décimos de segundo pro catálogo atual), então não vale uma fila.
     */
    public function refresh(ComprasPyFeedService $feed): RedirectResponse
    {
        try {
            $feed->refresh();

            return back()->with(
                'success',
                "Feed XML do ComprasPY atualizado: {$feed->itemCount()} produtos publicados."
            );
        } catch (Throwable $e) {
            Log::error('ComprasPyFeedController: falha ao regenerar o feed', [
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Falha ao atualizar o feed XML: ' . $e->getMessage());
        }
    }
}
