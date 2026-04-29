<?php

namespace App\Services;

class CurrencyService
{
    /**
     * Converte um valor usando a taxa informada.
     *
     * @param float $amount
     * @param float $rate
     * @return float
     */
    public function convert(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }
}
