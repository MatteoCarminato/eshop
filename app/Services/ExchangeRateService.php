<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    /**
     * Busca a cotação atual USD/BRL via APIs de câmbio (Frankfurter como fonte
     * primária, open.er-api.com como fallback), aplica desconto de R$0,02 e
     * cacheia por 15s. Retorna null se ambas as fontes falharem.
     */
    public function getUsdBrlRate(): ?array
    {
        return Cache::remember('usd_brl_rate', 15, function () {
            $rate = $this->fetchFromFrankfurter();
            if ($rate !== null) {
                return ['rate' => $rate - 0.02, 'source' => 'frankfurter.dev'];
            }

            $rate = $this->fetchFromExchangeRateApi();
            if ($rate !== null) {
                return ['rate' => $rate - 0.02, 'source' => 'open.er-api.com'];
            }

            return null;
        });
    }

    private function fetchFromFrankfurter(): ?float
    {
        try {
            $response = Http::timeout(10)->get('https://api.frankfurter.dev/v1/latest', [
                'base' => 'USD',
                'symbols' => 'BRL',
            ]);

            if (!$response->ok()) {
                return null;
            }

            $rate = $response->json('rates.BRL');

            return $rate !== null ? (float) $rate : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchFromExchangeRateApi(): ?float
    {
        try {
            $response = Http::timeout(10)->get('https://open.er-api.com/v6/latest/USD');

            if (!$response->ok()) {
                return null;
            }

            $rate = $response->json('rates.BRL');

            return $rate !== null ? (float) $rate : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
