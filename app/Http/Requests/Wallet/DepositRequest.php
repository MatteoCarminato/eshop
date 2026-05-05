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
            'currency' => 'required|in:BRL,USD',
            'amount' => 'required|numeric|min:0.01',
            'fee' => 'nullable|numeric|min:0.000001|required_if:currency,BRL',
            'payment_method' => 'required|in:pix,dinheiro,efetivo,usdt',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $currency = $this->input('currency');
            $paymentMethod = $this->input('payment_method');

            if ($currency === 'BRL' && !in_array($paymentMethod, ['pix', 'dinheiro'], true)) {
                $validator->errors()->add('payment_method', 'Para BRL, use Pix ou Dinheiro.');
            }

            if ($currency === 'USD' && !in_array($paymentMethod, ['efetivo', 'usdt'], true)) {
                $validator->errors()->add('payment_method', 'Para Dólar, use Efetivo ou USDT.');
            }
        });
    }
}
