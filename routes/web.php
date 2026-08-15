<?php

use App\Http\Controllers\AiController;
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




Route::middleware('auth')->prefix('admin/ai')->name('admin.ai.')->group(function () {
    Route::get('/', [AiController::class, 'index'])->name('index');
    Route::post('/analyze', [AiController::class, 'analyzeExtract'])->name('analyze');
});

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
            Route::get('/pix-daily-pdf', [\App\Http\Controllers\WalletController::class, 'exportDailyPixPdf'])->name('pix-daily-pdf');
            Route::get('/client/{client}', [\App\Http\Controllers\WalletController::class, 'clientWallet'])->name('client');
            Route::get('/client/{client}/export', [\App\Http\Controllers\WalletController::class, 'exportClientCsv'])->name('client.export');
            Route::get('/client/{client}/export-xlsx', [\App\Http\Controllers\WalletController::class, 'exportClientXlsx'])->name('client.export-xlsx');
            Route::get('/client/{client}/export-pdf', [\App\Http\Controllers\WalletController::class, 'exportClientPdf'])->name('client.export-pdf');
            Route::post('/client/{client}/export-pdf-async', [\App\Http\Controllers\WalletController::class, 'startPdfExport'])->name('client.export-pdf-async');
            Route::get('/export-pdf-poll/{key}', [\App\Http\Controllers\WalletController::class, 'pollPdfExport'])->name('export-pdf-poll');
            Route::get('/export-pdf-download/{key}', [\App\Http\Controllers\WalletController::class, 'downloadPdfExport'])->name('export-pdf-download');
            Route::get('/transactions', [\App\Http\Controllers\WalletController::class, 'transactions'])->name('transactions');
            Route::get('/audit', [\App\Http\Controllers\WalletController::class, 'audit'])->name('audit');
            Route::get('/usd-brl-rate', [\App\Http\Controllers\WalletController::class, 'fetchUsdBrlRate'])->name('usd-brl-rate');
        });

        Route::middleware('module:wallet.manage')->group(function () {
            Route::patch('/transactions/{transaction}/rate', [\App\Http\Controllers\WalletController::class, 'updateDepositRate'])->name('update-rate');
            Route::patch('/transactions/rate/bulk', [\App\Http\Controllers\WalletController::class, 'updateDepositRateBulk'])->name('update-rate-bulk');
            Route::post('/fechamento-dolar', [\App\Http\Controllers\WalletController::class, 'fechamentoDolar'])->name('fechamento-dolar');
            Route::post('/pre-purchase-dollar', [\App\Http\Controllers\WalletController::class, 'prePurchaseDollar'])->name('pre-purchase-dollar');
            Route::post('/pre-sell-dollar', [\App\Http\Controllers\WalletController::class, 'preSellDollar'])->name('pre-sell-dollar');
            Route::get('/deposit', function () { return view('admin.wallet.deposit'); })->name('deposit');
            Route::post('/deposit', [\App\Http\Controllers\WalletController::class, 'deposit']);
            // Página mobile (PWA-like) para entrada rápida via atalho do celular
            Route::get('/quick-deposit', function () {
                $clients = \App\Models\Client::orderBy('name')->get(['id', 'name', 'spread_points']);
                return view('admin.wallet.quick-deposit', compact('clients'));
            })->name('quick-deposit');
            Route::get('/withdraw', function () { return view('admin.wallet.withdraw'); })->name('withdraw');
            Route::post('/withdraw', [\App\Http\Controllers\WalletController::class, 'withdraw']);
            Route::post('/exchange', [\App\Http\Controllers\WalletController::class, 'exchange'])->name('exchange');
        });

        Route::middleware('module:wallet.delete')->group(function () {
            Route::post('/deposits/{transaction}/rollback', [\App\Http\Controllers\WalletController::class, 'rollbackDeposit'])->name('rollback-deposit');
            Route::post('/withdraws/{transaction}/rollback', [\App\Http\Controllers\WalletController::class, 'rollbackWithdraw'])->name('rollback-withdraw');
            Route::post('/operations/{snapshot}/reverse', [\App\Http\Controllers\WalletController::class, 'reverseSnapshot'])->name('operations.reverse');
            Route::post('/operations/reverse-many', [\App\Http\Controllers\WalletController::class, 'reverseManySnapshots'])->name('operations.reverse-many');
        });
    });

    // Caixa próprio em USD (treasury)
    Route::prefix('admin/treasury')->name('admin.treasury.')->group(function () {
        Route::middleware('module:treasury.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\TreasuryController::class, 'index'])->name('index');
        });
        Route::middleware('module:treasury.manage')->group(function () {
            Route::post('/aport', [\App\Http\Controllers\TreasuryController::class, 'storeAport'])->name('aport');
            Route::post('/sell', [\App\Http\Controllers\TreasuryController::class, 'sellToClient'])->name('sell');
            Route::post('/sell-pending', [\App\Http\Controllers\TreasuryController::class, 'sellPending'])->name('sell-pending');
        });
    });

    // WhatsApp (conexão com serviço Node já existente)
    Route::prefix('admin/whatsapp')->name('admin.whatsapp.')->group(function () {
        Route::middleware('module:whatsapp.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\WhatsappController::class, 'index'])->name('index');
            Route::get('/envio', [\App\Http\Controllers\WhatsappController::class, 'envio'])->name('envio');
            Route::get('/agendamentos', [\App\Http\Controllers\WhatsappController::class, 'schedules'])->name('schedules');
            Route::get('/grupos-wpp', [\App\Http\Controllers\WhatsappController::class, 'wppGroups'])->name('grupos-wpp');
            Route::post('/grupos-wpp', [\App\Http\Controllers\WhatsappController::class, 'saveWppGroups'])->name('grupos-wpp.save');
            Route::get('/grupos-wpp/status', [\App\Http\Controllers\WhatsappController::class, 'wppGroupsStatus'])->name('grupos-wpp.status');
            Route::post('/grupos-wpp/refresh', [\App\Http\Controllers\WhatsappController::class, 'wppGroupsRefresh'])->name('grupos-wpp.refresh');
            Route::get('/extracoes', [\App\Http\Controllers\WhatsappController::class, 'extracoes'])->name('extracoes');
            Route::get('/extracoes/{extraction}/imagem', [\App\Http\Controllers\WhatsappController::class, 'extracoesImagem'])->name('extracoes.imagem');
            // Instância disparador
            Route::get('/status', [\App\Http\Controllers\WhatsappController::class, 'status'])->name('status');
            Route::get('/qr', [\App\Http\Controllers\WhatsappController::class, 'qr'])->name('qr');
            Route::get('/qr-image', [\App\Http\Controllers\WhatsappController::class, 'qrImage'])->name('qr-image');
            // Instância grupos
            Route::get('/grupos-instance/status', [\App\Http\Controllers\WhatsappController::class, 'statusGrupos'])->name('grupos-instance.status');
            Route::get('/grupos-instance/qr', [\App\Http\Controllers\WhatsappController::class, 'qrGrupos'])->name('grupos-instance.qr');
        });

        Route::middleware('module:whatsapp.manage')->group(function () {
            // Instância disparador
            Route::post('/connect', [\App\Http\Controllers\WhatsappController::class, 'connect'])->name('connect');
            Route::post('/disconnect', [\App\Http\Controllers\WhatsappController::class, 'disconnect'])->name('disconnect');
            Route::post('/pairing-code', [\App\Http\Controllers\WhatsappController::class, 'pairingCode'])->name('pairing-code');
            // Instância grupos
            Route::post('/grupos-instance/connect', [\App\Http\Controllers\WhatsappController::class, 'connectGrupos'])->name('grupos-instance.connect');
            Route::post('/grupos-instance/disconnect', [\App\Http\Controllers\WhatsappController::class, 'disconnectGrupos'])->name('grupos-instance.disconnect');
            Route::post('/grupos-instance/pairing-code', [\App\Http\Controllers\WhatsappController::class, 'pairingCodeGrupos'])->name('grupos-instance.pairing-code');
            Route::post('/send', [\App\Http\Controllers\WhatsappController::class, 'send'])->name('send');
            Route::post('/schedules', [\App\Http\Controllers\WhatsappController::class, 'storeSchedule'])->name('schedules.store');
            Route::patch('/schedules/{scheduledMessage}', [\App\Http\Controllers\WhatsappController::class, 'updateSchedule'])->name('schedules.update');
            Route::post('/schedules/{scheduledMessage}/toggle', [\App\Http\Controllers\WhatsappController::class, 'toggleSchedule'])
                ->name('schedules.toggle');
            Route::delete('/schedules/{scheduledMessage}', [\App\Http\Controllers\WhatsappController::class, 'destroySchedule'])
                ->name('schedules.destroy');
        });
    });
});




Route::post('/api/whatsapp/webhook', [\App\Http\Controllers\WhatsappWebhookController::class, 'handle'])
    ->name('whatsapp.webhook');

Route::fallback(function () {
    return view('welcome');
});
