<?php

namespace App\Services\Services;

use Illuminate\Validation\Rule;

final class ServiceReportLayout
{
    public const FIELDS = [
        'service' => 'Serviço',
        'quantity' => 'Quantidade e medições',
        'beneficiary' => 'Nome do cliente',
        'service_value' => 'Valor cobrado',
        'received' => 'Valor recebido',
        'receivable_balance' => 'Saldo a receber',
        'provider' => 'Nome do prestador',
        'provider_due' => 'Valor do prestador',
        'provider_paid' => 'Valor pago ao prestador',
        'provider_balance' => 'Saldo a pagar',
        'payments' => 'Histórico de pagamentos (prestação de contas)',
    ];

    private const CUSTOMER = ['beneficiary', 'service_value', 'received', 'receivable_balance'];
    private const PROVIDER = ['provider', 'provider_due', 'provider_paid', 'provider_balance'];

    public readonly string $scope;
    public readonly bool $custom;
    public readonly array $fields;

    public function __construct(array $input = [])
    {
        $this->scope = $input['report_scope'] ?? 'both';
        $this->custom = isset($input['custom_fields']);
        $excluded = match ($this->scope) {
            'customer' => self::PROVIDER,
            'provider' => self::CUSTOMER,
            default => [],
        };
        $available = array_diff(array_keys(self::FIELDS), $excluded);
        $this->fields = array_values($this->custom ? array_intersect($available, $input['report_fields'] ?? []) : $available);
    }

    public static function rules(): array
    {
        return [
            'report_scope' => 'nullable|in:both,customer,provider',
            'custom_fields' => 'sometimes|accepted',
            'report_fields' => 'nullable|array',
            'report_fields.*' => ['string', Rule::in(array_keys(self::FIELDS))],
        ];
    }

    public function has(string $field): bool
    {
        return in_array($field, $this->fields, true);
    }

    public function complete(): bool
    {
        return $this->scope === 'both' && !$this->custom;
    }

    public function moneyColumns(): array
    {
        return array_intersect_key(self::FIELDS, array_flip(array_merge(array_slice(self::CUSTOMER, 1), array_slice(self::PROVIDER, 1))), array_flip($this->fields));
    }
}
