<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BillingPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_accounting_processes') === true
            && (int) session('tenant_id') === (int) $this->route('tenant')?->id;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'project_ids' => ['nullable', 'array', 'min:1', 'max:20'],
            'project_ids.*' => ['integer', 'distinct', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'organization_id' => ['nullable', 'integer', 'min:1'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'issued_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'distribution_ids' => ['required', 'array', 'max:5000'],
            'distribution_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $projects = collect($this->input('project_ids', []))->filter();
            if ($projects->isEmpty() && ! $this->filled('project_id')) {
                $validator->errors()->add('project_ids', 'Selecione ao menos um projeto.');
            }
            if ($this->filled('customer_id') === $this->filled('organization_id')) {
                $validator->errors()->add('customer_id', 'Escolha exatamente um cliente ou uma organização compradora.');
            }
        }];
    }
}
