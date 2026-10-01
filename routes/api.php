<?php

use App\Http\Controllers\Api\OrbitaProductController;
use App\Http\Controllers\Api\OrbitaSiteController;
use Illuminate\Support\Facades\Route;

// API pública somente-leitura consumida pelo frontend Orbita (Next.js).
// Autenticada via header X-Orbita-Token (middleware `orbita.token`).
// Ver docs/Orbita-Api.md.
Route::prefix('orbita')->middleware('orbita.token')->group(function () {
    Route::get('products', [OrbitaProductController::class, 'index']);
    Route::get('products/{slug}', [OrbitaProductController::class, 'show']);
    Route::get('promotions', [OrbitaProductController::class, 'promotions']);

    Route::get('categories/home', [OrbitaSiteController::class, 'categoriesHome']);
    Route::get('categories/menu', [OrbitaSiteController::class, 'categoriesMenu']);
    Route::get('settings', [OrbitaSiteController::class, 'settings']);
    Route::get('banners', [OrbitaSiteController::class, 'banners']);
    Route::get('popups', [OrbitaSiteController::class, 'popups']);
    Route::get('sellers', [OrbitaSiteController::class, 'sellers']);
});
