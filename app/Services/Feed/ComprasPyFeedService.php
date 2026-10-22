<?php

namespace App\Services\Feed;

use App\Models\Product;
use DOMDocument;
use DOMElement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Feed XML (RSS 2.0) do catálogo pra ComprasPY.
 *
 * Porte do cron legado `compraspy/gerar_feed_compraspy.php` +
 * `feed_precoweb_lib.php`, que montava o XML a partir de PRODUTO_PRO ⨯
 * tb_precoweb_manual e gravava num arquivo do public_html. Mudanças
 * deliberadas no porte:
 *
 * - Fonte: a tabela local `products` (já sincronizada do ERP por
 *   `erp:sync-catalog`), não o ERP direto. Uma consulta a mais no ERP por
 *   causa do feed não se justifica quando o catálogo local é o mesmo dado.
 * - Elegibilidade: a flag por produto `destino_xml` do painel legado não tem
 *   equivalente aqui; usamos a MESMA regra de vendável da loja
 *   (active + web_price > 0 + stock > 0, ver OrbitaCatalogService) para que o
 *   feed e a vitrine nunca divirjam — anúncio de produto que a loja não vende
 *   é clique perdido.
 * - Preço: `sitePrice()` (promoção do painel quando válida, senão Preço Web),
 *   também pra casar com o que a loja cobra. Já está em USD — o preço do ERP
 *   é negociado em dólar, real/guarani são conversões de exibição.
 * - Sem arquivo e sem lock: o XML é montado sob demanda e cacheado, então não
 *   há escrita em disco, nem .tmp/rename, nem corrida entre duas gerações.
 *
 * Os nomes das tags (`codigo`, `preco`, `estoque`, `disponibilidade`,
 * `link_imagem`) e o sufixo " USD" no preço são contrato do ComprasPY: saíam
 * assim no feed antigo e precisam continuar saindo, ainda que não sejam RSS
 * padrão.
 */
class ComprasPyFeedService
{
    public const CACHE_KEY = 'compraspy_feed_xml';

    /**
     * XML do feed, servido do cache quando disponível.
     */
    public function cachedXml(): string
    {
        $ttl = (int) config('feeds.compraspy.cache_ttl');

        if ($ttl <= 0) {
            return $this->xml();
        }

        return Cache::remember(self::CACHE_KEY, $ttl, fn () => $this->xml());
    }

    /**
     * Remonta o XML e regrava o cache, independente do que havia lá. Usado
     * pelo scheduler (`compraspy:feed`) pra manter o cache quente.
     */
    public function refresh(): string
    {
        $xml = $this->xml();
        $ttl = (int) config('feeds.compraspy.cache_ttl');

        if ($ttl > 0) {
            Cache::put(self::CACHE_KEY, $xml, $ttl);
        }

        return $xml;
    }

    public function itemCount(): int
    {
        return $this->eligibleQuery()->count();
    }

    /**
     * Produtos elegíveis ao feed. Espelha OrbitaCatalogService::sellableQuery()
     * e acrescenta o piso de preço do feed legado.
     *
     * O piso é aplicado sobre o preço EFETIVO (promoção quando válida), que é
     * o número que vai pro XML — não sobre o web_price. Isso sai como dois
     * ramos em vez de um CASE porque, no SQLite, comparar uma expressão CASE
     * com um parâmetro não casa nunca: a expressão não tem afinidade de tipo,
     * então o binding fica sendo texto e número nunca é maior que texto.
     */
    private function eligibleQuery(): Builder
    {
        $minimumPrice = (float) config('feeds.compraspy.minimum_price');

        return Product::query()
            ->where('active', true)
            ->where('stock', '>', 0)
            ->where('web_price', '>', 0)
            ->where(function (Builder $query) use ($minimumPrice) {
                // Com promoção válida, o piso vale sobre ela.
                $query->where(function (Builder $promoted) use ($minimumPrice) {
                    $promoted->where('promo_price', '>', 0)
                        ->whereColumn('promo_price', '<', 'web_price')
                        ->where('promo_price', '>', $minimumPrice);
                })
                // Sem promoção válida (vazia, zerada ou >= Preço Web, que é
                // erro de digitação — ver Product::hasSitePromo()), vale o
                // Preço Web.
                ->orWhere(function (Builder $plain) use ($minimumPrice) {
                    $plain->where(function (Builder $noPromo) {
                        $noPromo->whereNull('promo_price')
                            ->orWhere('promo_price', '<=', 0)
                            ->orWhereColumn('promo_price', '>=', 'web_price');
                    })->where('web_price', '>', $minimumPrice);
                });
            })
            ->orderBy('name');
    }

    /**
     * Monta o XML completo. Em chunks porque o catálogo pode crescer bem além
     * do que couber em memória como coleção única de models.
     */
    public function xml(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $storeUrl = $this->storeUrl();

        $this->appendText($dom, $channel, 'title', (string) config('feeds.compraspy.title'));
        $this->appendText($dom, $channel, 'link', $storeUrl);
        $this->appendText($dom, $channel, 'description', (string) config('feeds.compraspy.description'));

        $this->eligibleQuery()->chunk(500, function ($products) use ($dom, $channel, $storeUrl) {
            foreach ($products as $product) {
                $this->appendItem($dom, $channel, $product, $storeUrl);
            }
        });

        return (string) $dom->saveXML();
    }

    private function appendItem(DOMDocument $dom, DOMElement $channel, Product $product, string $storeUrl): void
    {
        $name = trim((string) $product->name);
        if ($name === '') {
            return;
        }

        $item = $dom->createElement('item');
        $channel->appendChild($item);

        // Estoque é decimal no banco (vem fracionado do ERP em alguns casos);
        // o feed sempre anunciou unidade inteira, então arredonda pra baixo.
        $stock = max(0, (int) floor((float) $product->stock));

        $this->appendText($dom, $item, 'title', $name);
        $this->appendText($dom, $item, 'link', $this->productUrl($storeUrl, $product));
        $this->appendText($dom, $item, 'description', $this->cleanDescription($product->description));
        $this->appendText($dom, $item, 'codigo', $product->feedCode());
        $this->appendText($dom, $item, 'preco', number_format($product->sitePrice(), 2, '.', '') . ' USD');
        $this->appendText($dom, $item, 'estoque', (string) $stock);
        $this->appendText($dom, $item, 'disponibilidade', $stock > 0 ? 'em estoque' : 'fora de estoque');
        $this->appendText($dom, $item, 'link_imagem', $this->imageUrl($storeUrl, $product->image_url));
    }

    private function appendText(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElement($name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);
    }

    private function storeUrl(): string
    {
        return rtrim((string) config('feeds.compraspy.store_url'), '/');
    }

    /**
     * URL do produto na loja Orbita. O feed legado apontava pro
     * `produto.php?id=RECNO` do site antigo, que não existe mais.
     */
    private function productUrl(string $storeUrl, Product $product): string
    {
        return $storeUrl . '/produtos/' . $product->slug;
    }

    private function cleanDescription(?string $text): string
    {
        $text = html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * `image_url` guarda tanto URL absoluta (foto hospedada fora) quanto
     * caminho relativo — no segundo caso o ComprasPY precisa da URL completa.
     */
    private function imageUrl(string $storeUrl, ?string $imageUrl): string
    {
        $imageUrl = trim((string) $imageUrl);

        if ($imageUrl === '') {
            return '';
        }

        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $imageUrl;
        }

        return $storeUrl . '/' . ltrim($imageUrl, '/');
    }
}
