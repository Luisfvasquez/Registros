<?php

namespace App\Http\Requests;

use App\Models\BudgetPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BudgetPeriodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // POST creates a period (identity fields required); PATCH is a partial
        // inline edit of the header, so every field is optional.
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'year' => [$required, 'integer', 'between:2000,2100'],
            'month' => [$required, 'integer', 'between:1,12'],
            'currency' => [$required, 'string', 'size:3'],
            'status' => ['sometimes', Rule::in(['abierto', 'cerrado'])],
            'available_money' => ['sometimes', 'numeric', 'between:-999999999999,999999999999'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->string('currency'))]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var BudgetPeriod|null $current */
            $current = $this->route('period');

            $year = $this->has('year') ? $this->integer('year') : $current?->year;
            $month = $this->has('month') ? $this->integer('month') : $current?->month;
            $currency = $this->has('currency') ? $this->string('currency')->toString() : $current?->currency;

            if ($year === null || $month === null || $currency === null) {
                return;
            }

            $exists = BudgetPeriod::query()
                ->where('year', $year)
                ->where('month', $month)
                ->where('currency', $currency)
                ->when($current, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->exists();

            if ($exists) {
                $validator->errors()->add('month', __('Ya existe un período para ese año, mes y moneda.'));
            }
        });
    }
}
