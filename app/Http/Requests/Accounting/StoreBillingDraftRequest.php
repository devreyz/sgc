<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Validation\Rule;

class StoreBillingDraftRequest extends BillingPreviewRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && ($this->user()?->can('create_customer::billing::receipt') === true
            || $this->user()?->can('update_customer::billing::receipt') === true);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'issued_at' => ['required', 'date'],
            'operation_key' => ['required', 'uuid'],
        ]);
    }
}
