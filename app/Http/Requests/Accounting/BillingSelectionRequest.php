<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BillingSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_accounting_processes') === true
            && (int) session('tenant_id') === (int) $this->route('tenant')?->id;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['period', 'receipts', 'manual'])],
            'project_ids' => ['required', 'array', 'min:1', 'max:20'],
            'project_ids.*' => ['integer', 'distinct', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'receipt_codes' => ['nullable', 'array', 'max:200'],
            'receipt_codes.*' => ['string', 'max:300'],
            'distribution_ids' => ['nullable', 'array', 'max:5000'],
            'distribution_ids.*' => ['integer', 'distinct', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('customer_id') === $this->filled('organization_id')) {
                $validator->errors()->add('customer_id', 'Escolha exatamente um cliente ou uma organização compradora.');
            }
            if ($this->input('mode') === 'receipts' && empty($this->input('receipt_codes'))) {
                $validator->errors()->add('receipt_codes', 'Informe ao menos um comprovante de origem.');
            }
        }];
    }
}
