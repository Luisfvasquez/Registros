<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Un abono cargado contra una factura entera. El monto se reparte después entre
 * las compras o ventas que agrupa.
 */
class BudgetInvoicePaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'amount_bs' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            // No lleva `sometimes`: si falta, `required_with` no llegaría a evaluarse.
            'exchange_rate' => ['nullable', 'required_with:amount_bs', 'numeric', 'min:0.0001'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('amount') || $this->filled('amount_bs')) {
                    return;
                }

                $validator->errors()->add('amount', __('Indica el monto del abono o el monto en bolívares con su tasa.'));
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fecha' => 'fecha',
            'method' => 'método',
            'amount' => 'monto',
            'amount_bs' => 'monto en bolívares',
            'exchange_rate' => 'tasa de cambio',
        ];
    }
}
