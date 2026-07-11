<?php

namespace App\Http\Controllers;

use App\Models\WhatsappGroup;
use App\Models\WhatsappPixExtraction;
use App\Services\ClientBankService;
use App\Services\WhatsappNodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        protected ClientBankService $clientBankService,
    ) {
        // Respostas nos grupos saem sempre pela instância de grupos
        $this->whatsappNodeService = new WhatsappNodeService('whatsapp_node_grupos');
    }

    protected WhatsappNodeService $whatsappNodeService;

    public function handle(Request $request): JsonResponse
    {
        if ($request->header('X-Webhook-Token') !== config('services.whatsapp.webhook_token')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $event = $request->input('event');
        $payload = $request->input('payload', []);

        // Log temporário para inspecionar payload completo (sem o base64 da mídia)
        $debugPayload = $payload;
        if (isset($debugPayload['media']['data_base64'])) {
            $debugPayload['media']['data_base64'] = '[omitido]';
        }
        Log::info('WhatsappWebhook: payload recebido', ['event' => $event, 'payload' => $debugPayload]);

        if ($event !== 'group.message') {
            return response()->json(['ok' => true]);
        }

        $chatId = $payload['chat_id'] ?? null;
        $media  = $payload['media'] ?? null;

        if (!$chatId || empty($media['data_base64'])) {
            return response()->json(['ok' => true]);
        }

        $group = WhatsappGroup::where('chat_id', $chatId)->where('ai_active', true)->first();
        if (!$group) {
            return response()->json(['ok' => true]);
        }

        try {
            $mimetype  = $media['mimetype'] ?? 'application/octet-stream';
            $base64    = $media['data_base64'];
            $isPdf     = str_contains($mimetype, 'pdf');

            // Decodifica e calcula hash antes de salvar
            $binary    = base64_decode($base64);
            $imageHash = hash('sha256', $binary);

            // Salva no Digital Ocean Spaces com prefixo de ambiente
            $env       = env('APP_ENV', 'local');
            $ext       = $isPdf ? 'pdf' : $this->extFromMime($mimetype);
            $filename  = now()->format('Ymd_His') . '_' . substr($imageHash, 0, 8) . '.' . $ext;
            $path      = "eshop-{$env}/whatsapp-pix/" . now()->format('Y/m/d') . '/' . $filename;
            Storage::disk('do_spaces')->put($path, $binary, 'public');

            // Chama a IA
            $messages = $isPdf
                ? $this->buildPdfMessages($base64)
                : $this->buildImageMessages($base64, $mimetype);

            $aiResponse = Http::withToken(config('services.openai.key'))
                ->timeout(90)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => config('services.openai.model'),
                    'max_tokens'  => 800,
                    'temperature' => 0,
                    'messages'    => $messages,
                ]);

            $aiRaw  = null;
            $aiData = null;

            if ($aiResponse->successful()) {
                $aiRaw  = $aiResponse->json('choices.0.message.content', '');
                $aiData = $this->parseJson($aiRaw);
            } else {
                Log::warning('WhatsappWebhook: OpenAI falhou', [
                    'chat_id' => $chatId,
                    'status'  => $aiResponse->status(),
                ]);
            }

            $numeroTransacao = $aiData['numero_transacao'] ?? null;
            $pixNome         = $aiData['nome_pagador'] ?? null;
            $pixValor        = $aiData['valor'] ?? null;
            $pixData         = $aiData['data_hora'] ?? null;

            // 1. Checa duplicata no banco por hash/número/campos (não requer API externa)
            $isDuplicate = $this->isDuplicate($imageHash, $numeroTransacao, $pixNome, $pixValor, $pixData);

            // 2. Se não é duplicata, verifica na API externa
            $bankTransactionId = null;
            $bankError         = false;

            if (!$isDuplicate && $aiData && $pixNome && $pixValor && $pixData) {
                try {
                    $bankTransactionId = $this->clientBankService->verifyPix($pixNome, $pixValor, $pixData);
                } catch (\Throwable $e) {
                    $bankError = true;
                    Log::warning('ClientBank: erro na verificação, marcando como não verificado', [
                        'error' => $e->getMessage(),
                    ]);
                }

                // 3. Se encontrou na API, checa se esse bank_transaction_id já foi confirmado antes
                //    (mesmo PIX enviado em formato diferente: JPG depois PDF, etc.)
                if ($bankTransactionId && WhatsappPixExtraction::where('bank_transaction_id', $bankTransactionId)
                        ->where('status', 'confirmed')
                        ->exists()
                ) {
                    $isDuplicate = true;
                }
            }

            // Status final
            $status = match (true) {
                $isDuplicate               => 'duplicate',
                $bankError                 => 'unverified',
                $bankTransactionId !== null => 'confirmed',
                default                    => 'false_payment',
            };

            // Persiste no banco
            WhatsappPixExtraction::create([
                'whatsapp_group_id'  => $group->id,
                'message_id'         => $payload['message_id'] ?? null,
                'from'               => $payload['name'] ?? $payload['short_name'] ?? $payload['push_name'] ?? $payload['from'] ?? null,
                'image_path'         => $path,
                'image_hash'         => $imageHash,
                'mimetype'           => $mimetype,
                'numero_transacao'   => $numeroTransacao,
                'pix_nome'           => $pixNome,
                'pix_valor'          => $pixValor,
                'pix_data'           => $pixData,
                'status'             => $status,
                'bank_transaction_id' => $bankTransactionId,
                'ai_data'            => $aiData,
                'ai_raw'             => $aiRaw,
            ]);

            // Responde no grupo
            if ($aiData) {
                $reply = match ($status) {
                    'confirmed'     => $this->formatConfirmed($aiData),
                    'duplicate'     => $this->formatDuplicate($aiData),
                    'false_payment' => $this->formatFalse($aiData),
                    default         => null,
                };

                if ($reply) {
                    $this->whatsappNodeService->sendText($chatId, $reply);
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsappWebhook: erro ao processar mídia', [
                'chat_id' => $chatId,
                'error'   => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'Você é um assistente financeiro especializado em comprovantes de PIX e extratos bancários brasileiros.',
            'Analise o documento fornecido e extraia os dados da transação.',
            'Retorne APENAS um JSON válido, sem markdown e sem texto adicional, com exatamente estes campos:',
            '{"nome_pagador":null,"cpf_cnpj":null,"instituicao":null,"numero_transacao":null,"data_hora":null,"valor":null}',
            'Preencha cada campo com o valor encontrado no documento.',
            'Se um campo não estiver disponível, mantenha null.',
            'O campo valor deve estar no formato "R$ 0,00".',
            'O campo data_hora deve estar no formato "DD/MM/YYYY HH:MM".',
            'O campo cpf_cnpj deve conter apenas dígitos, sem pontuação.',
        ]);
    }

    private function buildImageMessages(string $base64, string $mimetype): array
    {
        return [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            [
                'role'    => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Analise este comprovante/extrato PIX:'],
                    [
                        'type'      => 'image_url',
                        'image_url' => [
                            'url'    => "data:{$mimetype};base64,{$base64}",
                            'detail' => 'high',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function buildPdfMessages(string $base64): array
    {
        $binary  = base64_decode($base64);
        $tmpPdf  = tempnam(sys_get_temp_dir(), 'pix_wpp_') . '.pdf';
        file_put_contents($tmpPdf, $binary);

        try {
            // 1. Tenta extração de texto (PDF com camada de texto)
            $parser = new PdfParser();
            $text   = trim($parser->parseFile($tmpPdf)->getText());

            if (!empty($text)) {
                if (mb_strlen($text) > 8000) {
                    $text = mb_substr($text, 0, 8000);
                }
                return [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => "Analise este comprovante/extrato PIX:\n\n{$text}"],
                ];
            }

            // 2. PDF baseado em imagem — converte primeira página com ImageMagick + Ghostscript
            $tmpImg = sys_get_temp_dir() . '/pix_wpp_' . uniqid() . '.jpg';
            $cmd    = sprintf(
                '/opt/homebrew/bin/magick -density 200 %s[0] -quality 85 %s 2>&1',
                escapeshellarg($tmpPdf),
                escapeshellarg($tmpImg)
            );
            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($tmpImg) && filesize($tmpImg) > 0) {
                $imgBase64 = base64_encode(file_get_contents($tmpImg));
                @unlink($tmpImg);
                return $this->buildImageMessages($imgBase64, 'image/jpeg');
            }

            throw new \RuntimeException(
                'PDF baseado em imagem e conversão falhou. Instale ghostscript: brew install ghostscript'
            );
        } finally {
            @unlink($tmpPdf);
        }
    }

    private function isDuplicate(string $imageHash, ?string $txid, ?string $nome, ?string $valor, ?string $data): bool
    {
        $base = WhatsappPixExtraction::where('status', 'confirmed');

        // 1. Mesma imagem (hash idêntico) — mais confiável
        if ((clone $base)->where('image_hash', $imageHash)->exists()) {
            return true;
        }

        // 2. Mesmo número de transação exato
        if ($txid && (clone $base)->where('numero_transacao', $txid)->exists()) {
            return true;
        }

        // 3. Mesma combinação pagador + valor + data (screenshot diferente do mesmo PIX)
        if ($nome && $valor && $data) {
            if ((clone $base)
                ->where('pix_nome', $nome)
                ->where('pix_valor', $valor)
                ->where('pix_data', $data)
                ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function formatConfirmed(array $json): string
    {
        $nome  = $json['nome_pagador'] ?? 'N/D';
        $valor = $json['valor']        ?? 'N/D';
        $data  = $json['data_hora']    ?? 'N/D';

        return "PIX CONFIRMADO: \t{$data}\n{$nome}\t{$valor}";
    }

    private function formatDuplicate(array $json): string
    {
        $nome  = $json['nome_pagador']     ?? 'N/D';
        $valor = $json['valor']            ?? 'N/D';
        $data  = $json['data_hora']        ?? 'N/D';

        return "⚠️ PIX NÃO CONFIRMADO - DUPLICADO\n{$data}\n{$nome}\t{$valor}";
    }

    private function formatFalse(array $json): string
    {
        $nome  = $json['nome_pagador'] ?? 'N/D';
        $valor = $json['valor']        ?? 'N/D';
        $data  = $json['data_hora']    ?? 'N/D';

        return "❌ PIX NÃO LOCALIZADO\n{$data}\n{$nome}\t{$valor}";
    }

    private function parseJson(string $raw): ?array
    {
        $json = json_decode(trim($raw), true);

        if (!is_array($json)) {
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $json = json_decode($m[0], true);
            }
        }

        if (!is_array($json)) {
            Log::warning('WhatsappWebhook: IA não retornou JSON válido', ['raw' => $raw]);
            return null;
        }

        return $json;
    }

    private function extFromMime(string $mimetype): string
    {
        return match (true) {
            str_contains($mimetype, 'jpeg') => 'jpg',
            str_contains($mimetype, 'png')  => 'png',
            str_contains($mimetype, 'gif')  => 'gif',
            str_contains($mimetype, 'webp') => 'webp',
            str_contains($mimetype, 'pdf')  => 'pdf',
            default                         => 'bin',
        };
    }
}
