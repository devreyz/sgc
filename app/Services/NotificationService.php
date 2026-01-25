<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class NotificationService
{
    /**
     * Notify about a new production delivery.
     * 
     * @param \App\Models\ProductionDelivery $delivery
     * @return void
     */
    public function notifyDelivery(\App\Models\ProductionDelivery $delivery): void
    {
        // Get users with Tesoureiro or Presidente roles (only existing roles)
        $recipients = $this->getUsersByRoles(['tesoureiro', 'presidente', 'super_admin']);

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Nova Entrega de Produção')
                ->icon('heroicon-o-truck')
                ->iconColor('success')
                ->body(sprintf(
                    '%s entregou %.2f %s de %s',
                    $delivery->associate->user->name,
                    $delivery->quantity,
                    $delivery->product->unit,
                    $delivery->product->name
                ))
                ->actions([
                    Action::make('view')
                        ->label('Ver Detalhes')
                        ->url($this->filamentResourceUrl('production-deliveries', 'edit', $delivery))
                        ->button(),
                ])
                ->sendToDatabase($recipient);
        }
    }

    /**
     * Notify about low stock.
     * 
     * @param \App\Models\Product $product
     * @return void
     */
    public function notifyLowStock(\App\Models\Product $product): void
    {
        // Get users with Comprador role (only existing roles)
        $recipients = $this->getUsersByRoles(['comprador', 'admin', 'super_admin']);

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Estoque Baixo')
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('warning')
                ->body(sprintf(
                    'O produto %s está com estoque baixo: %.2f %s (mínimo: %.2f %s)',
                    $product->name,
                    $product->current_stock,
                    $product->unit,
                    $product->min_stock,
                    $product->unit
                ))
                ->actions([
                    Action::make('view')
                        ->label('Ver Produto')
                        ->url($this->filamentResourceUrl('products', 'edit', $product))
                        ->button(),
                ])
                ->sendToDatabase($recipient);
        }
    }

    /**
     * Notify about DAP/CAF expiring.
     * 
     * @param Associate $associate
     * @return void
     */
    public function notifyDapCafExpiring(Associate $associate): void
    {
        // Get admins (only existing roles)
        $recipients = $this->getUsersByRoles(['admin', 'super_admin']);

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('DAP/CAF Vencendo')
                ->icon('heroicon-o-document-text')
                ->iconColor('warning')
                ->body(sprintf(
                    'O DAP/CAF de %s vence em %s',
                    $associate->user->name,
                    $associate->dap_caf_expiry->format('d/m/Y')
                ))
                ->actions([
                    Action::make('view')
                        ->label('Ver Associado')
                        ->url($this->filamentResourceUrl('associates', 'edit', $associate))
                        ->button(),
                ])
                ->sendToDatabase($recipient);
        }
    }

    /**
     * Notify about overdue expense.
     * 
     * @param \App\Models\Expense $expense
     * @return void
     */
    public function notifyOverdueExpense(\App\Models\Expense $expense): void
    {
        $recipients = $this->getUsersByRoles(['financeiro', 'tesoureiro', 'super_admin']);

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Despesa Vencida')
                ->icon('heroicon-o-banknotes')
                ->iconColor('danger')
                ->body(sprintf(
                    'A despesa "%s" venceu em %s - Valor: R$ %s',
                    $expense->description,
                    $expense->due_date->format('d/m/Y'),
                    number_format($expense->amount, 2, ',', '.')
                ))
                ->actions([
                    Action::make('view')
                        ->label('Ver Despesa')
                        ->url($this->filamentResourceUrl('expenses', 'edit', $expense))
                        ->button(),
                ])
                ->sendToDatabase($recipient);
        }
    }

    /**
     * Notify associate about credit in ledger.
     * 
     * @param \App\Models\AssociateLedger $entry
     * @return void
     */
    public function notifyAssociateLedgerCredit(\App\Models\AssociateLedger $entry): void
    {
        $user = $entry->associate->user;

        Notification::make()
            ->title('Crédito em Conta')
            ->icon('heroicon-o-arrow-up-circle')
            ->iconColor('success')
            ->body(sprintf(
                'Você recebeu um crédito de R$ %s. %s',
                number_format($entry->amount, 2, ',', '.'),
                $entry->description
            ))
            ->sendToDatabase($user);
    }

    /**
     * Notify associate about debit in ledger.
     * 
     * @param \App\Models\AssociateLedger $entry
     * @return void
     */
    public function notifyAssociateLedgerDebit(\App\Models\AssociateLedger $entry): void
    {
        $user = $entry->associate->user;

        Notification::make()
            ->title('Débito em Conta')
            ->icon('heroicon-o-arrow-down-circle')
            ->iconColor('danger')
            ->body(sprintf(
                'Foi debitado R$ %s da sua conta. %s',
                number_format($entry->amount, 2, ',', '.'),
                $entry->description
            ))
            ->sendToDatabase($user);
    }

        /**
         * Safely get users by role names without throwing if a role doesn't exist.
         *
         * @param array $roles
         * @return \Illuminate\Support\Collection
         */
        private function getUsersByRoles(array $roles)
        {
            $existing = Role::whereIn('name', $roles)->pluck('name')->toArray();

            if (empty($existing)) {
                return collect();
            }

            return User::role($existing)->get();
        }

        /**
         * Build a Filament resource URL safely. Falls back to a reasonable admin path when
         * named route is missing.
         *
         * @param string $resourceSlug
         * @param string $action ('edit'|'view' etc.)
         * @param \Illuminate\Database\Eloquent\Model $record
         * @return string
         */
        private function filamentResourceUrl(string $resourceSlug, string $action, $record): string
        {
            try {
                return route(sprintf('filament.admin.resources.%s.%s', $resourceSlug, $action), $record);
            } catch (\Throwable $e) {
                $adminPath = config('filament.path', 'admin');
                $id = $record->getKey();

                if ($action === 'edit') {
                    return url("/{$adminPath}/resources/{$resourceSlug}/{$id}/edit");
                }

                return url("/{$adminPath}/resources/{$resourceSlug}/{$id}");
            }
        }
}
