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
            'fecha' => ['sometimes', 'nullable', 'date'],
            'party_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'producto' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cantidad' => $money,
            'unit_price' => $money,
            'payment_status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
            'ganancia' => $money,
            'gastos_personales' => $money,
            'perdidas_mercancia' => $money,
            'inversiones' => $money,
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
