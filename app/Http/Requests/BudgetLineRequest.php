<?php

namespace App\Http\Requests;

use App\Models\BudgetLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetLineRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $money = ['sometimes', 'nullable', 'numeric', 'between:-9999999999,9999999999'];

        return [
            'section' => [$creating ? 'required' : 'prohibited', Rule::in(BudgetLine::SECTIONS)],
            'tipo' => ['sometimes', 'nullable', 'string', 'max:20'],
            'fecha' => ['sometimes', 'nullable', 'date'],
            'contact_line_id' => [
                'sometimes',
                'nullable',
                Rule::exists('budget_lines', 'id')->where('section', BudgetLine::SECTION_CONTACT),
            ],
            'party_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'telefono' => ['sometimes', 'nullable', 'string', 'max:50'],
            'categoria' => ['sometimes', 'nullable', 'string', 'max:100'],
            'producto' => ['sometimes', 'nullable', 'string', 'max:255'],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cantidad' => $money,
            'unit_price' => $money,
            'costo' => $money,
            'monto' => $money,
            'payment_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'invoice_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ganancia' => $money,
            'gastos_personales' => $money,
            'perdidas_mercancia' => $money,
            // Registro origen de una factura: una compra o una venta.
            'linked_line_id' => [
                'sometimes',
                'nullable',
                Rule::exists('budget_lines', 'id')->whereIn('section', BudgetLine::PAYABLE_SECTIONS),
            ],
            'notas' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contact_line_id' => 'contacto',
            'linked_line_id' => 'registro origen',
            'party_name' => 'nombre',
            'unit_price' => 'precio unitario',
            'payment_status' => 'estado de pago',
            'payment_method' => 'método de pago',
            'invoice_number' => 'nº de factura',
        ];
    }
}
