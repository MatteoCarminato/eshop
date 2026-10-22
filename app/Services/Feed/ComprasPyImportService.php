<?php

namespace App\Services\Feed;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia produtos pro ComprasPY pela API de importação
 * (`POST /api/products/import/`, ver config/feeds.php).
 *
 * É o caminho rápido do mesmo catálogo que o ComprasPyFeedService publica em
 * XML: o feed é lido por eles de hora em hora, enquanto este envio sai no
 * mesmo request em que o Preço Web é salvo em /products-admin — é o que
 * garante que o anúncio lá nunca mostre um preço que a loja já não cobra.
 * Nenhum dos dois substitui o outro: o XML continua sendo a base do catálogo
 * (inclusive de quem nunca teve o preço editado), este é o empurrão pontual.
 *
 * Contrato do lado deles (do schema em
 * https://products.comprasparaguai.com.br/api/schema/swagger-ui/):
 *
 * - Autenticação no header `token` (token da loja). O schema também aceita
 *   `Authorization: Bearer <jwt>`, mas os dois são EXCLUDENTES: mandar o token
 *   da loja (um UUID) também como Bearer faz o middleware de JWT recusar com
 *   401 "Given token not valid for any token type" antes de alguém olhar o
 *   header `token` — conferido contra a API de produção. Por isso headers()
 *   manda um só.
 * - Body é uma LISTA de produtos, no máximo 500 por lote.
 * - Obrigatórios: `code`, `name.pt`, `price`, `stock`, `description.pt`,
 *   `url_image` e `link`. Produto sem o que preencher um desses é pulado aqui
 *   em vez de ser recusado lá (ver payloadFor()).
 * - `url_image` tem de terminar em .png/.jpg/.jpeg/.webp (a extensão é olhada
 *   no caminho, query string é aceita) e a imagem é baixada de forma
 *   assíncrona — a URL precisa continuar de pé depois do envio.
 * - `stock = 0` é como se derruba um anúncio; é o que mandamos quando o
 *   produto deixou de ser vendável.
 * - Resposta de sucesso é **207**, com `products_processed`/`products_failed`
 *   e os motivos em `validation_errors` — lote aceito em parte é o normal, não
 *   exceção, então o que falhou vai pro log com o motivo.
 *
 * Preços vão todos em DÓLAR (o catálogo do ERP é negociado em dólar, e eles
 * recusam valor em real).
 */
class ComprasPyImportService
{
    /** Teto por requisição, definido por eles. */
    public const BATCH_SIZE = 500;

    /**
     * Envia um produto só — é o que a grade de preços chama ao salvar o Preço
     * Web. Devolve false quando não havia o que enviar (envio desligado ou
     * produto sem os campos obrigatórios).
     */
    public function pushProduct(Product $product): bool
    {
        $payload = $this->payloadFor($product);

        if ($payload === null) {
            return false;
        }

        return $this->send([$payload]);
    }

    /**
     * Envia uma coleção qualquer de produtos, em lotes de BATCH_SIZE.
     *
     * @param  iterable<Product>  $products
     * @return int quantos produtos foram realmente enviados
     */
    public function pushMany(iterable $products): int
    {
        $lote = [];
        $enviados = 0;

        foreach ($products as $product) {
            $payload = $this->payloadFor($product);

            if ($payload === null) {
                continue;
            }

            $lote[] = $payload;

            if (count($lote) >= self::BATCH_SIZE) {
                $enviados += $this->send($lote) ? count($lote) : 0;
                $lote = [];
            }
        }

        if ($lote !== []) {
            $enviados += $this->send($lote) ? count($lote) : 0;
        }

        return $enviados;
    }

    /**
     * Monta o item de um produto, ou null quando falta algo obrigatório.
     *
     * O preço é o **Preço Web** (`web_price`), não o `sitePrice()` que o feed
     * XML publica: aqui o gatilho é justamente a edição dessa coluna, e é ela
     * que o anúncio do ComprasPY acompanha. Os três campos de preço levam o
     * mesmo valor — `price_iva` (venda a paraguaio) e `price_ecommerce_br`
     * (remessa pro Brasil) não têm acréscimo nosso, e `price_iva` ausente
     * faria o domínio paraguaio cair no `price` de qualquer forma.
     *
     * @return array<string, mixed>|null
     */
    public function payloadFor(Product $product): ?array
    {
        $code = $product->feedCode();
        $name = trim((string) $product->name);
        $image = $this->imageUrl($product);
        $price = round((float) $product->web_price, 2);

        if ($name === '' || $image === null || $price <= 0) {
            Log::warning('Produto não enviado ao ComprasPY: falta campo obrigatório', [
                'product_id' => $product->id,
                'code' => $code,
                'tem_imagem' => $image !== null,
                'web_price' => $price,
            ]);

            return null;
        }

        $url = $this->productUrl($product);

        return [
            'code' => $code,
            // Só `pt`: o catálogo não tem tradução pro espanhol, e `name.es`
            // é opcional. Mandar o nome português como se fosse espanhol não
            // acrescentaria nada ao anúncio.
            'name' => ['pt' => $name],
            'price' => $price,
            'price_iva' => $price,
            // 1 quando vendável; 0 derruba o anúncio (ver isSellable()).
            // Nunca o estoque real do ERP: o número por lá é controle interno
            // e às vezes vem fracionado, e pro anúncio só importa se tem ou
            // não tem.
            'stock' => $this->isSellable($product) ? 1 : 0,
            'brand' => $product->brand?->name,
            // `description.pt` é obrigatório lá e quase todo produto nosso
            // está sem descrição — nesse caso vai o próprio nome, que é a
            // informação que temos, em vez de o produto ser recusado.
            'description' => ['pt' => $this->description($product) ?: $name],
            'url_image' => $image,
            'link' => $url,
            'link_purchase' => $url,
            'price_ecommerce_br' => $price,
            'link_ecommerce_br' => $url,
            // A loja troca a foto mantendo a URL (o nome do arquivo no Spaces
            // é fixo por produto), então não dá pra confiar na URL ter mudado.
            'force_image_update' => (bool) config('feeds.compraspy.api.force_image_update'),
        ];
    }

    /**
     * Mesma regra de vendável do feed XML (ComprasPyFeedService::eligibleQuery()
     * e OrbitaCatalogService::sellableQuery()), aplicada a um produto só:
     * ativo, com estoque e com Preço Web acima do piso. Aqui o piso vale sobre
     * o `web_price` porque é ele que vai no anúncio.
     */
    public function isSellable(Product $product): bool
    {
        return (bool) $product->active
            && (float) $product->stock > 0
            && (float) $product->web_price > (float) config('feeds.compraspy.minimum_price');
    }

    /**
     * Dispara um lote. Nunca lança: quem chama está no meio de salvar um
     * preço, e o ComprasPY fora do ar não pode derrubar a gravação — o feed
     * XML continua sendo lido de hora em hora e corrige sozinho.
     *
     * @param  array<int, array<string, mixed>>  $itens
     */
    private function send(array $itens): bool
    {
        if ($itens === [] || ! $this->enabled()) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('feeds.compraspy.api.timeout'))
                ->withHeaders($this->headers())
                ->acceptJson()
                ->asJson()
                ->post($this->url(), $itens);
        } catch (Throwable $e) {
            Log::error('Falha ao enviar produtos ao ComprasPY', [
                'itens' => count($itens),
                'erro' => $e->getMessage(),
            ]);

            return false;
        }

        // 207 é o sucesso documentado (lote pode ser aceito em parte).
        if ($response->failed()) {
            Log::error('ComprasPY recusou o envio de produtos', [
                'status' => $response->status(),
                'itens' => count($itens),
                'codigos' => array_column($itens, 'code'),
                'resposta' => $response->body(),
            ]);

            return false;
        }

        $falhas = (int) $response->json('products_failed', 0);

        if ($falhas > 0) {
            Log::warning('ComprasPY recusou parte do lote', [
                'processados' => $response->json('products_processed'),
                'falhas' => $falhas,
                'motivos' => $response->json('validation_errors'),
            ]);
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        // Header `token` é o que o schema marca como obrigatório, e é o que o
        // token da loja (UUID) satisfaz — tem precedência.
        $token = (string) config('feeds.compraspy.api.token');
        if ($token !== '') {
            return ['token' => $token];
        }

        // Bearer é a outra forma aceita (securityScheme jwtAuth), pra quando a
        // credencial for um JWT de verdade. Nunca junto com a de cima: ver o
        // docblock da classe.
        $bearer = (string) config('feeds.compraspy.api.bearer');
        if ($bearer !== '') {
            return ['Authorization' => 'Bearer ' . $bearer];
        }

        return [];
    }

    /**
     * Sem URL ou sem credencial não há envio — ambiente local e testes ficam
     * só com o feed XML, que não depende de token.
     */
    public function enabled(): bool
    {
        return $this->url() !== '' && $this->headers() !== [];
    }

    private function url(): string
    {
        return trim((string) config('feeds.compraspy.api.url'));
    }

    /**
     * URL do produto na loja Orbita — a mesma que o feed XML publica, pra o
     * anúncio da API e o do XML não levarem o cliente a páginas diferentes.
     */
    private function productUrl(Product $product): string
    {
        return rtrim((string) config('feeds.compraspy.store_url'), '/') . '/produtos/' . $product->slug;
    }

    private function description(Product $product): string
    {
        $text = html_entity_decode((string) $product->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    }

    /**
     * URL absoluta da foto, ou null quando não há foto usável.
     *
     * Eles exigem `url_image` e checam a extensão no caminho da URL, então
     * foto faltando ou em formato que eles não baixam (um .gif, por exemplo)
     * significa produto pulado: mandar o item sem imagem só geraria recusa do
     * outro lado. `compraspy.api.fallback_image`, se configurada, cobre esse
     * caso com uma imagem genérica da loja.
     */
    private function imageUrl(Product $product): ?string
    {
        $candidatas = [
            trim((string) $product->image_url),
            trim((string) config('feeds.compraspy.api.fallback_image')),
        ];

        foreach ($candidatas as $candidata) {
            if ($candidata === '') {
                continue;
            }

            $url = filter_var($candidata, FILTER_VALIDATE_URL)
                ? $candidata
                : rtrim((string) config('feeds.compraspy.store_url'), '/') . '/' . ltrim($candidata, '/');

            if ($this->hasSupportedExtension($url)) {
                return $url;
            }
        }

        return null;
    }

    private function hasSupportedExtension(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true);
    }
}
