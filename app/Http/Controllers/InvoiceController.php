<?php

namespace App\Http\Controllers;

use App\Models\InvoiceFile;
use App\Models\InvoiceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Módulo de Invoice — porte do painel legado (painel.php?view=invoice e a
 * pasta g_invoice/).
 *
 * A tela é um editor WYSIWYG: o próprio HTML dos modelos é editável
 * (contenteditable) e, ao salvar, o navegador manda o HTML pronto pra cá, que
 * é renderizado em PDF pelo mPDF — mesma mecânica do `g_invoice/pdf.php`, que
 * usava Dompdf. Cada PDF gerado (ou enviado por upload) fica registrado em
 * `invoice_files` e aparece no histórico de Saída/Entrada.
 *
 * Os textos de Bill To / Ship To de cada modelo são persistidos em
 * `invoice_texts` pra que a próxima invoice já abra preenchida.
 */
class InvoiceController extends Controller
{
    /** Pasta (disco local) onde os PDFs ficam guardados. */
    private const DIR_PDF = 'invoices';

    /** Modelos que têm textos persistidos, com a quantidade de campos de cada. */
    private const CAMPOS_POR_MODELO = [
        InvoiceText::MODELO_CLASSICO => 6,
        InvoiceText::MODELO_PCS => 14,
        InvoiceText::MODELO_SCAL => 10,
    ];

    /**
     * Tela do editor de invoice.
     */
    public function index()
    {
        return view('admin.invoice.index', [
            'textos1' => InvoiceText::doModelo(InvoiceText::MODELO_CLASSICO)->textos(6),
            'textos3' => InvoiceText::doModelo(InvoiceText::MODELO_PCS)->textos(14),
            'textos5' => InvoiceText::doModelo(InvoiceText::MODELO_SCAL)->textos(10),
            'invoiceCod' => $this->proximoCodigo(),
            // O legado imprime a data no formato americano (m/d/Y).
            'dataAtual' => now()->format('m/d/Y'),
        ]);
    }

