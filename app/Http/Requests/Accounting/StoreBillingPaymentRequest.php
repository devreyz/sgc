<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update_customer::billing::receipt') === true
            && (int) session('tenant_id') === (int) $this->route('tenant')?->id;
    }

    public function rules(): array
    {
        return [
            'operation_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['pix', 'transfer', 'cash', 'check', 'card', 'other'])],
            'bank_account_id' => ['nullable', 'integer', 'min:1'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
