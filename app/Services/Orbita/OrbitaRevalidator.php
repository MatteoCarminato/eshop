<?php

namespace App\Services\Orbita;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa o frontend Orbita (Next.js) que o catálogo mudou, pra ele jogar fora o
 * cache e buscar os dados de novo.
 *
 * Por que existe: a API daqui (Api\OrbitaProductController) é lida pelo Orbita
 * com ISR — `colmeiaFetch` em web/src/lib/api.ts guarda a resposta por alguns
 * minutos. Sem este aviso, um Preço Web salvo em /products-admin só aparecia na
 * loja quando aquele cache vencia. Com ele, o preço do painel chega na vitrine
 * no mesmo instante em que a célula é salva.
 *
 * Do outro lado está web/src/app/api/revalidate/route.ts: ele confere o
 * segredo compartilhado (header X-Orbita-Revalidate) e chama revalidateTag()
 * nas tags que vierem no corpo. As tags são as mesmas declaradas lá:
 * `catalog` (listagem, busca, facets, promoções, categorias) e
 * `product:<slug>` (o detalhe de um produto).
 *
 * É melhor-esforço de propósito: o Orbita fora do ar, sem segredo configurado
 * ou devolvendo erro NUNCA pode derrubar o salvamento do preço — a base do
 * site é a fonte de verdade e o cache vence sozinho de qualquer jeito. Falha
 * vai pro log e o request segue.
 */
class OrbitaRevalidator
{
    /** Tag do Orbita que cobre tudo que é listagem/vitrine/promoção. */
    public const TAG_CATALOG = 'catalog';

    /**
     * Revalida um produto: o detalhe dele e as listagens onde ele aparece
     * (vitrine, busca, ofertas) — preço mudou em um, muda em todos.
     */
    public function product(Product $product): void
    {
        $tags = [self::TAG_CATALOG];

        if ($product->slug) {
            $tags[] = 'product:' . $product->slug;
        }

        $this->flush($tags);
    }

    /**
     * Dispara a revalidação das $tags. Chamado após a resposta já ter sido
     * enviada (ver chamadores), então a latência daqui não entra no tempo de
     * salvar a célula.
     *
     * @param  array<int, string>  $tags
     */
    public function flush(array $tags): void
    {
        if ($tags === [] || ! $this->enabled()) {
            return;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Orbita-Revalidate' => (string) config('services.orbita.revalidate_secret')])
                ->post($this->url(), ['tags' => array_values(array_unique($tags))]);

            if ($response->failed()) {
                Log::warning('Orbita recusou a revalidação de cache', [
                    'status' => $response->status(),
                    'tags' => $tags,
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Falha ao avisar o Orbita pra revalidar o cache', [
                'tags' => $tags,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sem URL ou sem segredo configurado (ambiente local, testes), não há o
     * que avisar — ver config/services.php.
     */
    public function enabled(): bool
    {
        return $this->url() !== '' && (string) config('services.orbita.revalidate_secret') !== '';
    }

    private function url(): string
    {
        return rtrim((string) config('services.orbita.revalidate_url'), '/');
    }
}
