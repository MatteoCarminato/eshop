<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'currency' => 'required|in:BRL,USD,USDT',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
        ];
    }
}
