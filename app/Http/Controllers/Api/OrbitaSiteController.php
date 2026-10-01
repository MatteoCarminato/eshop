<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Orbita\OrbitaCatalogService;
use Illuminate\Http\JsonResponse;

/**
 * Endpoints de conteúdo editorial (categorias da home, banners, popups,
 * vendedores, configuração da loja) que o frontend Orbita espera do "Colmeia".
 *
 * Categorias já são reais (ver App\Services\Catalog\ProductClassifierService).
 * O eshop ainda não tem admin pra banners/popups/vendedores/configurações —
 * esses respondem com lista/valor vazio (200, contrato correto) em vez de
 * 404, pra que o Orbita simplesmente não renderize essas seções (já previsto
 * no próprio front) em vez de quebrar a página. Ver docs/Orbita-Api.md.
 */
class OrbitaSiteController extends Controller
{
    public function __construct(private OrbitaCatalogService $catalog)
    {
    }

    /**
     * Categorias reais não têm hierarquia (não modelamos sub-categoria ainda)
     * nem imagem própria — `image` sempre null, front só omite a seção se
     * quiser exigir imagem; aqui a lista de nomes já basta pra navegação.
     */
    public function categoriesHome(): JsonResponse
    {
        $categories = $this->catalog->categoriesWithCounts()
            ->values()
            ->map(fn ($c, $i) => [
                'id' => $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'image' => null,
                'order' => $i,
                'parentId' => null,
                'parentName' => null,
            ]);

        return response()->json(['data' => $categories]);
    }

    public function categoriesMenu(): JsonResponse
    {
        $categories = $this->catalog->categoriesWithCounts()
            ->map(fn ($c) => [
                'id' => $c['id'],
                'name' => $c['name'],
                'slug' => $c['slug'],
                'image' => null,
                'children' => [],
            ])
            ->values();

        return response()->json(['data' => $categories]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => null]);
    }

    public function banners(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function popups(): JsonResponse
    {
        return response()->json(['data' => []]);
    }

    public function sellers(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
