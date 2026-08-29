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

        return [
            'section' => [$creating ? 'required' : 'prohibited', Rule::in(BudgetLine::SECTIONS)],
            'detail' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ideal_percent' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'planned' => ['sometimes', 'nullable', 'numeric', 'between:-9999999999,9999999999'],
            'actual' => ['sometimes', 'nullable', 'numeric', 'between:-9999999999,9999999999'],
            'is_unexpected' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['detail', 'planned', 'actual'] as $key) {
            if ($this->get($key) === null && $this->has($key)) {
                $this->merge([$key => $key === 'detail' ? '' : 0]);
            }
        }
    }
}
