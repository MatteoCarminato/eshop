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
    // Cargos & Permissões (gerenciamento)
    Route::middleware('module:roles.manage')->group(function () {
        Route::resource('roles', \App\Http\Controllers\RoleController::class)
            ->except(['show']);
    });

    Route::middleware('module:users.manage')->group(function () {
        Route::resource('users', \App\Http\Controllers\UserController::class)
            ->except(['show']);
    });

    Route::resource('groups', \App\Http\Controllers\GroupController::class)
        ->middleware('module:groups.view');
    Route::post('groups/{group}/add-clients', [\App\Http\Controllers\GroupController::class, 'addClients'])
        ->name('groups.addClients')
        ->middleware('module:groups.manage');

    Route::resource('clients', \App\Http\Controllers\ClientController::class)
        ->middleware('module:clients.view');
    Route::post('clients/add-to-groups', [\App\Http\Controllers\ClientController::class, 'addToGroups'])
        ->name('clients.addToGroups')
        ->middleware('module:clients.manage');

    // Carteira/Admin Wallet
    Route::prefix('admin/wallet')->name('admin.wallet.')->group(function () {
        Route::middleware('module:wallet.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\WalletController::class, 'index'])->name('index');
            Route::get('/client/{client}', [\App\Http\Controllers\WalletController::class, 'clientWallet'])->name('client');
            Route::get('/client/{client}/export', [\App\Http\Controllers\WalletController::class, 'exportClientCsv'])->name('client.export');
            Route::get('/transactions', [\App\Http\Controllers\WalletController::class, 'transactions'])->name('transactions');
            Route::get('/usd-brl-rate', [\App\Http\Controllers\WalletController::class, 'fetchUsdBrlRate'])->name('usd-brl-rate');
        });

        Route::middleware('module:wallet.manage')->group(function () {
            Route::patch('/transactions/{transaction}/rate', [\App\Http\Controllers\WalletController::class, 'updateDepositRate'])->name('update-rate');
            Route::patch('/transactions/rate/bulk', [\App\Http\Controllers\WalletController::class, 'updateDepositRateBulk'])->name('update-rate-bulk');
            Route::post('/fechamento-dolar', [\App\Http\Controllers\WalletController::class, 'fechamentoDolar'])->name('fechamento-dolar');
            Route::post('/pre-purchase-dollar', [\App\Http\Controllers\WalletController::class, 'prePurchaseDollar'])->name('pre-purchase-dollar');
            Route::get('/deposit', function () { return view('admin.wallet.deposit'); })->name('deposit');
            Route::post('/deposit', [\App\Http\Controllers\WalletController::class, 'deposit']);
            Route::get('/withdraw', function () { return view('admin.wallet.withdraw'); })->name('withdraw');
            Route::post('/withdraw', [\App\Http\Controllers\WalletController::class, 'withdraw']);
            Route::post('/exchange', [\App\Http\Controllers\WalletController::class, 'exchange'])->name('exchange');
        });
    });
});




Route::fallback(function () {
    return view('welcome');
});
