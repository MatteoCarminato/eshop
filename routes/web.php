<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\ComprasPyFeedController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Feed XML do ComprasPY — rota PÚBLICA, sem auth (o agregador busca sem
// credencial). O caminho repete o do arquivo estático que o cron legado
// publicava, porque é a URL já cadastrada no ComprasPY; quem a serve no
// domínio da loja é um proxy no Orbita — ver docs/ComprasPy-Feed.md.
// Sem sessão/cookie: a resposta é um XML público e cacheável, e o grupo `web`
// grava cookie de sessão + XSRF-TOKEN em toda resposta — o que, além de ser
// ruído pro agregador, impede cache compartilhado (CDN/proxy) do feed.
Route::get('/compraspy/feedseshop.xml', [ComprasPyFeedController::class, 'show'])
    ->name('compraspy.feed')
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
    ]);


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
    Route::post('/check-receipt', [AiController::class, 'checkReceipt'])->name('check-receipt');
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

    // Catálogo: Produtos & Marcas
    Route::resource('brands', \App\Http\Controllers\BrandController::class)
        ->middleware('module:brands.view');
    Route::resource('products', \App\Http\Controllers\ProductController::class)
        ->middleware('module:products.view');

    // Grade densa de produtos × preços (visão administrativa, sem paginação)
    Route::get('products-admin', [\App\Http\Controllers\ProductAdminController::class, 'index'])
        ->name('products-admin.index')
        ->middleware('module:products.view');

    // Tela de exportação da lista de produtos com estoque (copiar pra WhatsApp)
    Route::get('products-admin/export', [\App\Http\Controllers\ProductAdminController::class, 'export'])
        ->name('products-admin.export')
        ->middleware('module:products.view');

    // Catálogo em PDF (paisagem, fundo preto) dos produtos marcados na tela de
    // exportação. POST porque a seleção pode ter centenas de ids — não cabe
    // numa query string.
    Route::post('products-admin/export/pdf', [\App\Http\Controllers\ProductAdminController::class, 'exportPdf'])
        ->name('products-admin.export-pdf')
        ->middleware('module:products.view');

    // Botão "Atualizar XML" da grade de preços: regenera o feed do ComprasPY
    // na hora, sem esperar o ciclo do scheduler.
    Route::post('products-admin/feed/refresh', [ComprasPyFeedController::class, 'refresh'])
        ->name('compraspy.feed.refresh')
        ->middleware('module:products.manage');

    // Edição inline de um preço — ver é products.view, gravar exige manage
    Route::patch('products-admin/{product}/prices', [\App\Http\Controllers\ProductAdminController::class, 'updatePrices'])
        ->name('products-admin.prices')
        ->middleware('module:products.manage');

    Route::post('brands/sync', [\App\Http\Controllers\CatalogSyncController::class, 'syncBrands'])
        ->name('brands.sync')
        ->middleware('module:brands.manage');
    Route::post('products/sync', [\App\Http\Controllers\CatalogSyncController::class, 'syncProducts'])
        ->name('products.sync')
        ->middleware('module:products.manage');

    // Carteira/Admin Wallet
    Route::prefix('admin/wallet')->name('admin.wallet.')->group(function () {
        Route::middleware('module:wallet.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\WalletController::class, 'index'])->name('index');
            Route::get('/v2', [\App\Http\Controllers\WalletController::class, 'indexV2'])->name('index.v2');
            Route::get('/pix-daily-pdf', [\App\Http\Controllers\WalletController::class, 'exportDailyPixPdf'])->name('pix-daily-pdf');
            Route::get('/client/{client}', [\App\Http\Controllers\WalletController::class, 'clientWallet'])->name('client');
            Route::get('/v2/client/{client}', [\App\Http\Controllers\WalletController::class, 'clientWalletV2'])->name('client.v2');
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

    // Invoice (porte do módulo g_invoice do painel legado)
    Route::prefix('admin/invoice')->name('admin.invoice.')->group(function () {
        Route::middleware('module:invoice.view')->group(function () {
            Route::get('/', [\App\Http\Controllers\InvoiceController::class, 'index'])->name('index');
            Route::get('/historico', [\App\Http\Controllers\InvoiceController::class, 'history'])->name('history');
            // O PDF fica em storage/, fora do public: é entregue pela rota.
            Route::get('/arquivo/{arquivo}', [\App\Http\Controllers\InvoiceController::class, 'file'])->name('file');
            Route::get('/imais', [\App\Http\Controllers\InvoiceController::class, 'imaisInfo'])->name('imais-info');
            Route::get('/{invoiceFile}/imais', [\App\Http\Controllers\InvoiceController::class, 'imais'])->name('imais');
            Route::get('/{invoiceFile}/imais/xlsx', [\App\Http\Controllers\InvoiceController::class, 'exportImais'])->name('imais-export');
        });

        Route::middleware('module:invoice.manage')->group(function () {
            // Recebe o HTML do modelo editado na tela e devolve o nome do PDF.
            Route::post('/pdf', [\App\Http\Controllers\InvoiceController::class, 'pdf'])->name('pdf');
            Route::post('/textos', [\App\Http\Controllers\InvoiceController::class, 'saveTexts'])->name('texts');
            Route::post('/upload', [\App\Http\Controllers\InvoiceController::class, 'upload'])->name('upload');
            Route::post('/{invoiceFile}/imais', [\App\Http\Controllers\InvoiceController::class, 'saveImais'])->name('imais.save');
            Route::post('/{invoiceFile}/trava', [\App\Http\Controllers\InvoiceController::class, 'toggleLock'])->name('lock');
            Route::delete('/{invoiceFile}', [\App\Http\Controllers\InvoiceController::class, 'destroy'])->name('destroy');
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
