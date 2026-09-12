<?php

namespace App\Http\Requests;

use App\Models\BudgetLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BudgetLinePaymentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            // La compra o venta a la que se abona. Al editar no se cambia de fila.
            'budget_line_id' => [
                $creating ? 'required' : 'prohibited',
                Rule::exists('budget_lines', 'id')->whereIn('section', BudgetLine::PAYABLE_SECTIONS),
            ],
            'fecha' => [$creating ? 'required' : 'sometimes', 'date'],
            'method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            'amount_bs' => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
            // No lleva `sometimes`: si falta, `required_with` no llegaría a evaluarse.
            'exchange_rate' => ['nullable', 'required_with:amount_bs', 'numeric', 'min:0.0001'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Un abono se carga en la moneda del período o en bolívares con la tasa del
     * día; al crearlo tiene que venir uno de los dos.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->isMethod('post') || $this->filled('amount') || $this->filled('amount_bs')) {
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
            'budget_line_id' => 'registro vinculado',
            'fecha' => 'fecha',
            'method' => 'método',
            'amount' => 'monto',
            'amount_bs' => 'monto en bolívares',
            'exchange_rate' => 'tasa de cambio',
        ];
    }
}
