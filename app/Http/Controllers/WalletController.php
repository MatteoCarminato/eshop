<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Http\Requests\Wallet\ExchangeRequest;
use App\Services\WalletService;
use App\Services\TransactionService;
use App\Services\CurrencyService;
use App\Services\TreasuryService;
use App\Services\OperationReversalService;
use App\Services\ExchangeRateService;
use App\Models\OperationSnapshot;
use App\Models\Transaction;
use App\Models\TransactionRateChangeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;


class WalletController extends Controller
{
    protected $walletService;
    protected $transactionService;
    protected $currencyService;
    protected $treasuryService;
    protected $reversalService;
    protected $exchangeRateService;

    public function __construct(WalletService $walletService, TransactionService $transactionService, CurrencyService $currencyService, TreasuryService $treasuryService, OperationReversalService $reversalService, ExchangeRateService $exchangeRateService)
    {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
        $this->currencyService = $currencyService;
        $this->treasuryService = $treasuryService;
        $this->reversalService = $reversalService;
        $this->exchangeRateService = $exchangeRateService;
    }

    protected function calculateDisplayedBalances(\App\Models\Client $client): array
    {
        $brlTransactions = Transaction::query()
            ->where('client_id', $client->id)
            ->where('currency', 'BRL')
            ->get();

        $brl = 0.0;
        foreach ($brlTransactions as $transaction) {
            $amount = (float) $transaction->amount;

            if ($transaction->type === 'deposit') {
                if (in_array($transaction->status, ['fechado', 'finalizado'], true)) {
                    continue;
                }

                $brl += abs($amount);
                continue;
            }

            if ($transaction->type === 'withdraw' || $transaction->type === 'exchange_out') {
                $brl -= abs($amount);
                continue;
            }

            $brl += $amount;
        }

        $usd = round((float) Transaction::query()
            ->where('client_id', $client->id)
            ->where('currency', 'USD')
            ->sum('amount'), 2);

        $brlPreSoldOpen = (float) Transaction::query()
            ->where('client_id', $client->id)
            ->where('type', 'deposit')
            ->where('currency', 'BRL')
            ->where('amount', '>', 0)
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['fechado', 'finalizado']))
            ->sum('brl_pre_sold');

        return [
            'BRL' => round($brl - $brlPreSoldOpen, 2),
            'USD' => $usd,
        ];
    }

    /**
     * Exibe as carteiras do cliente
     */
    public function index(Request $request)
    {
        $clients = \App\Models\Client::query()
            ->where('is_exchange_client', true)
            ->orderBy('name')
            ->get();

        $clientIds = $clients->pluck('id');

        $walletsByClient = $clients->mapWithKeys(function (\App\Models\Client $client) {
            return [$client->id => $this->calculateDisplayedBalances($client)];
        });

        $totals = [
            'BRL' => round((float) $walletsByClient->sum('BRL'), 2),
            'USD' => round((float) $walletsByClient->sum('USD'), 2),
        ];

        // Resumo de pré-compra por cliente (USD pré-comprado = hedge do dono, PnL realizado).
        $prePurchaseByClient = [];
        foreach ($clientIds as $cid) {
            $prePurchaseByClient[$cid] = $this->walletService->prePurchaseSummary((int) $cid);

            // "Devo ao cliente" tem que refletir o que ainda NÃO foi entregue (pré-vendido),
            // não o que falta o dono comprar pra si (pré-compra é só hedge/custo interno e
            // fica em aberto mesmo depois do dólar já ter sido entregue via "Vender DÓLAR
            // antecipado" — ver WalletService::finalizeDepositIfCovered).
            $prePurchaseByClient[$cid]['devido_ao_cliente'] = $this->walletService->brlAvailableForPreSell((int) $cid);
        }

        // Totais consolidados (estado atual — não dependem do filtro de data).
        $totals['DEVO_BRL'] = 0.0;        // R$ que devo aos clientes (ainda não entregue/pré-vendido)
        $totals['USD_PRE']  = 0.0;        // USD pré-comprado total
        $totals['CLIENTE_DEVE_BRL'] = 0.0; // saldo BRL negativo (cliente está negativo → me deve)
        $totals['CLIENTE_DEVE_USD'] = 0.0; // idem em USD

        foreach ($clientIds as $cid) {
            $totals['DEVO_BRL'] += $prePurchaseByClient[$cid]['devido_ao_cliente'] ?? 0;
            $totals['USD_PRE']  += $prePurchaseByClient[$cid]['usd_pre_comprado'] ?? 0;

            $w = $walletsByClient[$cid] ?? ['BRL' => 0, 'USD' => 0];
            if (($w['BRL'] ?? 0) < 0) $totals['CLIENTE_DEVE_BRL'] += abs($w['BRL']);
            if (($w['USD'] ?? 0) < 0) $totals['CLIENTE_DEVE_USD'] += abs($w['USD']);
        }

        // PnL total (em USD) lendo as transações USD finalizadoras dentro do período.
        // Cada fechamento grava realized_pnl_usd na transação USD criada.
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $pnlQuery = Transaction::query()
            ->whereIn('client_id', $clientIds)
            ->whereNotNull('realized_pnl_usd')
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo));

        $totals['PNL_USD'] = (float) $pnlQuery->sum('realized_pnl_usd');

        $totals['USD'] = $totals['USD'] + $totals['USD_PRE'] + $totals['PNL_USD']; // saldo USD + pré-comprado + PnL

        // PnL por cliente no período (para a coluna da tabela).
        $pnlByClient = (clone $pnlQuery)
            ->select('client_id', DB::raw('COALESCE(SUM(realized_pnl_usd), 0) as total'))
            ->groupBy('client_id')
            ->pluck('total', 'client_id');

        return view('admin.wallet.index', compact(
            'clients',
            'totals',
            'walletsByClient',
            'prePurchaseByClient',
            'pnlByClient',
            'dateFrom',
            'dateTo'
        ));
    }

     /**
     * Exibe as transações do cliente
     */
    public function transactions(Request $request)
    {
        $clientId = $request->get('client_id');
        $transactions = null;
        if ($clientId) {
            $transactions = \App\Models\Transaction::where('client_id', $clientId)->orderByDesc('created_at')->get();
        }
        return view('admin.wallet.transactions', compact('transactions'));
    }

    /**
     * Tela de auditoria: reconcilia o saldo salvo (wallets.balance) contra o saldo
     * recalculado a partir do histórico de transações (dry-run, não grava nada) para
     * todos os clientes de câmbio, e mostra um extrato consolidado e filtrável de
     * todas as transações (entradas e saídas) de todos os clientes.
     */
    public function audit(Request $request)
    {
        $clients = \App\Models\Client::query()
            ->where('is_exchange_client', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // O saldo (salvo e recalculado) é sempre acumulado desde o início — não existe
        // "saldo do período", então o filtro de data não se aplica aqui. Só o cliente
        // filtra a reconciliação (pra focar em um só); tipo/moeda/status/período filtram
        // apenas o extrato, mais abaixo.
        $reconClients = $request->filled('client_id')
            ? $clients->where('id', (int) $request->input('client_id'))
            : $clients;

        $reconciliation = $reconClients->map(function (\App\Models\Client $client) {
            $brl = $this->walletService->recalculateBrlBalance($client->id, true);
            $usd = $this->walletService->recalculateUsdBalance($client->id, true);

            return [
                'client_id'   => $client->id,
                'client_name' => $client->name,
                'brl'         => $brl,
                'usd'         => $usd,
                'has_diff'    => abs($brl['difference']) > 0.01 || abs($usd['difference']) > 0.01,
            ];
        });

        $divergentCount = $reconciliation->where('has_diff', true)->count();

        $reconTotals = [
            'brl_atual'  => round((float) $reconciliation->sum(fn ($r) => $r['brl']['current_balance']), 2),
            'brl_recalc' => round((float) $reconciliation->sum(fn ($r) => $r['brl']['computed_balance']), 2),
            'brl_diff'   => round((float) $reconciliation->sum(fn ($r) => $r['brl']['difference']), 2),
            'usd_atual'  => round((float) $reconciliation->sum(fn ($r) => $r['usd']['current_balance']), 2),
            'usd_recalc' => round((float) $reconciliation->sum(fn ($r) => $r['usd']['computed_balance']), 2),
            'usd_diff'   => round((float) $reconciliation->sum(fn ($r) => $r['usd']['difference']), 2),
        ];

        // Extrato consolidado (todos os clientes de câmbio), com filtros.
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $extratoQuery = Transaction::query()
            ->whereIn('client_id', $clients->pluck('id'))
            ->with('client:id,name')
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->input('client_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->input('currency')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->input('status');
                $status === '__null__' ? $q->whereNull('status') : $q->where('status', $status);
            })
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $extratoTotals = [
            'count' => (clone $extratoQuery)->count(),
            'brl'   => round((float) (clone $extratoQuery)->where('currency', 'BRL')->sum('amount'), 2),
            'usd'   => round((float) (clone $extratoQuery)->where('currency', 'USD')->sum('amount'), 2),
        ];

        $transactions = $extratoQuery->paginate(50)->withQueryString();

        return view('admin.wallet.audit', compact(
            'clients',
            'reconciliation',
            'divergentCount',
            'reconTotals',
            'transactions',
            'extratoTotals',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Exibe a carteira do cliente com saldos em BRL e USD e botões de ação
     */
    public function clientWallet(\App\Models\Client $client, Request $request)
    {
        $balances = $this->calculateDisplayedBalances($client);

        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $transactions = $client->transactions()
            ->with('whatsappPixExtraction:id,whatsapp_group_id,image_path,mimetype,pix_nome,pix_valor,pix_data,numero_transacao,status')
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->get();

        $prePurchaseSummary = $this->walletService->prePurchaseSummary($client->id);
        $brlAvailableForPrePurchase = $this->walletService->brlAvailableForPrePurchase($client->id);
        $preSellSummary = $this->walletService->preSellSummary($client->id);
        $brlAvailableForPreSell = $this->walletService->brlAvailableForPreSell($client->id);
        $treasuryClientSummary = $this->treasuryService->clientSummary($client->id);
        $treasurySummary = $this->treasuryService->summary();

        // Lotes em aberto por depósito (para mostrar badges 🟢 compra / 🔴 venda).
        $prePurchasesByDeposit = \App\Models\WalletPrePurchase::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['open', 'partial'])
            ->get()->groupBy('source_transaction_id');
        $preSellsByDeposit = \App\Models\WalletPreSell::query()
            ->where('client_id', $client->id)
            ->whereIn('status', ['open', 'partial'])
            ->get()->groupBy('source_transaction_id');

        // Lotes de pré-venda já fechados, agrupados pela transação USD (Entrada U$).
        $preSellsByUsdTx = \App\Models\WalletPreSell::query()
            ->where('client_id', $client->id)
            ->whereNotNull('transaction_id')
            ->with('sourceTransaction')
            ->get()
            ->groupBy('transaction_id');

        // Operações reversíveis (snapshots) ainda não revertidas deste cliente,
        // indexadas pela transação-âncora (id do depósito) para exibir os botões.
        $reversalSnapshots = OperationSnapshot::where('client_id', $client->id)
            ->whereNull('reversed_at')
            ->orderByDesc('created_at')
            ->get();

        $reversalsByAnchor = [];
        foreach ($reversalSnapshots as $snap) {
            foreach (($snap->deposit_ids ?? []) as $anchorId) {
                $reversalsByAnchor[(int) $anchorId][] = $snap;
            }
        }

        return view('admin.wallet.client', compact(
            'client',
            'balances',
            'transactions',
            'prePurchaseSummary',
            'brlAvailableForPrePurchase',
            'preSellSummary',
            'brlAvailableForPreSell',
            'prePurchasesByDeposit',
            'preSellsByDeposit',
            'preSellsByUsdTx',
            'treasuryClientSummary',
            'treasurySummary',
            'reversalsByAnchor',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Exporta extrato do cliente em CSV (UTF-8 com BOM, ; como separador) — sem cores.
     * Layout (3 colunas lado a lado):
     *   Saldo Fulano  R$ xx,xx   U$ xxx,xx
     *   Entradas R$ | Saídas U$ | Entradas U$
     */
    /**
     * Exporta o extrato do cliente em XLSX usando o template em
     * storage/app/templates/extrato_template.xlsx.
     *
     * Layout do template:
     *   Linha 1 : A1:J1 = "Saldo {NOME} R$ X.XXX,XX"   |  K1 = "U$ X.XXX,00"
     *   Linha 2 : Entrada R$ (A:E)  |  Saída U$ (F:H)  |  Entrada U$ (I:K)
     *   Linha 3 : cabeçalhos das colunas
     *   Linhas 4..10 : dados (template traz 7 linhas, expandimos conforme necessário)
     *   Linha 11 : totais
     */
    public function exportClientXlsx(\App\Models\Client $client, Request $request)
    {
        $spreadsheet = $this->buildClientStatementSpreadsheet($client, $request);

        $filename = sprintf(
            'extrato_%s_%s.xlsx',
            \Illuminate\Support\Str::slug($client->name),
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Exporta o mesmo extrato em PDF.
     *
     * Estratégia:
     *   1) Gera o XLSX em arquivo temporário (mesmo do botão Excel).
     *   2) Se o LibreOffice (`soffice`) estiver instalado, converte com ele
     *      em paisagem — resultado idêntico ao "Salvar como PDF" do Excel.
     *   3) Caso contrário, faz fallback usando Mpdf (layout aproximado).
     *
     * Para o resultado fiel ao Excel, instale o LibreOffice:
     *   macOS: `brew install --cask libreoffice`
     *   Ubuntu: `sudo apt install libreoffice`
     */
    public function startPdfExport(\App\Models\Client $client, Request $request)
    {
        $exportKey = \Illuminate\Support\Str::uuid()->toString();
        $cacheKey  = 'pdf_export_' . $exportKey;

        Cache::put($cacheKey, ['status' => 'pending'], now()->addHour());

        $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
        $dateTo   = $request->filled('date_to')   ? $request->input('date_to')   : null;

        \App\Jobs\GenerateClientPdfJob::dispatch(
            $client->id,
            $dateFrom,
            $dateTo,
            $exportKey,
        );

        return response()->json(['key' => $exportKey]);
    }

    public function pollPdfExport(string $key)
    {
        $state = Cache::get('pdf_export_' . $key);

        if (!$state) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($state);
    }

    public function downloadPdfExport(string $key)
    {
        $state = Cache::get('pdf_export_' . $key);

        if (!$state || ($state['status'] ?? '') !== 'done') {
            abort(404);
        }

        $exportDir    = storage_path('app/exports');
        $pdfPath      = $exportDir . DIRECTORY_SEPARATOR . $key . '.pdf';
        $filenamePath = $exportDir . DIRECTORY_SEPARATOR . $key . '.name';

        if (!is_file($pdfPath)) {
            abort(404);
        }

        $filename = is_file($filenamePath) ? trim(file_get_contents($filenamePath)) : $key . '.pdf';

        return response()->streamDownload(function () use ($pdfPath, $filenamePath, $key) {
            readfile($pdfPath);
            @unlink($pdfPath);
            @unlink($filenamePath);
            Cache::forget('pdf_export_' . $key);
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function exportClientPdf(\App\Models\Client $client, Request $request)
    {
        $spreadsheet = $this->buildClientStatementSpreadsheet($client, $request, true);

        $filename = sprintf(
            'extrato_%s_%s.pdf',
            \Illuminate\Support\Str::slug($client->name),
            now()->format('Ymd_His')
        );

        // 1) Salva XLSX temporário.
        $tmpDir   = storage_path('app/tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . 'extrato_' . uniqid() . '.xlsx';
        $writer   = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);

        // 2) Tenta LibreOffice headless.
        $soffice = $this->locateSoffice();
        if ($soffice) {
            $cmd = sprintf(
                '%s --headless --norestore --nofirststartwizard --convert-to pdf:"calc_pdf_Export" --outdir %s %s 2>&1',
                escapeshellarg($soffice),
                escapeshellarg($tmpDir),
                escapeshellarg($xlsxPath)
            );
            @exec($cmd, $output, $code);
            $pdfPath = preg_replace('/\.xlsx$/', '.pdf', $xlsxPath);

            if ($code === 0 && is_file($pdfPath)) {
                @unlink($xlsxPath);
                return response()->streamDownload(function () use ($pdfPath) {
                    readfile($pdfPath);
                    @unlink($pdfPath);
                }, $filename, ['Content-Type' => 'application/pdf']);
            }
            // Se falhar, segue para Mpdf.
        }

        // 3) Fallback Mpdf.
        @unlink($xlsxPath);
        \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter(
            'Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class
        );
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function exportDailyPixPdf(Request $request)
    {
        $validated = $request->validate([
            'pix_date' => 'required|date',
        ]);

        [$utcStart, $utcEnd, $localDate] = $this->parsePixDailyDateRange($validated['pix_date']);

        $transactions = Transaction::query()
            ->with(['client:id,name'])
            ->where('type', 'deposit')
            ->where('currency', 'BRL')
            ->where('payment_method', 'pix')
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->orderBy('created_at')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pix do dia');

        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1', 'PIX do dia ' . $localDate->format('d/m/Y'));
        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue(
            'A2',
            sprintf(
                'Total: %d registro(s) | Soma: R$ %s | Horario exibido em UTC-3',
                $transactions->count(),
                number_format((float) $transactions->sum('amount'), 2, ',', '.')
            )
        );

        $sheet->fromArray(['Valor', 'Cliente', 'Horario (-3)'], null, 'A4');

        $row = 5;
        foreach ($transactions as $transaction) {
            $localTime = optional($transaction->created_at)
                ? $transaction->created_at->copy()->subHours(3)->format('H:i')
                : '';

            $sheet->setCellValueExplicit('A' . $row, number_format((float) $transaction->amount, 2, ',', '.'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, (string) optional($transaction->client)->name);
            $sheet->setCellValueExplicit('C' . $row, $localTime, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $row++;
        }

        if ($transactions->isEmpty()) {
            $sheet->mergeCells('A5:C5');
            $sheet->setCellValue('A5', 'Nenhum PIX encontrado para a data selecionada.');
            $row = 6;
        }

        $lastDataRow = max(4, $row - 1);

        $sheet->getStyle('A1:C1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2:C2')->getFont()->setSize(10);
        $sheet->getStyle('A4:C4')->getFont()->setBold(true);
        $sheet->getStyle('A4:C' . $lastDataRow)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('A4:C4')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE9ECEF');

        for ($dataRow = 5; $dataRow <= $lastDataRow; $dataRow++) {
            $sheet->getStyle('A' . $dataRow . ':C' . $dataRow)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            if (($dataRow % 2) === 1) {
                $sheet->getStyle('A' . $dataRow . ':C' . $dataRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF8F9FA');
            }
        }

        $sheet->getStyle('A5:A' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C5:C' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(16);

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setLeft(0.25)->setBottom(0.35);

        \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter(
            'Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class
        );

        $filename = sprintf('pix_%s.pdf', $localDate->format('Ymd'));

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Localiza o binário do LibreOffice (`soffice`) em paths conhecidos.
     */
    protected function locateSoffice(): ?string
    {
        // Permite override via .env: LIBREOFFICE_PATH=/caminho/soffice
        $env = env('LIBREOFFICE_PATH');
        if ($env && is_executable($env)) return $env;

        $candidates = [
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            '/opt/homebrew/bin/soffice',
            '/usr/bin/libreoffice',
        ];
        foreach ($candidates as $p) {
            if (is_executable($p)) return $p;
        }

        // Tenta `which`
        $which = @shell_exec('command -v soffice 2>/dev/null');
        if ($which) {
            $which = trim($which);
            if ($which && is_executable($which)) return $which;
        }
        return null;
    }

    protected function parsePixDailyDateRange(string $date): array
    {
        $localDate = \Illuminate\Support\Carbon::parse($date, 'UTC')->startOfDay();
        $utcStart = $localDate->copy()->addHours(3);
        $utcEnd = $localDate->copy()->endOfDay()->addHours(3);

        return [$utcStart, $utcEnd, $localDate];
    }

    /**
     * Monta a Spreadsheet do extrato a partir do template em
     * storage/app/templates/extrato_template.xlsx.
     *
     * Layout do template:
     *   Linha 1 : A1:J1 = "Saldo {NOME} R$ X.XXX,XX"   |  K1 = "U$ X.XXX,00"
     *   Linha 2 : Entrada R$ (A:E)  |  Saída U$ (F:H)  |  Entrada U$ (I:K)
     *   Linha 3 : cabeçalhos das colunas
     *   Linhas 4..9 : dados (template traz 6 linhas, ajustamos conforme registros)
     *   Linha 10 : "..." (placeholder — removido)
     *   Linha 11 : totais (com borda inferior simples)
     */
    protected function buildClientStatementSpreadsheet(\App\Models\Client $client, Request $request, bool $newestFirst = false): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $balances = $this->calculateDisplayedBalances($client);
        $brl = (float) ($balances['BRL'] ?? 0);
        $usd = (float) ($balances['USD'] ?? 0);

        $all = $client->transactions()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderBy('created_at', $newestFirst ? 'desc' : 'asc')
            ->get();

        $entradasBrl = $all->filter(fn ($t) => $t->type === 'deposit' && $t->currency === 'BRL' && (float) $t->amount > 0)->values();
        $saidasUsd   = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount < 0)->values();
        $entradasUsd = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount > 0)->values();

        $rowsCount = max(1, $entradasBrl->count(), $saidasUsd->count(), $entradasUsd->count());

        $br = fn ($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');
        $dt = fn ($t) => optional($t->created_at)->format('d/m/Y H:i');

        $templatePath = storage_path('app/templates/extrato_template.xlsx');
        if (!is_file($templatePath)) {
            abort(500, 'Template extrato_template.xlsx não encontrado em storage/app/templates.');
        }

        $reader      = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($templatePath);
        $sheet       = $spreadsheet->getActiveSheet();
        // ---- Linha 1: saldos
        $sheet->setCellValue('F1', 'Saldo ' . $client->name . ' R$ ' . $br($brl));
        $sheet->setCellValue('I1', 'U$ ' . $br($usd));

        // ---- Ajustar quantidade de linhas de dados.
        // Template original: linhas 4..9 = 6 linhas com numeração 1..6,
        // linha 10 = "..." (placeholder de exemplo) e linha 11 = totais.
        // Removemos a linha do "..." e ajustamos as linhas reais conforme rowsCount.
        $firstDataRow      = 4;
        $templateDataRows  = 6;            // linhas reais (4..9)
        $placeholderRow    = 10;           // linha do "..."
        $sheet->removeRow($placeholderRow, 1); // totais agora estão na linha 10

        if ($rowsCount > $templateDataRows) {
            // Insere o que faltar ANTES da linha de totais (herda formatação).
            $extra = $rowsCount - $templateDataRows;
            $sheet->insertNewRowBefore($firstDataRow + $templateDataRows, $extra);
        } elseif ($rowsCount < $templateDataRows) {
            // Remove linhas excedentes do meio.
            $remove = $templateDataRows - $rowsCount;
            $sheet->removeRow($firstDataRow + $rowsCount, $remove);
        }
        $totalsRow = $firstDataRow + $rowsCount;

        // ---- Preenche linhas de dados
        for ($i = 0; $i < $rowsCount; $i++) {
            $row = $firstDataRow + $i;
            $e   = $entradasBrl->get($i);
            $s   = $saidasUsd->get($i);
            $eu  = $entradasUsd->get($i);

            // Bloco "Entrada R$" (A..E): N | Data | Valor R$ | Taxa | Valor U$
            $sheet->setCellValue('A' . $row, $i + 1);
            if ($e) {
                $valorUsd = null;
                if ($e->converted_currency === 'USD' && $e->converted_amount !== null) {
                    $valorUsd = (float) $e->converted_amount;
                } elseif ((float) $e->exchange_rate > 0) {
                    $valorUsd = (float) $e->amount / (float) $e->exchange_rate;
                }
                $sheet->setCellValueExplicit('B' . $row, $dt($e), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, $br((float) $e->amount, 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $row, $e->exchange_rate ? $br((float) $e->exchange_rate, 4) : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E' . $row, $valorUsd !== null ? $br($valorUsd, 2) : '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                // Limpa células do bloco para apagar valores que vieram do template (1..6).
                foreach (['B','C','D','E'] as $col) $sheet->setCellValue($col . $row, null);
            }

            // Bloco "Saída U$" (F..H): Data | Valor U$ | Descrição
            if ($s) {
                $sheet->setCellValueExplicit('F' . $row, $dt($s), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G' . $row, $br(abs((float) $s->amount), 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('H' . $row, (string) ($s->description ?? ''));
            }

            // Bloco "Entrada U$" (I..K): Data | Valor U$ | Descrição
            if ($eu) {
                $sheet->setCellValueExplicit('I' . $row, $dt($eu), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J' . $row, $br((float) $eu->amount, 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue('K' . $row, (string) ($eu->description ?? ''));
            }
        }

        // ---- Linha de totais
        //valores com 2 casa decimais, vírgula como separador decimal e ponto como separador de milhar.
        $totalEntradaBrl = (float) $entradasBrl->sum('amount');
        $totalEntradaUsdEquiv = $entradasBrl->sum(function ($t) {
            if ($t->converted_currency === 'USD' && $t->converted_amount !== null) {
                return (float) $t->converted_amount;
            }
            return ((float) $t->exchange_rate > 0) ? (float) $t->amount / (float) $t->exchange_rate : 0.0;
        });
        $totalSaidaUsd   = $saidasUsd->sum(fn ($t) => abs((float) $t->amount));
        $totalEntradaUsd = (float) $entradasUsd->sum('amount');

        $sheet->setCellValue('C' . $totalsRow, 'R$ ' . $br($totalEntradaBrl));
        $sheet->setCellValue('E' . $totalsRow, 'U$ ' . $br($totalEntradaUsdEquiv));
        $sheet->setCellValue('G' . $totalsRow, 'U$ ' . $br($totalSaidaUsd));
        $sheet->setCellValue('J' . $totalsRow, 'U$ ' . $br($totalEntradaUsd));
        $sheet->setCellValue('K' . $totalsRow, '');

        // ---- Borda simples embaixo da linha de totais (última linha do extrato).
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)
            ->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // ---- Bordas leves entre as linhas de dados (separação sutil) + zebra striping.
        $cinza  = 'FFF2F2F2';
        $branco = 'FFFFFFFF';
        $bordaLeve = 'FFD9D9D9';
        for ($i = 0; $i < $rowsCount; $i++) {
            $row = $firstDataRow + $i;
            $cor = ($i % 2 === 0) ? $cinza : $branco;

            $rowStyle = $sheet->getStyle('A' . $row . ':K' . $row);
            $rowStyle->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($cor);
            $rowStyle->getBorders()->getBottom()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR)
                ->getColor()->setARGB($bordaLeve);

            // Altura maior para dar respiro vertical entre linhas.
            $sheet->getRowDimension($row)->setRowHeight(22);
        }

        // ---- Alinhamento à direita nas colunas de valores + fonte um pouco menor (dados).
        $colsRight = ['C', 'D', 'E', 'G', 'J'];
        $rangeStart = $firstDataRow;
        $rangeEnd   = $totalsRow;
        foreach ($colsRight as $col) {
            $style = $sheet->getStyle($col . $rangeStart . ':' . $col . $rangeEnd);
            $style->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        }

        // Garante alinhamento + tamanho de fonte por célula (sobrescreve qualquer
        // estilo herdado do template, que vinha sobrescrevendo o range).
        for ($i = 0; $i < $rowsCount; $i++) {
            $row = $firstDataRow + $i;
            // Coluna A (numeração) à esquerda
            $sheet->getStyle('A' . $row)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setIndent(1);

            // Datas centralizadas
            foreach (['A', 'B', 'F', 'I'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            // Valores à direita com leve recuo à direita pra não colar na próxima coluna
            foreach ($colsRight as $col) {
                $sheet->getStyle($col . $row)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                    ->setIndent(1);
            }
            // Descrições à esquerda com recuo — sem wrap para não quebrar em 2 linhas
            foreach (['H', 'K'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                    ->setIndent(1)
                    ->setWrapText(false);
                // Fonte menor nas descrições para caber em uma linha
                $sheet->getStyle($col . $row)->getFont()->setSize(6.5);
            }
            // Fonte geral nas linhas de dados.
            $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setSize(7);
        }

        // Linha de totais: à direita nas colunas de valores e em negrito.
        foreach (['C', 'E', 'G', 'J'] as $col) {
            $sheet->getStyle($col . $totalsRow)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setIndent(1)
                ->setWrapText(false);  // Garante que valores não quebrem em 2 linhas
        }
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->getFont()->setSize(7)->setBold(true);
        // Garante wrap=false em toda linha de totais
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->getAlignment()->setWrapText(false);

        // ---- Separadores verticais entre os 3 blocos (E|F e H|I).
        // Aplica borda direita leve ao final de Entrada R$ e Saída U$.
        foreach (['E', 'H'] as $col) {
            $sheet->getStyle($col . $firstDataRow . ':' . $col . $totalsRow)
                ->getBorders()->getRight()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                ->getColor()->setARGB('FFBFBFBF');
        }

        // ---- Larguras de coluna ajustadas para A4 paisagem caber inteiro.
        // Total ~ 150 unidades — calibrado para A4 paisagem com margens 0.3.
        // Reduzidas para evitar quebra em múltiplas linhas (wrap=false truncará elegantemente).
        $larguras = [
            'A' => 2,    // N
            'B' => 15,   // Data (Entrada R$)
            'C' => 13,   // Valor R$ (reduzido de 13)
            'D' => 8,    // Taxa (reduzido de 9)
            'E' => 12,   // Valor U$ (reduzido de 12)
            'F' => 16,   // Data (Saída U$)
            'G' => 10,   // Valor U$ (reduzido de 12)
            'H' => 21,   // Descrição saída (reduzido de 26)
            'I' => 16,   // Data (Entrada U$)
            'J' => 11,   // Valor U$ (reduzido de 12)
            'K' => 27,   // Descrição entrada U$ (reduzido de 28)
        ];
        foreach ($larguras as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Datas nunca devem quebrar — garante "wrap=false" nas colunas de data.
        foreach (['B', 'F', 'I'] as $col) {
            $sheet->getStyle($col . $firstDataRow . ':' . $col . $totalsRow)
                ->getAlignment()->setWrapText(false);
        }

        // ---- Altura mais generosa nas linhas de cabeçalho/totais.
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension($totalsRow)->setRowHeight(22);

        // ---- Page setup: A4 paisagem, ajusta para caber em 1 página de largura.
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true);
        $sheet->getPageMargins()
            ->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.0);
        $sheet->setPrintGridlines(false);
        
        // Zoom out de ~15% para melhor alinhamento com valores altos
        // 85% = 0.85 escala (85 de 100)
        $sheet->getSheetView()->setZoomScale(85);

        return $spreadsheet;
    }

    public function exportClientCsv(\App\Models\Client $client, Request $request)
    {
        [$dateFrom, $dateTo] = $this->parseDateRange($request);

        $balances = $this->calculateDisplayedBalances($client);
        $brl = (float) ($balances['BRL'] ?? 0);
        $usd = (float) ($balances['USD'] ?? 0);

        $base = $client->transactions()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderBy('created_at');

        $all = $base->get();

        $entradasBrl = $all->filter(fn ($t) => $t->type === 'deposit' && $t->currency === 'BRL' && (float) $t->amount > 0)->values();
        $saidasUsd   = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount < 0)->values();
        $entradasUsd = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount > 0)->values();

        $rowsCount = max($entradasBrl->count(), $saidasUsd->count(), $entradasUsd->count());

        // Helpers de formatação local pt-BR.
        $br = fn ($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');
        $dt = fn ($t) => optional($t->created_at)->format('d/m/Y H:i');

        $filename = sprintf(
            'extrato_%s_%s.csv',
            \Illuminate\Support\Str::slug($client->name),
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use (
            $client, $brl, $usd, $dateFrom, $dateTo,
            $entradasBrl, $saidasUsd, $entradasUsd, $rowsCount, $br, $dt
        ) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 → Excel/Numbers reconhecem acentos e separador ;
            fwrite($out, "\xEF\xBB\xBF");

            $sep = ';';

            // Cabeçalho
            fputcsv($out, ["Extrato — {$client->name}"], $sep);
            $periodo = 'Período: ' . ($dateFrom ? $dateFrom->format('d/m/Y') : 'início') .
                       ' até ' . ($dateTo ? $dateTo->format('d/m/Y') : 'hoje');
            fputcsv($out, [$periodo], $sep);
            fputcsv($out, [
                'Saldo ' . $client->name,
                'R$ ' . $br($brl),
                'U$ ' . $br($usd),
            ], $sep);
            fputcsv($out, [], $sep);

            // Cabeçalhos das 3 colunas (lado a lado).
            fputcsv($out, [
                'Entradas R$', '', '', '',
                'Saídas U$', '', '',
                'Entradas U$', '', '', '',
            ], $sep);
            fputcsv($out, [
                'Data', 'Valor R$', 'Taxa', 'Valor U$',
                'Data', 'Valor U$', 'Descrição',
                'Data', 'Valor U$', 'Descrição', 'PnL U$',
            ], $sep);

            for ($i = 0; $i < $rowsCount; $i++) {
                $e  = $entradasBrl->get($i);
                $s  = $saidasUsd->get($i);
                $eu = $entradasUsd->get($i);

                $eValorUsd = null;
                if ($e) {
                    if ($e->converted_currency === 'USD' && $e->converted_amount !== null) {
                        $eValorUsd = (float) $e->converted_amount;
                    } elseif ((float) $e->exchange_rate > 0) {
                        $eValorUsd = (float) $e->amount / (float) $e->exchange_rate;
                    }
                }

                fputcsv($out, [
                    $e ? $dt($e) : '',
                    $e ? $br($e->amount) : '',
                    $e && $e->exchange_rate ? $br($e->exchange_rate, 4) : '',
                    $eValorUsd !== null ? $br($eValorUsd) : '',

                    $s ? $dt($s) : '',
                    $s ? $br(abs((float) $s->amount)) : '',
                    $s ? (string) ($s->description ?? '') : '',

                    $eu ? $dt($eu) : '',
                    $eu ? $br((float) $eu->amount) : '',
                    $eu ? (string) ($eu->description ?? '') : '',
                    $eu && $eu->realized_pnl_usd !== null
                        ? (((float) $eu->realized_pnl_usd >= 0 ? '+' : '') . $br($eu->realized_pnl_usd, 4))
                        : '',
                ], $sep);
            }

            // Totais.
            $totalEntradaBrl = (float) $entradasBrl->sum('amount');
            $totalSaidaUsd   = (float) $saidasUsd->sum(fn ($t) => abs((float) $t->amount));
            $totalEntradaUsd = (float) $entradasUsd->sum('amount');
            $totalPnlUsd     = (float) $entradasUsd->sum(fn ($t) => (float) ($t->realized_pnl_usd ?? 0));

            fputcsv($out, [], $sep);
            fputcsv($out, [
                'TOTAL', 'R$ ' . $br($totalEntradaBrl), '', '',
                'TOTAL', 'U$ ' . $br($totalSaidaUsd), '',
                'TOTAL', 'U$ ' . $br($totalEntradaUsd), '',
                ($totalPnlUsd >= 0 ? '+' : '') . 'U$ ' . $br($totalPnlUsd, 4),
            ], $sep);

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Lê os parâmetros de filtro de período (date_from / date_to) da request.
     *
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    protected function parseDateRange(Request $request): array
    {
        $from = $request->input('date_from');
        $to   = $request->input('date_to');

        try {
            $dateFrom = $from ? \Illuminate\Support\Carbon::parse($from)->startOfDay() : null;
        } catch (\Throwable $e) { $dateFrom = null; }
        try {
            $dateTo = $to ? \Illuminate\Support\Carbon::parse($to)->endOfDay() : null;
        } catch (\Throwable $e) { $dateTo = null; }

        return [$dateFrom, $dateTo];
    }

    /**
     * Pré-compra de dólar pelo dono usando o BRL do cliente.
     * Não altera o saldo da carteira: apenas registra o lote e reserva o R$ no(s) depósito(s).
     */
    public function prePurchaseDollar(Request $request)
    {
        $validated = $request->validate([
            'client_id'         => 'required|exists:clients,id',
            'amount'            => 'required|numeric|min:0.01',
            'exchange_rate'     => 'required|numeric|min:0.000001',
            'description'       => 'nullable|string|max:255',
            'transaction_ids'   => 'nullable|array',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        $anchorIds = !empty($validated['transaction_ids'])
            ? array_map('intval', $validated['transaction_ids'])
            : [];
        $label = 'Pré-compra R$ ' . number_format((float) $validated['amount'], 2, ',', '.') .
            ' @ ' . number_format((float) $validated['exchange_rate'], 4, ',', '.');

        return $this->reversalService->capture('pre_purchase', (int) $validated['client_id'], $anchorIds, $label, function () use ($validated) {
            try {
                $lotes = $this->walletService->prePurchaseDollar(
                    (int) $validated['client_id'],
                    (float) $validated['amount'],
                    (float) $validated['exchange_rate'],
                    $validated['description'] ?? null,
                    !empty($validated['transaction_ids'])
                        ? array_map('intval', $validated['transaction_ids'])
                        : null
                );

                // Finaliza depósitos-fonte cobertos por compra + venda.
                $depositIds = array_unique(array_map(fn ($l) => (int) $l->source_transaction_id, $lotes));
                foreach ($depositIds as $depId) {
                    $dep = \App\Models\Transaction::find($depId);
                    if ($dep) {
                        $this->walletService->finalizeDepositIfCovered($dep);
                    }
                }
            } catch (\Throwable $e) {
                return back()->with('error', $e->getMessage());
            }

            $totalUsd = array_sum(array_map(fn ($l) => (float) $l->usd_amount, $lotes));
            $totalBrl = array_sum(array_map(fn ($l) => (float) $l->brl_amount, $lotes));

            return back()->with('success',
                'Pré-compra registrada: R$ ' . number_format($totalBrl, 2, ',', '.') .
                ' → US$ ' . number_format($totalUsd, 2, ',', '.') .
                ' (taxa ' . number_format((float) $validated['exchange_rate'], 4, ',', '.') . ').'
            );
        });
    }

    /**
     * Pré-venda de dólar: o dono fixa a taxa que vai cobrar do cliente sobre R$ de
     * depósitos abertos. Não altera saldo do cliente nem cria transação de exchange.
     */
    public function preSellDollar(Request $request)
    {
        $validated = $request->validate([
            'client_id'         => 'required|exists:clients,id',
            'amount'            => 'required|numeric|min:0.01',
            'sell_rate'         => 'required|numeric|min:0.0001',
            'description'       => 'nullable|string|max:255',
            'transaction_ids'   => 'nullable|array',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        $anchorIds = !empty($validated['transaction_ids'])
            ? array_map('intval', $validated['transaction_ids'])
            : [];
        $label = 'Pré-venda R$ ' . number_format((float) $validated['amount'], 2, ',', '.') .
            ' @ ' . number_format((float) $validated['sell_rate'], 4, ',', '.');

        return $this->reversalService->capture('pre_sell', (int) $validated['client_id'], $anchorIds, $label, function () use ($validated) {
            try {
            return DB::transaction(function () use ($validated) {
                $clientId  = (int) $validated['client_id'];
                $sellRate  = round((float) $validated['sell_rate'], 4);
                $brlAmount = (float) $validated['amount'];
                $userDesc  = $validated['description'] ?? null;

                // 1) Cria lotes de pré-venda (reserva R$ nos depósitos selecionados).
                $lotes = $this->walletService->preSellDollar(
                    $clientId,
                    $brlAmount,
                    $sellRate,
                    $userDesc,
                    !empty($validated['transaction_ids'])
                        ? array_map('intval', $validated['transaction_ids'])
                        : null
                );

                $totalUsd = round(array_sum(array_map(fn ($l) => (float) $l->usd_amount, $lotes)), 4);
                $totalBrl = round(array_sum(array_map(fn ($l) => (float) $l->brl_amount, $lotes)), 2);

                // Atualiza a taxa "atual" do depósito com a taxa da PRÉ-VENDA
                // recém-registrada. Assim o campo de taxa na carteira reflete
                // exatamente a taxa vendida e pode ficar readonly.
                $depositIds = array_unique(array_map(fn ($l) => (int) $l->source_transaction_id, $lotes));
                foreach ($depositIds as $depId) {
                    $dep = \App\Models\Transaction::find($depId);
                    if (!$dep) {
                        continue;
                    }
                    #atualizar o converted_amount , com base na nova taxa de venda, para refletir o valor USD equivalente do depósito após a pré-venda
                    $dep->converted_amount = round((float) $dep->amount / $sellRate, 2);
                    $dep->exchange_rate = $sellRate;
                    $dep->save();
                }

                $descricao = $userDesc ?: 'Fechamento R$ ' . number_format($totalBrl, 2, ',', '.') .
                                          ' @ ' . number_format($sellRate, 4, ',', '.');

                // 2) Entrega USD do caixa do dono ao cliente (consome lotes — PnL do CAIXA
                // é registrado ali; PnL do CLIENTE/DEPÓSITO só será computado quando a
                // pré-compra fechar o depósito (finalizeDepositIfCovered).
                $consumo = $this->treasuryService->deliverFromCash(
                    $clientId, $totalUsd, $sellRate, $descricao
                );
                $shortfall = (float) ($consumo['shortfall_usd'] ?? 0);

                // 3) Cria a transação USD finalizada (Entrada U$ do cliente) com PnL ZERADO.
                // O PnL real vem depois, via finalizeDepositIfCovered, calculado como
                // (taxa de compra) − (taxa de venda) deste mesmo depósito.
                
                $usdTx = $this->transactionService->create([
                    'client_id'        => $clientId,
                    'type'             => 'deposit',
                    'currency'         => 'USD',
                    'amount'           => $totalUsd,
                    'exchange_rate'    => $sellRate,
                    'description'      => $descricao,
                    'realized_pnl_brl' => 0,
                    'realized_pnl_usd' => 0,
                    'status'           => 'finalizado',
                ]);

                // 3b) Linka todos os lotes de pré-venda à Transaction USD.
                // Permite que finalizeDepositIfCovered propague o PnL real pra esta transaction.
                if ($usdTx && isset($usdTx->id)) {
                    foreach ($lotes as $loteVenda) {
                        $loteVenda->transaction_id = $usdTx->id;
                        $loteVenda->save();
                    }
                }

                // 4) Credita USD no saldo do cliente.
                $this->walletService->updateBalance($clientId, 'USD', $totalUsd);

                // 5) Para cada depósito-fonte tocado, finaliza se compra+venda já cobrem tudo.
                foreach ($depositIds as $depId) {
                    $dep = \App\Models\Transaction::find($depId);
                    if ($dep) {
                        $this->walletService->finalizeDepositIfCovered($dep);
                    }
                }

                $msg = 'Venda registrada: R$ ' . number_format($totalBrl, 2, ',', '.') .
                       ' → US$ ' . number_format($totalUsd, 2, ',', '.') .
                       ' (taxa ' . number_format($sellRate, 4, ',', '.') . ').';

                $redirect = redirect()->back()->with('success', $msg);
                if ($shortfall > 0.0001) {
                    $redirect = $redirect->with('warning',
                        '⚠ Caixa USD ficou negativo em US$ ' . number_format($shortfall, 2, ',', '.') .
                        '. O dólar entregue ao cliente excedeu o saldo do caixa — registre um aporte ou pré-compra para cobrir o déficit.'
                    );
                }
                return $redirect;
            });
            } catch (\Throwable $e) {
                return back()->with('error', $e->getMessage());
            }
        });
    }

    public function rollbackDeposit(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->walletService->rollbackDeposit(
                (int) $transaction->id,
                (int) Auth::id(),
                $validated['reason'] ?? null,
                false
            );
            
            return back()->with('success',
                'Depósito #' . $result['deposit_id'] . ' removido com rollback completo. ' .
                'Novo saldo: R$ ' . number_format((float) ($result['wallet_brl_after'] ?? 0), 2, ',', '.') .
                ' | US$ ' . number_format((float) ($result['wallet_usd_after'] ?? 0), 2, ',', '.')
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverte (apaga) um saque USD e recompõe o saldo da carteira do cliente.
     */
    public function rollbackWithdraw(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($transaction, $validated) {
                $tx = Transaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!($tx->type === 'withdraw' && $tx->currency === 'USD')) {
                    throw new \RuntimeException('Apenas saques em USD podem ser revertidos por esta ação.');
                }

                $amountUsd = abs((float) $tx->amount);
                $clientId = (int) $tx->client_id;

                // Soft-delete do saque e recálculo do saldo para manter consistência com o ledger.
                $tx->delete();
                $this->walletService->recalculateUsdBalance($clientId, false);

                return back()->with('success',
                    'Saque removido com sucesso: US$ ' . number_format($amountUsd, 2, ',', '.') .
                    (!empty($validated['reason']) ? ' | Motivo: ' . $validated['reason'] : '')
                );
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverte uma operação registrada (depósito, pré-compra, pré-venda ou fechamento)
     * restaurando exatamente o estado anterior capturado no snapshot.
     */
    public function reverseSnapshot(Request $request, OperationSnapshot $snapshot)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $summary = $this->reversalService->reverse(
                $snapshot,
                (int) Auth::id(),
                $validated['reason'] ?? null
            );

            return back()->with('success',
                'Operação revertida: ' . ($snapshot->label ?: $snapshot->type) . '.'
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverte várias operações de uma vez (ex.: cancelar compra + venda do mesmo
     * depósito). As reversões são aplicadas da mais recente para a mais antiga.
     */
    public function reverseManySnapshots(Request $request)
    {
        $validated = $request->validate([
            'snapshot_ids'   => 'required|array|min:1',
            'snapshot_ids.*' => 'integer|exists:operation_snapshots,id',
            'reason'         => 'nullable|string|max:500',
        ]);

        $snapshots = OperationSnapshot::whereIn('id', $validated['snapshot_ids'])
            ->whereNull('reversed_at')
            ->get();

        if ($snapshots->isEmpty()) {
            return back()->with('error', 'Nenhuma operação reversível encontrada.');
        }

        try {
            $this->reversalService->reverseMany(
                $snapshots,
                (int) Auth::id(),
                $validated['reason'] ?? null
            );

            return back()->with('success',
                $snapshots->count() . ' operação(ões) revertida(s) com sucesso.'
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Depósito
     */
    public function deposit(DepositRequest $request)
    {
        $data = $request->validated();
        $type = $data['currency'] === 'BRL' ? 'deposit_brl' : 'deposit_usd';
        $label = 'Depósito ' . $data['currency'] . ' ' .
            number_format((float) $data['amount'], 2, ',', '.') .
            ' via ' . ($data['payment_method'] ?? '-');

        return $this->reversalService->capture($type, (int) $data['client_id'], [], $label, function () use ($request) {
            return DB::transaction(function () use ($request) {
                $data = $request->validated();
                $client = \App\Models\Client::findOrFail($data['client_id']);
            $exchangeRate = null;
            $convertedAmount = null;
            $description = null;

            if ($data['currency'] === 'BRL') {
                // O front (modal Depositar) envia o campo `fee` já com o spread aplicado
                // ("Base + spread do cliente"). Usar direto para não somar spread em dobro.
                $exchangeRate = (float) $data['fee'];
                $convertedAmount = round($data['amount'] / $exchangeRate, 2);
            }else{
                $description = 'Depósito em ' . $data['currency'] . ' via ' . $data['payment_method'];
            }


            $this->walletService->updateBalance($data['client_id'], $data['currency'], $data['amount']);
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'deposit',
                'currency' => $data['currency'],
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'exchange_rate' => $exchangeRate,
                'converted_currency' => $data['currency'] === 'BRL' ? 'USD' : null,
                'converted_amount' => $convertedAmount,
                'status' => $data['currency'] === 'BRL' ? 'ambos_abertos' : null,
                'description' => $description,
            ]);
            return response()->json(['message' => 'Depósito realizado com sucesso.']);
            });
        });
    }

    /**
     * Busca a cotação atual USD/BRL (via ExchangeRateService, cacheada por 15s).
     */
    public function fetchUsdBrlRate()
    {
        try {
            $result = $this->exchangeRateService->getUsdBrlRate();

            if ($result === null) {
                return response()->json(['success' => false, 'message' => 'Não foi possível obter a cotação.'], 502);
            }

            return response()->json([
                'success' => true,
                'rate' => $result['rate'],
                'source' => $result['source'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao consultar cotação.'], 500);
        }
    }

    /**
     * Atualiza a taxa de uma transação de entrada BRL e audita a alteração.
     */
    public function updateDepositRate(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:0.0001',
        ]);

        if (!($transaction->type === 'deposit' && $transaction->currency === 'BRL' && $transaction->amount > 0)) {
            return back()->with('error', 'A taxa só pode ser alterada para depósitos de entrada em BRL.');
        }

        if (in_array($transaction->status, ['fechado', 'finalizado'], true)) {
            return response()->json([
                'message' => 'A taxa não pode ser alterada em uma transação fechada/finalizada.',
            ], 422);
        }

        return DB::transaction(function () use ($validated, $transaction) {
            $oldRate = (float) ($transaction->exchange_rate ?? 0);
            $newRate = round((float) $validated['exchange_rate'], 4);

            $transaction->exchange_rate = $newRate;
            $transaction->converted_currency = 'USD';
            $transaction->converted_amount = round($transaction->amount / $newRate, 2);
            $transaction->save();

            TransactionRateChangeLog::create([
                'transaction_id' => $transaction->id,
                'client_id' => $transaction->client_id,
                'changed_by' => Auth::id(),
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
            ]);

            return back()->with('success', 'Taxa atualizada com sucesso.');
        });
    }

    /**
     * Atualiza a taxa de várias transações de entrada BRL e audita as alterações.
     */
    public function updateDepositRateBulk(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:transactions,id',
        ]);

        $newRate = round((float) $validated['exchange_rate'], 4);

        return DB::transaction(function () use ($validated, $newRate) {
            $transactions = Transaction::query()
                ->whereIn('id', $validated['transaction_ids'])
                ->where('client_id', $validated['client_id'])
                ->where('type', 'deposit')
                ->where('currency', 'BRL')
                ->where('amount', '>', 0)
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['fechado', 'finalizado']))
                ->get();

            if ($transactions->isEmpty()) {
                return back()->with('error', 'Nenhuma transação válida foi selecionada para atualização em lote.');
            }

            foreach ($transactions as $transaction) {
                $oldRate = (float) ($transaction->exchange_rate ?? 0);

                $transaction->exchange_rate = $newRate;
                $transaction->converted_currency = 'USD';
                $transaction->converted_amount = round($transaction->amount / $newRate, 2);
                $transaction->save();

                TransactionRateChangeLog::create([
                    'transaction_id' => $transaction->id,
                    'client_id' => $transaction->client_id,
                    'changed_by' => Auth::id(),
                    'old_rate' => $oldRate,
                    'new_rate' => $newRate,
                ]);
            }

            return back()->with('success', 'Taxa atualizada em lote para ' . $transactions->count() . ' registro(s).');
        });
    }

    /**
     * Saque
     */
    public function withdraw(WithdrawRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $method = $data['payment_method']; // efetivo | usdt
            $description = $data['description'] ?? ($method === 'usdt' ? 'USDT Enviado' : 'Efetivo Enviado');

            $wallet = $this->walletService->updateBalance($data['client_id'], 'USD', -$data['amount']);
            // Permitido sacar mais do que o cliente tem em carteira (saldo USD pode ficar negativo).
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'withdraw',
                'currency' => 'USD',
                'amount' => -$data['amount'],
                'payment_method' => $method,
                'description' => $description,
            ]);
            return response()->json(['message' => 'Saque realizado com sucesso.']);
        });
    }

    /**
     * Conversão de moedas
     */
    public function exchange(ExchangeRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            // Spread do cliente: cada ponto = R$ 0,01 sobre a taxa
            $client = \App\Models\Client::findOrFail($data['client_id']);
            $effectiveRate = $data['exchange_rate'] + ($client->spread_points * 0.01);

            // Saída da moeda de origem
            $walletOrigem = $this->walletService->updateBalance($data['client_id'], $data['from_currency'], -$data['amount']);
            if ($walletOrigem->balance < 0) {
                throw new \Exception('Saldo insuficiente na moeda de origem.');
            }
            // Valor convertido usando taxa efetiva (base + spread)
            $convertedAmount = $this->currencyService->convert($data['amount'], $effectiveRate);
            // Entrada na moeda destino
            $this->walletService->updateBalance($data['client_id'], $data['to_currency'], $convertedAmount);
            // Registrar transações
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'exchange_out',
                'currency' => $data['from_currency'],
                'amount' => -$data['amount'],
                'converted_currency' => $data['to_currency'],
                'converted_amount' => $convertedAmount,
                'exchange_rate' => $effectiveRate,
            ]);
            $this->transactionService->create([
                'client_id' => $data['client_id'],
                'type' => 'exchange_in',
                'currency' => $data['to_currency'],
                'amount' => $convertedAmount,
                'converted_currency' => $data['from_currency'],
                'converted_amount' => $data['amount'],
                'exchange_rate' => $effectiveRate,
            ]);
            return response()->json(['message' => 'Conversão realizada com sucesso.']);
        });
    }

    /**
     * Fechamento em dólar: consome BRL (FIFO – do registro mais antigo para o mais novo)
     * dentro do conjunto de transações selecionadas, podendo "quebrar" um registro
     * em duas partes (parte finalizada + parte que sobra) via soft-delete do original.
     *
     * Entrada esperada:
     *  - amount        => valor TOTAL em BRL que será convertido nesta operação
     *  - exchange_rate => taxa usada na conversão (será gravada em todos os registros finalizados)
     *  - transaction_ids => ids das transações BRL elegíveis (pool da seleção)
     */
    public function fechamentoDolar(Request $request)
    {
        $validated = $request->validate([
            'client_id'        => 'required|exists:clients,id',
            'exchange_rate'    => 'required|numeric|min:0.000001',
            'transaction_ids'  => 'required|string',
            'amount'           => 'required|numeric|min:0.01',
            'date'             => 'required|date',
            'description'      => 'required|string|max:255',
        ]);

        $ids = array_values(array_filter(array_map('intval', explode(',', $validated['transaction_ids']))));
        if (empty($ids)) {
            return back()->with('error', 'Selecione ao menos uma transação para fechar.');
        }

        $newRate   = (float) $validated['exchange_rate'];
        $remaining = round((float) $validated['amount'], 2);

        $label = 'Fechamento R$ ' . number_format($remaining, 2, ',', '.') .
            ' @ ' . number_format($newRate, 4, ',', '.');

        return $this->reversalService->capture('fechamento', (int) $validated['client_id'], $ids, $label, function () use ($validated, $ids, $newRate, $remaining) {
            return DB::transaction(function () use ($validated, $ids, $newRate, $remaining) {
            // Pool elegível: BRL, depósitos positivos, ainda abertos, do cliente, ordenados FIFO.
            $candidates = Transaction::query()
                ->whereIn('id', $ids)
                ->where('client_id', $validated['client_id'])
                ->where('type', 'deposit')
                ->where('currency', 'BRL')
                ->where('amount', '>', 0)
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['fechado', 'finalizado']))
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $totalDisponivel = round((float) $candidates->sum('amount'), 2);

            if ($remaining > $totalDisponivel + 0.005) {
                return back()->with('error',
                    'O valor solicitado (R$ ' . number_format($remaining, 2, ',', '.') .
                    ') excede o disponível nas transações selecionadas (R$ ' .
                    number_format($totalDisponivel, 2, ',', '.') . ').');
            }

            $totalBrlConsumido = 0.0;
            $entregas          = []; // [{usd, sell_rate}] — cada chunk de USD a entregar com sua taxa
            $totalUsdComprado  = 0.0; // USD efetivamente "comprado pelo dono" nesta operação (custo)
            $custoBrlCompra    = 0.0; // R$ efetivos do dono na compra (para preview)

            foreach ($candidates as $tx) {
                if ($remaining <= 0.005) {
                    break;
                }

                $valorTx = round((float) $tx->amount, 2);

                // Decide quanto desse depósito será consumido nesta operação.
                $consumido = ($valorTx <= $remaining + 0.005) ? $valorTx : round($remaining, 2);
                $isParcial = $consumido < $valorTx - 0.005;

                if ($isParcial) {
                    $brlPre = round((float) ($tx->brl_pre_purchased ?? 0), 2);
                    $brlSld = round((float) ($tx->brl_pre_sold ?? 0), 2);
                    if ($brlPre > $consumido + 0.005 || $brlSld > $consumido + 0.005) {
                        throw new \RuntimeException(
                            'O depósito BRL de R$ ' . number_format($valorTx, 2, ',', '.') .
                            ' tem pré-compra/venda de R$ ' . number_format(max($brlPre, $brlSld), 2, ',', '.') .
                            '. Para fechar parcialmente é preciso consumir ao menos toda a parte pré-comprada/vendida.'
                        );
                    }
                }

                // 1) Consume PRÉ-VENDAS deste depósito (gera USD a entregar pelas taxas dos lotes).
                $preSellLotes = \App\Models\WalletPreSell::query()
                    ->where('source_transaction_id', $tx->id)
                    ->whereIn('status', ['open', 'partial'])
                    ->orderBy('created_at')->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $brlVendido = 0.0;
                $restanteVenda = round(min($consumido, (float) ($tx->brl_pre_sold ?? 0)), 2);
                foreach ($preSellLotes as $lote) {
                    if ($restanteVenda <= 0.005) break;
                    $brlLote = (float) $lote->brl_remaining;
                    if ($brlLote <= 0.005) continue;

                    $consumirLote = round(min($brlLote, $restanteVenda), 2);
                    $rateLote     = (float) $lote->sell_rate;
                    $usdParte     = $rateLote > 0 ? round($consumirLote / $rateLote, 4) : 0.0;

                    $lote->brl_remaining = round((float) $lote->brl_remaining - $consumirLote, 2);
                    $lote->usd_remaining = round((float) $lote->usd_remaining - $usdParte, 4);
                    if ($lote->brl_remaining <= 0.005) {
                        $lote->brl_remaining = 0; $lote->usd_remaining = 0; $lote->status = 'closed';
                    } else { $lote->status = 'partial'; }
                    $lote->save();

                    $entregas[] = ['usd' => $usdParte, 'sell_rate' => $rateLote];
                    $brlVendido += $consumirLote;
                    $restanteVenda = round($restanteVenda - $consumirLote, 2);
                }
                $tx->brl_pre_sold = round(max(0.0, (float) ($tx->brl_pre_sold ?? 0) - $brlVendido), 2);

                // 2) Consume PRÉ-COMPRAS deste depósito (libera USD do caixa que JÁ FOI espelhado).
                $pre = $this->walletService->consumePrePurchasesOnClose($tx, $consumido, $newRate);
                $totalUsdComprado += (float) $pre['usd_consumido_lotes'];
                $custoBrlCompra   += (float) ($pre['brl_consumido_lotes'] ?? 0); // R$ que o dono já tinha "investido"

                // 3) R$ que sobrou SEM venda registrada → entrega na taxa do fechamento.
                $brlSemVenda = round($consumido - $brlVendido, 2);
                if ($brlSemVenda > 0.005) {
                    $usdResidual = round($brlSemVenda / $newRate, 4);
                    $entregas[] = ['usd' => $usdResidual, 'sell_rate' => $newRate];
                }

                // 4) R$ que sobrou SEM compra registrada → dono compra agora à newRate (lote 'close').
                $brlSemCompra = round($consumido - (float) ($pre['brl_consumido_lotes'] ?? 0), 2);
                if ($brlSemCompra > 0.005) {
                    $usdNovo = round($brlSemCompra / $newRate, 4);
                    $this->treasuryService->bookCloseLot(
                        (int) $validated['client_id'], $usdNovo, $newRate, $validated['description'] ?? null
                    );
                    $totalUsdComprado += $usdNovo;
                    $custoBrlCompra   += $brlSemCompra;
                }

                // 5) Marca/quebra o depósito como hoje.
                if (!$isParcial) {
                    $oldRate = (float) ($tx->exchange_rate ?? 0);
                    $tx->exchange_rate      = $newRate;
                    $tx->converted_currency = 'USD';
                    $tx->converted_amount   = round($valorTx / $newRate, 2);
                    $tx->status             = 'finalizado';
                    $tx->save();

                    if ($oldRate !== $newRate) {
                        TransactionRateChangeLog::create([
                            'transaction_id' => $tx->id, 'client_id' => $tx->client_id,
                            'changed_by' => Auth::id(), 'old_rate' => $oldRate, 'new_rate' => $newRate,
                        ]);
                    }
                    $totalBrlConsumido += $valorTx;
                    $remaining -= $valorTx;
                } else {
                    $sobra = round($valorTx - $consumido, 2);

                    $finalizada = new Transaction([
                        'parent_transaction_id' => $tx->id, 'client_id' => $tx->client_id,
                        'type' => $tx->type, 'currency' => $tx->currency,
                        'amount' => $consumido, 'payment_method' => $tx->payment_method,
                        'converted_currency' => 'USD', 'converted_amount' => round($consumido / $newRate, 2),
                        'exchange_rate' => $newRate, 'description' => $tx->description,
                        'status' => 'finalizado',
                    ]);
                    $finalizada->created_at = $tx->created_at;
                    $finalizada->updated_at = now();
                    $finalizada->save();

                    $restanteTx = new Transaction([
                        'parent_transaction_id' => $tx->id, 'client_id' => $tx->client_id,
                        'type' => $tx->type, 'currency' => $tx->currency,
                        'amount' => $sobra, 'payment_method' => $tx->payment_method,
                        'converted_currency' => $tx->converted_currency,
                        'converted_amount' => ($tx->exchange_rate && $tx->exchange_rate > 0)
                            ? round($sobra / (float) $tx->exchange_rate, 2) : null,
                        'exchange_rate' => $tx->exchange_rate, 'description' => $tx->description,
                        'status' => $tx->status,
                    ]);
                    $restanteTx->created_at = $tx->created_at;
                    $restanteTx->updated_at = now();
                    $restanteTx->save();

                    $tx->delete();

                    $totalBrlConsumido += $consumido;
                    $remaining = 0;
                }
            }

            // Total USD a entregar e taxa média ponderada.
            $totalUsdEntregue = 0.0; $brlReceitaTotal = 0.0;
            foreach ($entregas as $e) {
                $totalUsdEntregue += (float) $e['usd'];
                $brlReceitaTotal  += (float) $e['usd'] * (float) $e['sell_rate'];
            }
            $totalUsdEntregue = round($totalUsdEntregue, 2);
            $taxaMediaVenda   = $totalUsdEntregue > 0 ? round($brlReceitaTotal / $totalUsdEntregue, 6) : $newRate;

            // Consome USD do caixa por chunk (cada um na sua taxa) e acumula PnL.
            $totalPnlBrl = 0.0; $totalPnlUsd = 0.0; $totalShortfall = 0.0;
            foreach ($entregas as $e) {
                $u = round((float) $e['usd'], 4);
                if ($u <= 0.0001) continue;
                $consumo = $this->treasuryService->deliverFromCash(
                    (int) $validated['client_id'], $u, (float) $e['sell_rate'], $validated['description'] ?? null
                );
                $totalPnlBrl    += (float) $consumo['pnl_brl'];
                $totalPnlUsd    += (float) $consumo['pnl_usd'];
                $totalShortfall += (float) ($consumo['shortfall_usd'] ?? 0);
            }

            // Cria UMA transação USD FINALIZADA (a "Entrada U$" do cliente).
            // A descrição mostra apenas a taxa de venda (visão do cliente).
            $usdTx = new Transaction([
                'client_id'        => $validated['client_id'],
                'type'             => 'deposit',
                'currency'         => 'USD',
                'amount'           => $totalUsdEntregue,
                'exchange_rate'    => $taxaMediaVenda,
                'description'      => $validated['description'],
                // PnL do CLIENTE só é gravado quando o depósito BRL fecha via
                // finalizeDepositIfCovered (compra+venda do mesmo depósito). O PnL
                // do FIFO contra o caixa segue gravado em treasury_lots/treasury_sales.
                'realized_pnl_brl' => 0,
                'realized_pnl_usd' => 0,
                'status'           => 'finalizado',
            ]);
            $usdTx->created_at = $validated['date'];
            $usdTx->updated_at = now();
            $usdTx->save();

            // Atualiza saldos: debita BRL e credita USD do cliente.
            $this->walletService->updateBalance((int) $validated['client_id'], 'BRL', -$totalBrlConsumido);
            $this->walletService->updateBalance((int) $validated['client_id'], 'USD', $totalUsdEntregue);

            $msg = 'Fechamento em dólar: R$ ' . number_format($totalBrlConsumido, 2, ',', '.') .
                ' → US$ ' . number_format($totalUsdEntregue, 2, ',', '.') .
                ' (taxa média venda ' . number_format($taxaMediaVenda, 4, ',', '.') . ').';

            $redirect = redirect()->back()->with('success', $msg);

            if ($totalShortfall > 0.0001) {
                $redirect = $redirect->with('warning',
                    '⚠ Caixa USD ficou negativo em US$ ' . number_format($totalShortfall, 2, ',', '.') .
                    '. O dólar entregue ao cliente excedeu o saldo do caixa — registre um aporte ou pré-compra para cobrir o déficit.'
                );
            }

            return $redirect;
        });
        });
    }
}
