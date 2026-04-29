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
        Route::get('/transactions', [\App\Http\Controllers\WalletController::class, 'transactions'])->name('transactions');
        Route::get('/deposit', function() { return view('admin.wallet.deposit'); })->name('deposit');
        Route::post('/deposit', [\App\Http\Controllers\WalletController::class, 'deposit']);
        Route::get('/withdraw', function() { return view('admin.wallet.withdraw'); })->name('withdraw');
        Route::post('/withdraw', [\App\Http\Controllers\WalletController::class, 'withdraw']);
        Route::get('/exchange', function() { return view('admin.wallet.exchange'); })->name('exchange');
        Route::post('/exchange', [\App\Http\Controllers\WalletController::class, 'exchange']);
        Route::get('/', [\App\Http\Controllers\WalletController::class, 'index'])->name('index');
    });
});




Route::fallback(function () {
    return view('welcome');
});
