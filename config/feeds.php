<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feed XML ComprasPY
    |--------------------------------------------------------------------------
    |
    | Porte do cron legado `compraspy/gerar_feed_compraspy.php`, que lia
    | tb_precoweb_manual e gravava o XML num arquivo do public_html. Aqui o
    | feed é gerado a partir da tabela `products` (mesma regra de vendável da
    | loja) e servido por rota pública — ver docs/ComprasPy-Feed.md.
    |
    | `store_url` é a base dos links de produto e de imagem do feed: é o
    | domínio da LOJA (Orbita), não o do painel, porque é a página que o
    | ComprasPY precisa abrir quando alguém clica no anúncio.
    |
    */

    'compraspy' => [

        'store_url' => env('COMPRASPY_STORE_URL', 'https://eshopcell.com'),

        'title' => env('COMPRASPY_FEED_TITLE', 'eShop - Produtos'),

        'description' => env('COMPRASPY_FEED_DESCRIPTION', 'Feed de produtos da eShop'),

        // Preço mínimo (USD) pra um produto entrar no feed. Herdado do legado:
        // protege contra produto com preço zerado/esquecido virar anúncio.
        'minimum_price' => (float) env('COMPRASPY_FEED_MIN_PRICE', 0.10),

        // Segundos de cache do XML montado — uma hora, o mesmo ciclo em que o
        // scheduler reaquece o cache, então em operação normal a requisição do
        // ComprasPY nunca paga a montagem. Quem precisa publicar antes disso
        // usa o botão "Atualizar XML" da grade Produtos × Preços.
        'cache_ttl' => (int) env('COMPRASPY_FEED_CACHE_TTL', 3600),

    ],

];
