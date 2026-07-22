<?php

namespace App\Support;

class NotificationEventCatalog
{
    public const PRIORITIES = ['info', 'normal', 'high', 'critical'];

    public static function all(): array
    {
        return [
            'delivery.registered' => self::event('Entrega registrada', 'Entregas', 'Nova entrada fisica registrada.', ['registrador_entregas', 'admin'], false, 'normal'),
            'distribution.changed' => self::event('Distribuicao alterada', 'Entregas', 'Registro interno de distribuicoes editaveis.', ['registrador_entregas', 'admin'], false, 'info', false),
            'receipt.generated' => self::event('Comprovante gerado', 'Comprovantes', 'Um comprovante do associado foi gerado ou regenerado.', ['associado', 'registrador_entregas', 'admin'], true, 'high'),
            'receipt.obsolete' => self::event('Comprovante obsoleto', 'Comprovantes', 'Um comprovante precisa ser conferido e regenerado.', ['registrador_entregas', 'financeiro', 'admin'], true, 'high'),
            'ledger.credit' => self::event('Credito registrado', 'Financeiro', 'Credito lancado para o associado.', ['associado'], true, 'high'),
            'ledger.debit' => self::event('Debito registrado', 'Financeiro', 'Debito lancado para o associado.', ['associado'], true, 'high'),
            'stock.low' => self::event('Estoque baixo', 'Estoque', 'Produto atingiu o estoque minimo.', ['comprador', 'admin'], true, 'normal'),
            'associate.document_expiring' => self::event('Documento a vencer', 'Associados', 'DAP ou CAF proximo do vencimento.', ['admin'], true, 'normal'),
            'expense.overdue' => self::event('Despesa vencida', 'Financeiro', 'Despesa ultrapassou a data de vencimento.', ['financeiro', 'tesoureiro', 'admin'], true, 'high'),
            'associate.limit_updated' => self::event('Limite atualizado', 'Projetos', 'Limite de participacao ou produto do associado alterado.', ['associado', 'registrador_entregas'], true, 'normal'),
            'buyer_request.created' => self::event('Solicitacao recebida', 'Compradores', 'Nova solicitacao de uma organizacao compradora.', ['registrador_entregas', 'admin'], true, 'normal'),
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function roles(): array
    {
        return [
            'admin' => 'Administradores',
            'financeiro' => 'Financeiro',
            'tesoureiro' => 'Tesouraria',
            'registrador_entregas' => 'Registradores de entregas',
            'associado' => 'Associados',
            'comprador' => 'Compradores',
        ];
    }

    private static function event(
        string $label,
        string $group,
        string $description,
        array $roles,
        bool $pushDefault,
        string $priority,
        bool $pushAllowed = true,
    ): array {
        return compact('label', 'group', 'description', 'roles', 'pushDefault', 'priority', 'pushAllowed') + [
            'databaseDefault' => true,
        ];
    }
}
