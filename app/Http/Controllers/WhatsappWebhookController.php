<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\WhatsappGroup;
use App\Models\WhatsappPixExtraction;
use App\Services\ClientBankService;
use App\Services\ExchangeRateService;
use App\Services\OperationReversalService;
use App\Services\TransactionService;
use App\Services\WalletService;
use App\Services\WhatsappNodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;

class WhatsappWebhookController extends Controller
{
    public function __construct(
        protected ClientBankService $clientBankService,
        protected WalletService $walletService,
        protected TransactionService $transactionService,
        protected OperationReversalService $reversalService,
        protected ExchangeRateService $exchangeRateService,
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
            $extraction = WhatsappPixExtraction::create([
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

            // Credita a carteira do cliente vinculado a este grupo, quando o PIX foi confirmado no extrato bancário
            if ($status === 'confirmed' && $group->client_id) {
                $this->creditClientWallet($group->client_id, $pixValor, $extraction);
            }

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

    /**
     * Credita o valor do PIX confirmado na carteira BRL do cliente vinculado ao grupo,
     * registrando a transação com referência ao comprovante que a originou.
     */
    private function creditClientWallet(int $clientId, string $pixValor, WhatsappPixExtraction $extraction): void
    {
        try {
            $valor = $this->clientBankService->parseValor($pixValor);

            $label = 'Depósito PIX (WhatsApp) R$ ' . number_format($valor, 2, ',', '.');

            // Mesma taxa (Frankfurter/fallback - R$0,02 + spread do cliente) e mesmos
            // campos que o depósito manual via modal grava, para que o depósito já
            // entre pronto para compra/venda de dólar sem ajuste manual.
            $client = Client::find($clientId);
            $rateData = $this->exchangeRateService->getUsdBrlRate();
            $exchangeRate = null;
            $convertedAmount = null;

            if ($rateData !== null) {
                $spreadValue = ((float) ($client->spread_points ?? 0)) * 0.01;
                $exchangeRate = $rateData['rate'] + $spreadValue;
                $convertedAmount = round($valor / $exchangeRate, 2);
            }

            $this->reversalService->capture('deposit_brl', $clientId, [], $label, function () use ($clientId, $valor, $extraction, $exchangeRate, $convertedAmount) {
                $this->walletService->updateBalance($clientId, 'BRL', $valor);

                return $this->transactionService->create([
                    'client_id'                  => $clientId,
                    'type'                       => 'deposit',
                    'currency'                   => 'BRL',
                    'amount'                     => $valor,
                    'payment_method'             => 'pix',
                    'exchange_rate'              => $exchangeRate,
                    'converted_currency'         => 'USD',
                    'converted_amount'           => $convertedAmount,
                    'status'                     => 'ambos_abertos',
                    'description'                => 'PIX de ' . ($extraction->pix_nome ?? 'pagador não identificado') . ' via WhatsApp',
                    'whatsapp_pix_extraction_id' => $extraction->id,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('WhatsappWebhook: erro ao creditar carteira do cliente a partir do PIX confirmado', [
                'client_id'      => $clientId,
                'extraction_id'  => $extraction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reprocessa um comprovante já salvo (ex.: falhou originalmente por erro da OpenAI).
     * Refaz a chamada à IA a partir da imagem/PDF já armazenado, atualiza o registro
     * existente (em vez de criar um novo) e credita a carteira se confirmado.
     *
     * @param  bool  $persist  false = não grava nada nem credita, só retorna o resultado (dry-run)
     */
    public function reprocess(WhatsappPixExtraction $extraction, bool $persist = true, bool $notify = false): array
    {
        $binary = Storage::disk('do_spaces')->get($extraction->image_path);
        if ($binary === null) {
            throw new \RuntimeException("Arquivo não encontrado em do_spaces: {$extraction->image_path}");
        }

        $mimetype = $extraction->mimetype;
        $isPdf    = str_contains($mimetype, 'pdf');
        $base64   = base64_encode($binary);

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

        if (!$aiResponse->successful()) {
            Log::warning('WhatsappWebhook: OpenAI falhou (reprocess)', [
                'extraction_id' => $extraction->id,
                'status'        => $aiResponse->status(),
            ]);

            return [
                'status'  => 'ai_failed',
                'http'    => $aiResponse->status(),
            ];
        }

        $aiRaw  = $aiResponse->json('choices.0.message.content', '');
        $aiData = $this->parseJson($aiRaw);

        $numeroTransacao = $aiData['numero_transacao'] ?? null;
        $pixNome         = $aiData['nome_pagador'] ?? null;
        $pixValor        = $aiData['valor'] ?? null;
        $pixData         = $aiData['data_hora'] ?? null;

        $isDuplicate = $this->isDuplicate($extraction->image_hash, $numeroTransacao, $pixNome, $pixValor, $pixData, $extraction->id);

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

            if ($bankTransactionId && WhatsappPixExtraction::where('bank_transaction_id', $bankTransactionId)
                    ->where('status', 'confirmed')
                    ->where('id', '!=', $extraction->id)
                    ->exists()
            ) {
                $isDuplicate = true;
            }
        }

        $status = match (true) {
            $isDuplicate                => 'duplicate',
            $bankError                  => 'unverified',
            $bankTransactionId !== null => 'confirmed',
            default                     => 'false_payment',
        };

        $result = [
            'status'               => $status,
            'numero_transacao'     => $numeroTransacao,
            'pix_nome'             => $pixNome,
            'pix_valor'            => $pixValor,
            'pix_data'             => $pixData,
            'bank_transaction_id'  => $bankTransactionId,
            'ai_data'              => $aiData,
            'ai_raw'               => $aiRaw,
        ];

        if (!$persist) {
            return $result;
        }

        $extraction->fill([
            'numero_transacao'    => $numeroTransacao,
            'pix_nome'            => $pixNome,
            'pix_valor'           => $pixValor,
            'pix_data'            => $pixData,
            'status'              => $status,
            'bank_transaction_id' => $bankTransactionId,
            'ai_data'             => $aiData,
            'ai_raw'              => $aiRaw,
        ]);
        $extraction->save();

        $group = $extraction->group;

        if ($status === 'confirmed' && $group?->client_id && !$extraction->transaction()->exists()) {
            $this->creditClientWallet($group->client_id, $pixValor, $extraction);
        }

        if ($notify && $aiData && $group) {
            $reply = match ($status) {
                'confirmed'     => $this->formatConfirmed($aiData),
                'duplicate'     => $this->formatDuplicate($aiData),
                'false_payment' => $this->formatFalse($aiData),
                default         => null,
            };

            if ($reply) {
                $this->whatsappNodeService->sendText($group->chat_id, $reply);
            }
        }

        return $result;
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
            'ATENÇÃO ao campo valor — documentos brasileiros usam PONTO como separador de milhar e VÍRGULA como separador decimal (é o INVERSO do padrão americano).',
            'Exemplo: o texto "7.300" significa sete mil e trezentos reais (R$ 7.300,00), e NÃO sete reais e trinta centavos. O texto "46.128,60" significa quarenta e seis mil, cento e vinte e oito reais e sessenta centavos.',
            'Comprovantes do Mercado Pago exibem os centavos em fonte pequena SOBRESCRITA logo após o valor principal (ex.: "R$ 46.128" com um "60" pequeno elevado ao final) — esses dois dígitos sobrescritos SÃO os centavos, não um valor separado. Junte tudo como "R$ 46.128,60".',
            'NUNCA trunque, arredonde ou descarte dígitos do valor. Copie TODOS os dígitos da parte inteira exatamente como aparecem no documento, da esquerda para a direita, antes de adicionar os centavos.',
            'Antes de responder, releia o valor extraído e confira dígito por dígito contra o documento original.',
            'O campo valor deve estar no formato "R$ 0.000,00" (com ponto de milhar quando aplicável).',
            'O campo data_hora deve estar no formato "DD/MM/YYYY HH:MM".',
            'O campo cpf_cnpj deve conter apenas dígitos, sem pontuação.',
            'ATENÇÃO ao campo nome_pagador — transcreva o nome EXATAMENTE como está escrito no documento, letra por letra. NUNCA "corrija" ou normalize a grafia para a variante mais comum (ex.: se o documento diz "Willian", responda "Willian", e NÃO "William"; se diz "Marcia", NÃO troque por "Márcia"). Releia o nome extraído e confira letra por letra contra o documento antes de responder.',
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
            // 1. Tenta extração de texto (PDF com camada de texto).
            //    PDFs protegidos (secured/encrypted) fazem o parser lançar exceção;
            //    nesse caso caímos para a renderização como imagem abaixo.
            $text = '';
            try {
                $pdfConfig = new PdfParserConfig();
                $pdfConfig->setIgnoreEncryption(true);
                $parser = new PdfParser([], $pdfConfig);
                $text   = trim($parser->parseFile($tmpPdf)->getText());
            } catch (\Throwable $e) {
                Log::info('WhatsappWebhook: extração de texto do PDF falhou, tentando renderizar como imagem', [
                    'error' => $e->getMessage(),
                ]);
            }

            if (!empty($text)) {
                if (mb_strlen($text) > 8000) {
                    $text = mb_substr($text, 0, 8000);
                }
                return [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => "Analise este comprovante/extrato PIX:\n\n{$text}"],
                ];
            }

            // 2. PDF baseado em imagem (ou protegido) — renderiza a primeira página via Imagick/Ghostscript
            try {
                $imagick = new \Imagick();
                $imagick->setResolution(200, 200);
                $imagick->readImage($tmpPdf . '[0]');
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $imgBase64 = base64_encode($imagick->getImageBlob());
                $imagick->clear();
                $imagick->destroy();
            } catch (\Throwable $e) {
                throw new \RuntimeException('PDF baseado em imagem e conversão falhou: ' . $e->getMessage());
            }

            return $this->buildImageMessages($imgBase64, 'image/jpeg');
        } finally {
            @unlink($tmpPdf);
        }
    }

    private function isDuplicate(string $imageHash, ?string $txid, ?string $nome, ?string $valor, ?string $data, ?int $excludeId = null): bool
    {
        $base = WhatsappPixExtraction::where('status', 'confirmed');
        if ($excludeId !== null) {
            $base->where('id', '!=', $excludeId);
        }

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
