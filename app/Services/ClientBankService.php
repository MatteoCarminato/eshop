<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClientBankService
{
    private string $baseUrl;
    private string $name;
    private string $password;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.clientbank.url'), '/');
        $this->name     = config('services.clientbank.name');
        $this->password = config('services.clientbank.password');
    }

    public function login(): string
    {
        $response = Http::timeout(15)->post("{$this->baseUrl}/api/acess/login", [
            'name'     => $this->name,
            'password' => $this->password,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("ClientBank login falhou: HTTP {$response->status()}");
        }

        // A API retorna o token como string bruta ou dentro de um JSON
        $body = trim($response->body(), " \t\n\r\"");
        if (!$body) {
            throw new \RuntimeException('ClientBank: token vazio na resposta do login');
        }

        return $body;
    }

    public function getBrlWalletId(string $token): string
    {
        $response = Http::withToken($token)
            ->timeout(15)
            ->get("{$this->baseUrl}/api/client/profile");

        if (!$response->successful()) {
            throw new \RuntimeException("ClientBank profile falhou: HTTP {$response->status()}");
        }

        $wallets = $response->json('wallets', []);
        $brl     = collect($wallets)->firstWhere('coin', 'BRL');

        if (!$brl) {
            throw new \RuntimeException('ClientBank: carteira BRL não encontrada no perfil');
        }

        return $brl['id'];
    }

    /**
     * Busca o extrato, paginando até a API não devolver mais itens.
     * Sem filtro de data, a API só devolve os ~500 lançamentos mais recentes,
     * então comprovantes de PIX mais antigos nunca eram encontrados.
     */
    public function getStatements(string $token, string $walletId, ?Carbon $initialDate = null, ?Carbon $endDate = null): array
    {
        $query = ['WalletId' => $walletId];

        if ($initialDate && $endDate) {
            $query['TypeFilterDateStatement'] = 'DateTransaction';
            $query['InitialDate']             = $initialDate->toDateString();
            $query['EndDate']                 = $endDate->toDateString();
        }

        $items    = [];
        $size     = 100;
        $maxPages = 50; // salvaguarda contra loop infinito (5.000 itens)

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = Http::withToken($token)
                ->timeout(15)
                ->get("{$this->baseUrl}/api/statements/clients", $query + [
                    'Page' => $page,
                    'Size' => $size,
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException("ClientBank statements falhou: HTTP {$response->status()}");
            }

            $pageItems = $response->json('itens', []);
            $items     = array_merge($items, $pageItems);

            if (count($pageItems) < $size) {
                break;
            }
        }

        return $items;
    }

    /**
     * Autentica e verifica se a transação PIX existe no extrato.
     * Retorna o ID da transação na API se encontrada, null caso contrário.
     */
    public function verifyPix(string $nome, string $valorStr, string $dataHora): ?string
    {
        try {
            $token    = $this->login();
            $walletId = $this->getBrlWalletId($token);

            $valorFloat = $this->parseValor($valorStr);
            $dataAi     = Carbon::createFromFormat('d/m/Y H:i', $dataHora);

            // Busca já filtrada pela data do comprovante (com folga de 1 dia para
            // cobrir fuso horário e a tolerância de horário usada no match abaixo),
            // em vez de depender da janela padrão da API (últimos ~500 lançamentos,
            // que às vezes não alcança comprovantes de mais de 1-2 semanas atrás).
            $items = $this->getStatements(
                $token,
                $walletId,
                $dataAi->copy()->subDay(),
                $dataAi->copy()->addDay()
            );

            return $this->findInStatements($items, $nome, $valorFloat, $dataAi);
        } catch (\Throwable $e) {
            Log::error('ClientBank: erro na verificação do PIX', [
                'error' => $e->getMessage(),
                'nome'  => $nome,
                'valor' => $valorStr,
                'data'  => $dataHora,
            ]);
            throw $e;
        }
    }

    /**
     * Retorna o ID da transação encontrada ou null.
     */
    private function findInStatements(array $items, string $nome, float $valor, Carbon $dataAi): ?string
    {
        $nomeLower = mb_strtolower($nome);

        foreach ($items as $item) {
            // Só entradas (amount positivo)
            if (($item['amount'] ?? 0) <= 0) {
                continue;
            }

            // Valor com tolerância de R$ 0,01
            if (abs($item['amount'] - $valor) > 0.005) {
                continue;
            }

            // Nome do pagador (match parcial, case-insensitive, tolerante a pequena divergência de grafia)
            $desc = mb_strtolower($item['description'] ?? '');
            if (!$this->nomesCorrespondem($nomeLower, $desc)) {
                continue;
            }

            // Data e horário (o comprovante só traz HH:MM, sem segundos; toleramos até 3h + 2min
            // do transactionDate, somando o fuso (3h) à margem de segundos que o transactionDate tem)
            try {
                $dataTx = Carbon::parse($item['transactionDate']);
                if (abs($dataTx->diffInSeconds($dataAi, false)) > 10920) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            return $item['id'];
        }

        return null;
    }

    /**
     * Compara nomes tolerando pequena divergência de grafia — a IA às vezes "corrige"
     * uma grafia de nome incomum para a variante mais comum (ex.: lê "Willian" como "William").
     */
    private function nomesCorrespondem(string $nome, string $desc): bool
    {
        if ($nome === '' || $desc === '') {
            return false;
        }

        if (str_contains($desc, $nome) || str_contains($nome, $desc)) {
            return true;
        }

        $tamanho    = max(mb_strlen($nome), mb_strlen($desc));
        $tolerancia = max(1, (int) floor($tamanho / 10));

        return levenshtein($nome, $desc) <= $tolerancia;
    }

    public function parseValor(string $valor): float
    {
        // "R$ 7.195,00" → 7195.00
        $v = preg_replace('/[R$\s]/u', '', $valor);
        $v = str_replace('.', '', $v);   // separador de milhar
        $v = str_replace(',', '.', $v);  // decimal
        return (float) $v;
    }
}
