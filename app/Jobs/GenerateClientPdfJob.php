<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;

class GenerateClientPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public readonly int $clientId,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly string $exportKey,
    ) {}

    public function handle(): void
    {
        $cacheKey = 'pdf_export_' . $this->exportKey;

        try {
            $client = Client::findOrFail($this->clientId);
            $spreadsheet = $this->buildSpreadsheet($client);

            $exportDir = storage_path('app/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0775, true);
            }

            $filename = sprintf(
                'extrato_%s_%s.pdf',
                \Illuminate\Support\Str::slug($client->name),
                now()->format('Ymd_His')
            );

            $pdfPath      = $exportDir . DIRECTORY_SEPARATOR . $this->exportKey . '.pdf';
            $filenamePath = $exportDir . DIRECTORY_SEPARATOR . $this->exportKey . '.name';

            $tmpDir = storage_path('app/tmp');
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0775, true);
            }
            $xlsxPath = $tmpDir . DIRECTORY_SEPARATOR . $this->exportKey . '.xlsx';

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($xlsxPath);

            $soffice   = $this->locateSoffice();
            $converted = false;

            if ($soffice) {
                $cmd = sprintf(
                    '%s --headless --norestore --nofirststartwizard --convert-to pdf:"calc_pdf_Export" --outdir %s %s 2>&1',
                    escapeshellarg($soffice),
                    escapeshellarg($tmpDir),
                    escapeshellarg($xlsxPath)
                );
                exec($cmd, $output, $code);
                $generatedPdf = preg_replace('/\.xlsx$/', '.pdf', $xlsxPath);

                if ($code === 0 && is_file($generatedPdf)) {
                    rename($generatedPdf, $pdfPath);
                    $converted = true;
                }
            }

            @unlink($xlsxPath);

            if (!$converted) {
                IOFactory::registerWriter('Pdf', Mpdf::class);
                $pdfWriter = IOFactory::createWriter($spreadsheet, 'Pdf');
                $pdfWriter->save($pdfPath);
            }

            file_put_contents($filenamePath, $filename);

            Cache::put($cacheKey, ['status' => 'done'], now()->addHour());

        } catch (\Throwable $e) {
            Log::error('GenerateClientPdfJob failed', [
                'key'   => $this->exportKey,
                'error' => $e->getMessage(),
            ]);
            Cache::put($cacheKey, ['status' => 'failed', 'error' => $e->getMessage()], now()->addHour());
        }
    }

    private function buildSpreadsheet(Client $client): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $dateFrom = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : null;
        $dateTo   = $this->dateTo   ? Carbon::parse($this->dateTo)->endOfDay()     : null;

        // Saldos
        $brlTransactions = Transaction::query()
            ->where('client_id', $client->id)
            ->where('currency', 'BRL')
            ->get();

        $brl = 0.0;
        foreach ($brlTransactions as $t) {
            $amount = (float) $t->amount;
            if ($t->type === 'deposit') {
                if (in_array($t->status, ['fechado', 'finalizado'], true)) {
                    continue;
                }
                $brl += abs($amount);
                continue;
            }
            if ($t->type === 'withdraw' || $t->type === 'exchange_out') {
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
            ->whereNotIn('status', ['fechado', 'finalizado'])
            ->sum('brl_pre_sold');

        $balances = ['BRL' => round($brl - $brlPreSoldOpen, 2), 'USD' => $usd];

        $all = $client->transactions()
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('created_at', '<=', $dateTo))
            ->orderBy('created_at', 'desc')
            ->get();

        $entradasBrl = $all->filter(fn ($t) => $t->type === 'deposit' && $t->currency === 'BRL' && (float) $t->amount > 0)->values();
        $saidasUsd   = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount < 0)->values();
        $entradasUsd = $all->filter(fn ($t) => $t->currency === 'USD' && (float) $t->amount > 0)->values();

        $rowsCount = max(1, $entradasBrl->count(), $saidasUsd->count(), $entradasUsd->count());

        $br = fn ($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');
        $dt = fn ($t) => optional($t->created_at)->format('d/m/Y H:i');

        $templatePath = storage_path('app/templates/extrato_template.xlsx');
        if (!is_file($templatePath)) {
            throw new \RuntimeException('Template extrato_template.xlsx não encontrado em storage/app/templates.');
        }

        $reader      = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($templatePath);
        $sheet       = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('F1', 'Saldo ' . $client->name . ' R$ ' . $br($balances['BRL']));
        $sheet->setCellValue('I1', 'U$ ' . $br($balances['USD']));

        $firstDataRow     = 4;
        $templateDataRows = 6;
        $placeholderRow   = 10;
        $sheet->removeRow($placeholderRow, 1);

        if ($rowsCount > $templateDataRows) {
            $extra = $rowsCount - $templateDataRows;
            $sheet->insertNewRowBefore($firstDataRow + $templateDataRows, $extra);
        } elseif ($rowsCount < $templateDataRows) {
            $remove = $templateDataRows - $rowsCount;
            $sheet->removeRow($firstDataRow + $rowsCount, $remove);
        }

        $totalsRow = $firstDataRow + $rowsCount;

        for ($i = 0; $i < $rowsCount; $i++) {
            $row = $firstDataRow + $i;
            $e   = $entradasBrl->get($i);
            $s   = $saidasUsd->get($i);
            $eu  = $entradasUsd->get($i);

            $sheet->setCellValue('A' . $row, $i + 1);

            if ($e) {
                $valorUsd = null;
                if ($e->converted_currency === 'USD' && $e->converted_amount !== null) {
                    $valorUsd = (float) $e->converted_amount;
                } elseif ((float) $e->exchange_rate > 0) {
                    $valorUsd = (float) $e->amount / (float) $e->exchange_rate;
                }
                $sheet->setCellValueExplicit('B' . $row, $dt($e), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C' . $row, $br((float) $e->amount, 2), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D' . $row, $e->exchange_rate ? $br((float) $e->exchange_rate, 4) : '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E' . $row, $valorUsd !== null ? $br($valorUsd, 2) : '', DataType::TYPE_STRING);
            } else {
                foreach (['B', 'C', 'D', 'E'] as $col) {
                    $sheet->setCellValue($col . $row, null);
                }
            }

            if ($s) {
                $sheet->setCellValueExplicit('F' . $row, $dt($s), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('G' . $row, $br(abs((float) $s->amount), 2), DataType::TYPE_STRING);
                $sheet->setCellValue('H' . $row, (string) ($s->description ?? ''));
            }

            if ($eu) {
                $sheet->setCellValueExplicit('I' . $row, $dt($eu), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J' . $row, $br((float) $eu->amount, 2), DataType::TYPE_STRING);
                $sheet->setCellValue('K' . $row, (string) ($eu->description ?? ''));
            }
        }

        // Totais
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

        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)
            ->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_THIN);

        $cinza     = 'FFF2F2F2';
        $branco    = 'FFFFFFFF';
        $bordaLeve = 'FFD9D9D9';
        $colsRight = ['C', 'D', 'E', 'G', 'J'];

        for ($i = 0; $i < $rowsCount; $i++) {
            $row = $firstDataRow + $i;
            $cor = ($i % 2 === 0) ? $cinza : $branco;

            $rowStyle = $sheet->getStyle('A' . $row . ':K' . $row);
            $rowStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($cor);
            $rowStyle->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB($bordaLeve);
            $sheet->getRowDimension($row)->setRowHeight(22);

            $sheet->getStyle('A' . $row)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setIndent(1);

            foreach (['A', 'B', 'F', 'I'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }
            foreach ($colsRight as $col) {
                $sheet->getStyle($col . $row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setIndent(1);
            }
            foreach (['H', 'K'] as $col) {
                $sheet->getStyle($col . $row)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setIndent(1)
                    ->setWrapText(false);
                $sheet->getStyle($col . $row)->getFont()->setSize(6.5);
            }
            $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setSize(7);
        }

        foreach (['C', 'E', 'G', 'J'] as $col) {
            $sheet->getStyle($col . $totalsRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setIndent(1)
                ->setWrapText(false);
        }
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->getFont()->setSize(7)->setBold(true);
        $sheet->getStyle('A' . $totalsRow . ':K' . $totalsRow)->getAlignment()->setWrapText(false);

        foreach (['E', 'H'] as $col) {
            $sheet->getStyle($col . $firstDataRow . ':' . $col . $totalsRow)
                ->getBorders()->getRight()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFBFBFBF');
        }

        $larguras = [
            'A' => 2,  'B' => 15, 'C' => 13, 'D' => 8,  'E' => 12,
            'F' => 16, 'G' => 10, 'H' => 21, 'I' => 16, 'J' => 11, 'K' => 27,
        ];
        foreach ($larguras as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        foreach (['B', 'F', 'I'] as $col) {
            $sheet->getStyle($col . $firstDataRow . ':' . $col . $totalsRow)
                ->getAlignment()->setWrapText(false);
        }

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getRowDimension($totalsRow)->setRowHeight(22);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setHorizontalCentered(true);
        $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.3)->setRight(0.0);
        $sheet->setPrintGridlines(false);
        $sheet->getSheetView()->setZoomScale(85);

        return $spreadsheet;
    }

    private function locateSoffice(): ?string
    {
        $env = env('LIBREOFFICE_PATH');
        if ($env && is_executable($env)) {
            return $env;
        }

        $candidates = [
            '/Applications/LibreOffice.app/Contents/MacOS/soffice',
            '/usr/bin/soffice',
            '/usr/local/bin/soffice',
            '/opt/homebrew/bin/soffice',
            '/usr/bin/libreoffice',
        ];
        foreach ($candidates as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }

        $which = @shell_exec('command -v soffice 2>/dev/null');
        if ($which) {
            $which = trim($which);
            if ($which && is_executable($which)) {
                return $which;
            }
        }

        return null;
    }
}
