<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser as PdfParser;

class AiController extends Controller
{
    public function index()
    {
        return view('admin.ai.index');
    }

    public function analyzeExtract(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:15360',
        ], [
            'file.required' => 'Selecione um arquivo para análise.',
            'file.mimes' => 'Formato inválido. Aceito: JPG, PNG, WEBP ou PDF.',
            'file.max' => 'Arquivo muito grande. Máximo 15MB.',
        ]);

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $isPdf = str_contains($mime, 'pdf');

        $apiKey = config('services.openai.key');
        $model = config('services.openai.model');
        $maxTokens = (int) config('services.openai.max_tokens');
        $temperature = (float) config('services.openai.temperature');

        $systemPrompt = implode(' ', [
            'Você é um assistente financeiro especializado em extratos de PIX e transações bancárias brasileiras.',
            'Analise o extrato fornecido e retorne um relatório estruturado contendo:',
            '1) Resumo geral do período',
            '2) Total de entradas (créditos)',
            '3) Total de saídas (débitos)',
            '4) Saldo líquido do período',
            '5) Lista das principais transações',
            '6) Período coberto pelo extrato',
            '7) Observações ou alertas importantes',
            'Responda sempre em português brasileiro, de forma clara, organizada e com valores em Reais (R$).',
        ]);

        try {
            if ($isPdf) {
                $messages = $this->buildPdfMessages($file->getRealPath(), $systemPrompt);
            } else {
                $messages = $this->buildImageMessages($file->getRealPath(), $mime, $systemPrompt);
            }

            $response = Http::withToken($apiKey)
                ->timeout(90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'messages' => $messages,
                ]);

            if ($response->failed()) {
                $errorMsg = $response->json('error.message', 'Erro desconhecido na API OpenAI.');
                return back()
                    ->withInput()
                    ->with('error', $errorMsg);
            }

            $result = $response->json('choices.0.message.content');
            $usage = $response->json('usage');

            return back()->with([
                'result' => $result,
                'filename' => $file->getClientOriginalName(),
                'usage' => $usage,
            ]);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', 'Erro ao processar o arquivo: ' . $e->getMessage());
        }
    }

    private function buildImageMessages(string $path, string $mime, string $systemPrompt): array
    {
        $base64 = base64_encode(file_get_contents($path));

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Analise este extrato PIX e gere o relatório:'],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => "data:{$mime};base64,{$base64}", 'detail' => 'high'],
                    ],
                ],
            ],
        ];
    }

    private function buildPdfMessages(string $path, string $systemPrompt): array
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        $text = trim($pdf->getText());

        if (empty($text)) {
            throw new \RuntimeException('Não foi possível extrair texto do PDF. Tente enviar uma imagem do extrato.');
        }

        // Limita o texto para não estourar tokens
        if (mb_strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000) . "\n\n[... conteúdo truncado ...]";
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Analise este extrato PIX e gere o relatório:\n\n{$text}"],
        ];
    }
}
