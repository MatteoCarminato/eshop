<?php

namespace App\Http\Controllers;

use App\Models\TreasuryLot;
use App\Models\TreasurySale;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TreasuryController extends Controller
{
    public function __construct(protected TreasuryService $treasuryService) {}

    public function index(Request $request)
    {
        $summary = $this->treasuryService->summary();

        // Filtro de período aplicado ao PnL acumulado das vendas.
        $from = $request->input('date_from');
        $to   = $request->input('date_to');
        $dateFrom = $from ? Carbon::parse($from)->startOfDay() : null;
        $dateTo   = $to ? Carbon::parse($to)->endOfDay() : null;

        $salesQ = TreasurySale::query()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo));

        $pnlPeriodo = (float) $salesQ->sum('realized_pnl_brl');
        $usdVendidoPeriodo = (float) $salesQ->sum('usd_amount');

        $lotes = TreasuryLot::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $vendas = TreasurySale::query()
            ->with('client')
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $clients = \App\Models\Client::query()
            ->where('is_exchange_client', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.treasury.index', compact(
            'summary', 'lotes', 'vendas', 'clients',
            'pnlPeriodo', 'usdVendidoPeriodo', 'dateFrom', 'dateTo'
        ));
    }

    public function storeAport(Request $request)
    {
        $data = $request->validate([
            'usd_amount'   => 'required|numeric|min:0.01',
            'cost_rate'    => 'required|numeric|min:0.000001',
            'purchased_at' => 'nullable|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        try {
            $this->treasuryService->addLot(
                (float) $data['usd_amount'],
                (float) $data['cost_rate'],
                $data['notes'] ?? null,
                !empty($data['purchased_at']) ? Carbon::parse($data['purchased_at']) : null
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Aporte registrado no caixa.');
    }

    public function sellToClient(Request $request)
    {
        $data = $request->validate([
            'client_id'  => 'required|exists:clients,id',
            'usd_amount' => 'required|numeric|min:0.01',
            'sell_rate'  => 'required|numeric|min:0.000001',
            'notes'      => 'nullable|string|max:500',
        ]);

        try {
            $sale = $this->treasuryService->sellToClient(
                (int) $data['client_id'],
                (float) $data['usd_amount'],
                (float) $data['sell_rate'],
                $data['notes'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $pnl = (float) $sale->realized_pnl_brl;
        return back()->with('success',
            'Venda registrada: US$ ' . number_format($sale->usd_amount, 2, ',', '.') .
            ' @ ' . number_format($sale->sell_rate, 4, ',', '.') .
            ' = R$ ' . number_format($sale->brl_total, 2, ',', '.') .
            ' — Lucro: ' . ($pnl >= 0 ? '+' : '') . 'R$ ' . number_format($pnl, 2, ',', '.')
        );
    }

    /**
     * Finaliza transações USD em 'aguardando_venda' geradas pelo fechamento de dólar.
     */
    public function sellPending(Request $request)
    {
        $data = $request->validate([
            'client_id'         => 'required|exists:clients,id',
            'transaction_ids'   => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
            'sell_rate'         => 'required|numeric|min:0.000001',
            'notes'             => 'nullable|string|max:500',
        ]);

        try {
            $sale = $this->treasuryService->sellPendingTransactions(
                (int) $data['client_id'],
                array_map('intval', $data['transaction_ids']),
                (float) $data['sell_rate'],
                $data['notes'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        $pnl = (float) $sale->realized_pnl_brl;
        return back()->with('success',
            'Venda do fechamento finalizada: US$ ' . number_format($sale->usd_amount, 2, ',', '.') .
            ' @ ' . number_format($sale->sell_rate, 4, ',', '.') .
            ' = R$ ' . number_format($sale->brl_total, 2, ',', '.') .
            ' — Lucro: ' . ($pnl >= 0 ? '+' : '') . 'R$ ' . number_format($pnl, 2, ',', '.')
        );
    }
}
