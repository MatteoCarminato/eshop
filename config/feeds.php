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

        /*
        |----------------------------------------------------------------------
        | API de importação (POST /api/products/import/)
        |----------------------------------------------------------------------
        |
        | Caminho rápido do mesmo catálogo do XML: salvar o Preço Web na grade
        | empurra aquele produto na hora, em vez de esperar o ComprasPY ler o
        | feed de novo. Ver App\Services\Feed\ComprasPyImportService e
        | docs/ComprasPy-Feed.md.
        |
        | Sem `token` (ou `bearer`) o envio fica desligado e vale só o XML —
        | é o estado de ambiente local e dos testes.
        |
        */
        'api' => [

            'url' => env('COMPRASPY_API_URL', 'https://products.comprasparaguai.com.br/api/products/import/'),

            // Token da loja, mandado no header `token` (o que o schema deles
            // marca como obrigatório).
            'token' => env('COMPRASPY_API_TOKEN'),

            // Alternativa aceita pelo mesmo endpoint (securityScheme jwtAuth):
            // vai como `Authorization: Bearer <jwt>`. EXCLUDENTE com o de
            // cima — o token da loja mandado como Bearer é recusado pelo
            // middleware de JWT deles (401), então `token` tem precedência e
            // este só é usado quando aquele está vazio.
            'bearer' => env('COMPRASPY_API_BEARER'),

            'timeout' => (int) env('COMPRASPY_API_TIMEOUT', 15),

            // `url_image` é obrigatória lá e hoje quase nenhum produto tem
            // foto cadastrada. Com uma URL aqui, produto sem foto é anunciado
            // com essa imagem genérica; vazio (o padrão), ele é pulado e fica
            // só no XML, que aceita item sem imagem.
            'fallback_image' => env('COMPRASPY_API_FALLBACK_IMAGE'),

            // Eles só rebaixam a imagem quando a URL muda. A nossa muda a cada
            // upload (nome com hash), então `false` basta; `true` força o
            // re-download em todo envio, ao custo de trabalho do lado deles.
            'force_image_update' => (bool) env('COMPRASPY_API_FORCE_IMAGE_UPDATE', false),

        ],

    ],

];
