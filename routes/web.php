<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');


Route::get('/dashboard', function () {
    return view('admin.dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';




Route::middleware('auth')->group(function () {
    Route::resource('groups', \App\Http\Controllers\GroupController::class);
    Route::post('groups/{group}/add-clients', [\App\Http\Controllers\GroupController::class, 'addClients'])->name('groups.addClients');

    Route::resource('clients', \App\Http\Controllers\ClientController::class);
    Route::post('clients/add-to-groups', [\App\Http\Controllers\ClientController::class, 'addToGroups'])->name('clients.addToGroups');

    // Carteira/Admin Wallet
    Route::prefix('admin/wallet')->name('admin.wallet.')->group(function () {
        Route::get('/client/{client}', [\App\Http\Controllers\WalletController::class, 'clientWallet'])->name('client');
        Route::patch('/transactions/{transaction}/rate', [\App\Http\Controllers\WalletController::class, 'updateDepositRate'])->name('update-rate');
        Route::patch('/transactions/rate/bulk', [\App\Http\Controllers\WalletController::class, 'updateDepositRateBulk'])->name('update-rate-bulk');
        Route::post('/fechamento-dolar', [\App\Http\Controllers\WalletController::class, 'fechamentoDolar'])->name('fechamento-dolar');
        Route::get('/usd-brl-rate', [\App\Http\Controllers\WalletController::class, 'fetchUsdBrlRate'])->name('usd-brl-rate');
        Route::get('/transactions', [\App\Http\Controllers\WalletController::class, 'transactions'])->name('transactions');
        Route::get('/deposit', function() { return view('admin.wallet.deposit'); })->name('deposit');
        Route::post('/deposit', [\App\Http\Controllers\WalletController::class, 'deposit']);
        Route::get('/withdraw', function() { return view('admin.wallet.withdraw'); })->name('withdraw');
        Route::post('/withdraw', [\App\Http\Controllers\WalletController::class, 'withdraw']);
        Route::post('/exchange', [\App\Http\Controllers\WalletController::class, 'exchange'])->name('exchange');
        Route::get('/', [\App\Http\Controllers\WalletController::class, 'index'])->name('index');
    });
});




Route::fallback(function () {
    return view('welcome');
});