    /**
     * Grava os textos editáveis de um modelo (Bill To / Ship To).
     *
     * Substitui `g_invoice/invoice_texto.php` (modelo 1) e
     * `g_invoice/invoice_data.php?modelo=3|5`.
     */
    public function saveTexts(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'modelo' => ['required', 'integer', 'in:'.implode(',', array_keys(self::CAMPOS_POR_MODELO))],
            'txt' => ['array'],
            'txt.*' => ['nullable', 'string', 'max:255'],
        ]);

        $modelo = (int) $dados['modelo'];
        $enviados = $dados['txt'] ?? [];

        $valores = [];

        for ($i = 1; $i <= self::CAMPOS_POR_MODELO[$modelo]; $i++) {
            // O editor manda os campos indexados de 1 a N, na ordem da tela.
            $valores['txt'.$i] = (string) ($enviados[$i] ?? $enviados[(string) $i] ?? '');
        }

        $texto = InvoiceText::doModelo($modelo);
        $texto->fill($valores)->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Gera o PDF a partir do HTML do modelo escolhido na tela.
     *
     * Devolve só o nome do arquivo (text/plain), como o legado fazia: a tela
     * abre a aba do PDF antes da requisição terminar e depois aponta ela pro
     * arquivo gerado.
     */
    public function pdf(Request $request): Response
    {
        $dados = $request->validate([
            'nome' => ['nullable', 'string', 'max:255'],
            'conteudo' => ['required', 'string'],
        ]);

        $conteudo = $this->preparaConteudo($dados['conteudo']);

        $css = $this->cssDoPdf();

        $html = '<!DOCTYPE html>'
            .'<html lang="pt-BR"><head><meta charset="UTF-8"><title>Invoice</title>'
            .'<style>'.$css.'</style></head><body>'.$conteudo.'</body></html>';

        $temp = storage_path('app/mpdf');
        File::ensureDirectoryExists($temp);

        /*
         * O modelo SCal Mobile (5) é desenhado no formato Letter do PDF de
         * referência; os outros seguem em A4 — mesma regra do legado.
         */
        $ehScal = Str::contains($conteudo, ['SCal Mobile', 'invoice5-items', 'scal-page-one']);

        /*
         * O modelo SCal desenha a folha inteira (cabeçalho azul sangrando até
         * a borda, altura fixa de 1056px = Letter), então vai com margem zero,
         * como o `@page { margin: 0 }` que ele traz. Os outros modelos usam a
         * margem de 22px do pdf.css legado — 22/96" ≈ 5.8mm.
         */
        $margem = $ehScal ? 0 : 5.8;

        $pdf = new \Mpdf\Mpdf([
            'format' => $ehScal ? 'Letter' : 'A4',
            'margin_left' => $margem,
            'margin_right' => $margem,
            'margin_top' => $margem,
            'margin_bottom' => $margem,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $temp,
            'curlTimeout' => 5,
        ]);

        $pdf->SetTitle('Invoice');
        $pdf->SetDefaultBodyCSS('background-color', '#ffffff');
        $pdf->WriteHTML($html);

        $saida = $pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

        if (strlen($saida) < 100) {
            return response('O mPDF retornou um PDF vazio.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $arquivo = md5(uniqid('invoice_', true)).'.pdf';
        Storage::disk('local')->put(self::DIR_PDF.'/'.$arquivo, $saida);

        InvoiceFile::create([
            'nome' => trim((string) ($dados['nome'] ?? '')),
            'imais' => '',
            'travado' => false,
            'tipo' => 'saida',
            'file_arquivo' => $arquivo,
        ]);

        return response($arquivo, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Recebe um PDF pronto (histórico de Entrada).
     *
     * Substitui `g_invoice/carregar_pdf.php`.
     */
    public function upload(Request $request): Response
    {
        $dados = $request->validate([
            'nome' => ['nullable', 'string', 'max:255'],
            'file_arquivo' => ['required', 'file', 'mimetypes:application/pdf', 'max:51200'],
        ]);

        $arquivo = uniqid('invoice_').'.pdf';

        Storage::disk('local')->putFileAs(
            self::DIR_PDF,
            $request->file('file_arquivo'),
            $arquivo
        );

        InvoiceFile::create([
            'nome' => trim((string) ($dados['nome'] ?? '')),
            'imais' => '',
            'travado' => false,
            'tipo' => 'entrada',
            'file_arquivo' => $arquivo,
        ]);

        return response($arquivo, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Entrega o PDF pro navegador (os arquivos ficam fora do public/).
     */
    public function file(string $arquivo): StreamedResponse
    {
        // Só o nome do arquivo: fecha a porta pra ../ no caminho.
        $arquivo = basename($arquivo);

        abort_unless(
            InvoiceFile::where('file_arquivo', $arquivo)->exists(),
            404
        );

        $caminho = self::DIR_PDF.'/'.$arquivo;

        abort_unless(Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho, $arquivo, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$arquivo.'"',
        ]);
    }

    /**
     * Histórico de invoices (modal da tela), em HTML.
     *
     * Substitui `g_invoice/relatorio_invoice.php`.
     */
    public function history(Request $request)
    {
        $dados = $request->validate([
            'tipo' => ['required', 'string', 'in:entrada,saida'],
        ]);

        $arquivos = InvoiceFile::where('tipo', $dados['tipo'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('admin.invoice.partials.history', compact('arquivos'));
    }

    /**
     * Apaga o registro e o PDF correspondente.
     */
    public function destroy(InvoiceFile $invoiceFile): JsonResponse
    {
        Storage::disk('local')->delete(self::DIR_PDF.'/'.$invoiceFile->file_arquivo);

        $invoiceFile->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * IMEIs de uma invoice, pra montar a lista de bipagem.
     *
     * Substitui `g_invoice/imais.php?rec=imais`.
     */
    public function imais(InvoiceFile $invoiceFile): JsonResponse
    {
        return response()->json([
            'imais' => $invoiceFile->imaisArray(),
            'travado' => $invoiceFile->travado,
        ]);
    }

    /**
     * Grava a lista de IMEIs bipados.
     *
     * Substitui `g_invoice/imais.php` (POST id + imais).
     */
    public function saveImais(Request $request, InvoiceFile $invoiceFile): JsonResponse
    {
        $dados = $request->validate([
            'imais' => ['nullable', 'string'],
        ]);

        // A trava existe justamente pra impedir alteração da lista bipada.
        if ($invoiceFile->travado) {
            return response()->json([
                'ok' => false,
                'message' => 'Invoice travada.',
            ], 422);
        }

        $invoiceFile->update(['imais' => (string) ($dados['imais'] ?? '')]);

        return response()->json(['ok' => true]);
    }

    /**
     * Liga/desliga a trava da invoice.
     *
     * Substitui `g_invoice/imais.php?travar=true`.
     */
    public function toggleLock(Request $request, InvoiceFile $invoiceFile): JsonResponse
    {
        $dados = $request->validate([
            'travado' => ['required', 'boolean'],
        ]);

        $invoiceFile->update(['travado' => (bool) $dados['travado']]);

        return response()->json([
            'ok' => true,
            'travado' => $invoiceFile->travado,
        ]);
    }

    /**
     * Todos os IMEIs já bipados, usado pela busca "em qual invoice está?".
     *
     * Substitui `g_invoice/imais-info.php`.
     */
    public function imaisInfo(): JsonResponse
    {
        $registros = InvoiceFile::query()
            ->where('imais', '<>', '')
            ->orderByDesc('id')
            ->get(['id', 'imais'])
            ->map(fn (InvoiceFile $arquivo) => [
                'id' => $arquivo->id,
                'codigo' => $arquivo->codigoFormatado(),
                'nome' => $arquivo->nome,
                'imais' => (string) $arquivo->imais,
            ]);

        return response()->json($registros);
    }

    /**
     * Exporta os IMEIs de uma invoice em XLSX.
     *
     * Substitui `g_invoice/imais-download.php` (que usava SimpleXLSXGen).
     */
    public function exportImais(InvoiceFile $invoiceFile): StreamedResponse
    {
        $planilha = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $aba = $planilha->getActiveSheet();

        $aba->setCellValue('A1', 'IMAIS');
        $aba->getColumnDimension('A')->setWidth(40);
        $aba->getStyle('A1')->getFont()->setBold(true);

        $linha = 2;

        foreach ($invoiceFile->imaisArray() as $imai) {
            // Texto explícito: IMEI tem 15 dígitos e viraria notação
            // científica se o Excel tratasse como número.
            $aba->setCellValueExplicit(
                'A'.$linha,
                $imai,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $linha++;
        }

        $nome = 'imais-'.$invoiceFile->codigoFormatado().'.xlsx';

        return response()->streamDownload(function () use ($planilha) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planilha);
            $writer->save('php://output');
        }, $nome, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Número da próxima invoice: a mesma conta do legado — 6000 + (último id
     * gerado × 12). Não é sequencial de propósito.
     */
    private function proximoCodigo(): int
    {
        $ultimoId = (int) (InvoiceFile::max('id') ?? 0);

        return 6000 + ($ultimoId * 12);
    }

    /**
     * Ajusta o HTML vindo do editor antes de mandar pro mPDF.
     */
    private function preparaConteudo(string $conteudo): string
    {
        // Remove <img> com src vazio, que faria o renderizador reclamar.
        $conteudo = (string) preg_replace(
            '/<img\b[^>]*\bsrc\s*=\s*(["\'])\s*\1[^>]*>/i',
            '',
            $conteudo
        );

        /*
         * O modelo SCal traz um `@page { size: letter; margin: 0 }` inline,
         * que existia por causa do Dompdf. O mPDF não lida bem com @page no
         * meio do corpo — ele estoura o fluxo e a invoice de 2 páginas sai com
         * 13, quase todas em branco. O formato e as margens já são definidos
         * em código aqui, então a regra é removida (o resto do <style>,
         * como o box-sizing, continua valendo).
         */
        $conteudo = (string) preg_replace('/@page\s*\{[^}]*\}/i', '', $conteudo);

        /*
         * As imagens dos modelos (logos) são servidas pelo próprio app. Trocar
         * a URL pelo caminho em disco evita uma volta HTTP no meio da geração
         * — e funciona mesmo quando o PDF é gerado por fila/CLI.
         */
        $base = asset('assets/images/invoice/');

        return str_replace(
            [$base.'/', rtrim($base, '/').'/'],
            rtrim(public_path('assets/images/invoice'), '/').'/',
            $conteudo
        );
    }

    /**
     * CSS base do PDF (o mesmo `css/pdf.css` do painel legado).
     */
    private function cssDoPdf(): string
    {
        $caminho = public_path('assets/css/invoice-pdf.css');

        return is_file($caminho) ? (string) file_get_contents($caminho) : '';
    }
}
