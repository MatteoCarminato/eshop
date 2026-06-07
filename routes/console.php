<?php

use App\Jobs\ProcessWhatsappScheduledMessagesJob;
use App\Services\TreasuryService;
use App\Services\WalletService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('treasury:undo-sale {saleId} {--dry-run}', function (string $saleId) {
    /** @var TreasuryService $service */
    $service = app(TreasuryService::class);
    $result = $service->undoSale((int) $saleId, (bool) $this->option('dry-run'));

    $this->info(sprintf(
        'Sale #%d %s — cliente %d — US$ %.4f — R$ %.2f — transações: %d — profit lots: %d',
        $result['sale_id'],
        $result['mode'],
        $result['client_id'],
        $result['usd_amount'],
        $result['brl_total'],
        $result['transactions'],
        $result['profit_lots']
    ));
})->purpose('Undo a TreasurySale (Venda Dólar) by id');

Artisan::command('treasury:undo-pre-purchase {depositId} {--dry-run}', function (string $depositId) {
    /** @var TreasuryService $service */
    $service = app(TreasuryService::class);
    $result = $service->undoPrePurchase((int) $depositId, (bool) $this->option('dry-run'));

    $this->info(sprintf(
        'Pre-purchase deposit #%d — cliente %d — R$ %.2f — US$ %.4f — lots: %d — cash lots: %d',
        $result['deposit_id'],
        $result['client_id'],
        $result['brl_total'],
        $result['usd_total'],
        $result['pre_purchase_lots'],
        $result['cash_lots']
    ));
})->purpose('Undo a complete pre-purchase by deposit id');

Artisan::command('wallet:recalculate-brl {clientId} {--dry-run}', function (string $clientId) {
    /** @var WalletService $service */
    $service = app(WalletService::class);
    $result = $service->recalculateBrlBalance((int) $clientId, (bool) $this->option('dry-run'));

    $this->info(sprintf(
        'Cliente #%d — BRL atual: R$ %.2f — recalculado: R$ %.2f — diferença: R$ %.2f — modo: %s',
        $result['client_id'],
        $result['current_balance'],
        $result['computed_balance'],
        $result['difference'],
        $result['mode']
    ));
})->purpose('Recalculate a client BRL wallet balance from transactions');

Schedule::job(new ProcessWhatsappScheduledMessagesJob())
    ->dailyAt('07:30')
    ->timezone('America/Sao_Paulo')
    ->name('whatsapp-scheduled-messages');
