<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFinancialReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $tenantId = (int) session('tenant_id');

        return [
            'payer_type' => ['required', Rule::in(['customer', 'organization', 'associate', 'supplier', 'service_provider', 'other'])],
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_document' => ['nullable', 'string', 'max:30'],
            'payer_contact' => ['nullable', 'string', 'max:255'],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'bank_account_id' => [
                'required',
                'integer',
                Rule::exists('bank_accounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', true)
                    ->whereNull('deleted_at')),
            ],
            'chart_account_id' => [
                'nullable',
                'integer',
                Rule::exists('chart_accounts', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $tenantId)
                    ->where('status', true)
                    ->where('allows_entries', true)
                    ->whereNull('deleted_at')),
            ],
            'payment_method' => ['required', Rule::in(['dinheiro', 'pix', 'transferencia', 'boleto', 'cartao', 'cheque', 'outro'])],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'manual_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.description' => ['required_with:items.*', 'string', 'max:1000'],
            'items.*.quantity' => ['required_with:items.*', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.unit' => ['required_with:items.*', 'string', 'max:30'],
            'items.*.unit_price' => ['required_with:items.*', 'numeric', 'gte:0', 'max:9999999999'],
            'items.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_on.before_or_equal' => 'A data do recebimento não pode estar no futuro.',
            'bank_account_id.exists' => 'A conta selecionada não está disponível nesta organização.',
            'chart_account_id.exists' => 'A classificação selecionada não aceita lançamentos nesta organização.',
            'items.min' => 'Adicione ao menos um item ao recibo.',
            'items.max' => 'Um recibo pode ter no máximo 50 itens.',
            'items.*.quantity.gt' => 'A quantidade deve ser maior que zero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = collect($this->input('items', []))
                ->filter(fn ($item) => is_array($item) && filled($item['description'] ?? null));

            if ($items->isEmpty() && (float) $this->input('manual_amount', 0) <= 0) {
                $validator->errors()->add('manual_amount', 'Informe o valor recebido ou detalhe ao menos um item.');
            }
        });
    }
}
