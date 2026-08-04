<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssociateReceipt;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\ChartAccount;
use App\Models\Customer;
use App\Models\CustomerBillingReceipt;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\PriceTable;
use App\Models\ServiceOrderPayment;
use App\Models\ServiceProvider;
use App\Models\Supplier;
use Illuminate\Validation\Rule;

class FinanceModuleRegistry
{
    public static function all(): array
    {
        return [
            'accounts' => self::module(BankAccount::class, 'Contas e caixas', 'landmark', 'bank::account', ['name', 'type', 'bank_name', 'current_balance', 'status'], [
                'name' => self::field('Nome', 'text', ['required', 'string', 'max:255']),
                'type' => self::field('Tipo', 'select', ['required', Rule::in(['caixa', 'corrente', 'poupanca', 'investimento', 'aplicacao'])], ['caixa' => 'Caixa', 'corrente' => 'Conta corrente', 'poupanca' => 'Poupanca', 'investimento' => 'Investimento', 'aplicacao' => 'Aplicacao']),
                'bank_name' => self::field('Banco', 'text', ['nullable', 'string', 'max:255']),
                'agency' => self::field('Agencia', 'text', ['nullable', 'string', 'max:10']),
                'account_number' => self::field('Conta', 'text', ['nullable', 'string', 'max:20']),
                'initial_balance' => self::field('Saldo inicial', 'money', ['required', 'numeric']),
                'is_default' => self::field('Conta padrao', 'toggle', ['boolean']),
                'status' => self::field('Ativa', 'toggle', ['boolean']),
                'notes' => self::field('Observacoes', 'textarea', ['nullable', 'string', 'max:3000']),
            ]),
            'movements' => self::module(CashMovement::class, 'Movimentacoes', 'arrow-left-right', 'cash::movement', ['movement_date', 'description', 'type', 'amount', 'document_number'], [], false),
            'expenses' => self::module(Expense::class, 'Despesas', 'circle-minus', 'expense', ['due_date', 'description', 'status', 'amount', 'paid_amount'], [
                'description' => self::field('Descricao', 'text', ['required', 'string', 'max:1000']),
                'document_number' => self::field('Documento', 'text', ['nullable', 'string', 'max:100']),
                'amount' => self::field('Valor', 'money', ['required', 'numeric', 'gt:0']),
                'date' => self::field('Data', 'date', ['required', 'date']),
                'due_date' => self::field('Vencimento', 'date', ['nullable', 'date']),
                'status' => self::field('Situacao', 'select', ['required', Rule::in(['pending', 'paid', 'cancelled', 'overdue'])], ['pending' => 'Pendente', 'paid' => 'Paga', 'cancelled' => 'Cancelada', 'overdue' => 'Vencida']),
                'notes' => self::field('Observacoes', 'textarea', ['nullable', 'string', 'max:3000']),
            ]),
            'associate-receipts' => self::module(AssociateReceipt::class, 'Pagamentos a associados', 'hand-coins', 'associate::receipt', ['receipt_year', 'receipt_number', 'issued_at', 'status', 'total_net', 'amount_paid'], [], false),
            'customer-billings' => self::module(CustomerBillingReceipt::class, 'Cobrancas de clientes', 'file-check-2', 'customer::billing::receipt', ['receipt_year', 'receipt_number', 'issued_at', 'status', 'total_net', 'amount_paid'], [], false),
            'service-payments' => self::module(ServiceOrderPayment::class, 'Pagamentos de servicos', 'briefcase-business', 'service::order::payment', ['payment_date', 'service_order_id', 'type', 'status', 'final_amount'], [], false),
            'chart-accounts' => self::module(ChartAccount::class, 'Plano de contas', 'list-tree', 'chart::account', ['code', 'name', 'type', 'nature', 'status'], [
                'code' => self::field('Codigo', 'text', ['required', 'string', 'max:20']),
                'name' => self::field('Nome', 'text', ['required', 'string', 'max:255']),
                'type' => self::field('Tipo', 'select', ['required', Rule::in(['receita', 'despesa', 'ativo', 'passivo', 'patrimonio'])], ['receita' => 'Receita', 'despesa' => 'Despesa', 'ativo' => 'Ativo', 'passivo' => 'Passivo', 'patrimonio' => 'Patrimonio']),
                'nature' => self::field('Natureza', 'select', ['required', Rule::in(['debit', 'credit'])], ['debit' => 'Devedora', 'credit' => 'Credora']),
                'allows_entries' => self::field('Permite lancamentos', 'toggle', ['boolean']),
                'status' => self::field('Ativa', 'toggle', ['boolean']),
                'description' => self::field('Descricao', 'textarea', ['nullable', 'string', 'max:2000']),
            ]),
            'price-tables' => self::module(PriceTable::class, 'Tabelas de precos', 'tags', 'price::table', ['name', 'code', 'year', 'valid_from', 'valid_until', 'active'], [
                'name' => self::field('Nome', 'text', ['required', 'string', 'max:255']),
                'code' => self::field('Codigo', 'text', ['nullable', 'string', 'max:50']),
                'year' => self::field('Ano', 'number', ['nullable', 'integer', 'min:2000', 'max:2200']),
                'valid_from' => self::field('Valida desde', 'date', ['nullable', 'date']),
                'valid_until' => self::field('Valida ate', 'date', ['nullable', 'date', 'after_or_equal:valid_from']),
                'active' => self::field('Ativa', 'toggle', ['boolean']),
                'notes' => self::field('Observacoes', 'textarea', ['nullable', 'string', 'max:3000']),
            ]),
            'customers' => self::party(Customer::class, 'Clientes', 'contact', 'customer', 'status', 'cnpj'),
            'organizations' => self::party(Organization::class, 'Organizacoes compradoras', 'building-2', 'organization', 'active', 'cnpj'),
            'suppliers' => self::party(Supplier::class, 'Fornecedores', 'truck', 'supplier', 'status', 'cpf_cnpj'),
            'providers' => self::party(ServiceProvider::class, 'Prestadores', 'hard-hat', 'service::provider', 'status', 'cpf'),
            'assets' => self::module(Asset::class, 'Patrimonio', 'warehouse', 'asset', ['name', 'identifier', 'type', 'status', 'current_value'], [
                'name' => self::field('Nome', 'text', ['required', 'string', 'max:255']),
                'identifier' => self::field('Identificador', 'text', ['nullable', 'string', 'max:100']),
                'type' => self::field('Tipo', 'select', ['required', Rule::in(['trator', 'caminhao', 'veiculo', 'implemento', 'equipamento', 'imovel', 'outro'])], array_combine(['trator', 'caminhao', 'veiculo', 'implemento', 'equipamento', 'imovel', 'outro'], ['Trator', 'Caminhao', 'Veiculo', 'Implemento', 'Equipamento', 'Imovel', 'Outro'])),
                'brand' => self::field('Marca', 'text', ['nullable', 'string', 'max:100']),
                'model' => self::field('Modelo', 'text', ['nullable', 'string', 'max:100']),
                'acquisition_date' => self::field('Aquisicao', 'date', ['nullable', 'date']),
                'acquisition_value' => self::field('Valor de aquisicao', 'money', ['nullable', 'numeric', 'gte:0']),
                'current_value' => self::field('Valor atual', 'money', ['nullable', 'numeric', 'gte:0']),
                'status' => self::field('Situacao', 'select', ['required', Rule::in(['disponivel', 'em_uso', 'manutencao', 'inativo', 'vendido'])], array_combine(['disponivel', 'em_uso', 'manutencao', 'inativo', 'vendido'], ['Disponivel', 'Em uso', 'Manutencao', 'Inativo', 'Vendido'])),
                'location' => self::field('Localizacao', 'text', ['nullable', 'string', 'max:255']),
                'notes' => self::field('Observacoes', 'textarea', ['nullable', 'string', 'max:3000']),
            ]),
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? abort(404);
    }

    private static function party(string $model, string $label, string $icon, string $permission, string $status, string $document): array
    {
        return self::module($model, $label, $icon, $permission, ['name', $document, 'email', 'phone', 'city', $status], [
            'name' => self::field('Nome', 'text', ['required', 'string', 'max:255']),
            $document => self::field('CPF/CNPJ', 'text', ['nullable', 'string', 'max:30']),
            'type' => self::field('Tipo', 'text', ['nullable', 'string', 'max:50']),
            'email' => self::field('E-mail', 'email', ['nullable', 'email', 'max:255']),
            'phone' => self::field('Telefone', 'text', ['nullable', 'string', 'max:30']),
            'address' => self::field('Endereco', 'text', ['nullable', 'string', 'max:255']),
            'city' => self::field('Cidade', 'text', ['nullable', 'string', 'max:100']),
            'state' => self::field('UF', 'text', ['nullable', 'string', 'max:2']),
            $status => self::field('Ativo', 'toggle', ['boolean']),
            'notes' => self::field('Observacoes', 'textarea', ['nullable', 'string', 'max:3000']),
        ]);
    }

    private static function module(string $model, string $label, string $icon, string $permission, array $columns, array $fields, bool $writable = true): array
    {
        return compact('model', 'label', 'icon', 'permission', 'columns', 'fields', 'writable');
    }

    private static function field(string $label, string $type, array $rules, array $options = []): array
    {
        return compact('label', 'type', 'rules', 'options');
    }
}
