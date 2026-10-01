<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Orbita\ProductResource;
use App\Services\Orbita\OrbitaCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pública (somente leitura) de produtos consumida pelo frontend Orbita.
 * Autenticada via middleware `orbita.token` (ver routes/api.php).
 */
class OrbitaProductController extends Controller
{
    public function __construct(private OrbitaCatalogService $catalog)
    {
    }

    /**
     * GET /api/orbita/products
     * Serve tanto getProducts() (só lê `data`) quanto searchProducts()
     * (lê `data` + `meta`, com facets/paginação) do frontend — mesmo path.
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->catalog->search([
            'q' => $request->query('q'),
            'category' => $request->query('category'),
            'brand' => $request->query('brand'),
            'model' => $request->query('model'),
            'storage' => $request->query('storage'),
            'color' => $request->query('color'),
            'price_min' => $request->query('price_min'),
            'price_max' => $request->query('price_max'),
            'sort' => $request->query('sort'),
            'page' => $request->query('page'),
            'limit' => $request->query('limit'),
        ]);

        return response()->json([
            'data' => ProductResource::collection($result['products']),
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /api/orbita/products/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->catalog->findSellableBySlug($slug);

        if (!$product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        $resource = new ProductResource($product);
        $resource->includeBrandLogo = true;

        return response()->json(['data' => $resource]);
    }

    /**
     * GET /api/orbita/promotions
     * O eshop ainda não tem o conceito de promoção (preço "de/por" com prazo/
     * quantidade limitados) — o front já trata 404/erro aqui como "sem
     * ofertas" (getPromotedProducts tem try/catch e cai pra lista vazia), mas
     * respondemos 200 com lista vazia mesmo assim, por ser mais correto que
     * um erro para um recurso que existe no contrato mas está vazio.
     */
    public function promotions(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
