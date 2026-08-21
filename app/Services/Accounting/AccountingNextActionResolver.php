<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;

class AccountingNextActionResolver
{
    /**
     * @return array{state: string, label: string, tone: string, next_action: string, next_action_key: string}
     */
    public function resolve(
        CustomerReceiptStatus|string|null $status,
        int $criticalIssues = 0,
        string $authorizationState = 'legacy_unsubmitted',
        ?array $fiscalGate = null,
    ): array {
        if ($criticalIssues > 0) {
            return [
                'state' => 'critical_inconsistency',
                'label' => 'Inconsistência crítica',
                'tone' => 'danger',
                'next_action' => 'Conferir a origem financeira',
                'next_action_key' => 'review_inconsistency',
            ];
        }

        $status = $status instanceof CustomerReceiptStatus ? $status : CustomerReceiptStatus::tryFrom((string) $status);

        if ($status !== CustomerReceiptStatus::DRAFT) {
            $authorization = match ($authorizationState) {
                'sent' => ['state' => 'awaiting_organization', 'label' => 'Aguardando organização', 'tone' => 'warning', 'next_action' => 'Aguardar a análise da organização', 'next_action_key' => 'await_organization'],
                'correction_requested' => ['state' => 'correction_requested', 'label' => 'Correção solicitada', 'tone' => 'danger', 'next_action' => 'Revisar a solicitação e enviar nova versão', 'next_action_key' => 'review_correction'],
                'authorized' => $this->authorizedState($fiscalGate),
                'invalidated' => ['state' => 'authorization_invalidated', 'label' => 'Autorização invalidada', 'tone' => 'danger', 'next_action' => 'Enviar nova versão para a organização', 'next_action_key' => 'resend_authorization'],
                default => null,
            };
            if ($authorization) {
                return $authorization;
            }
        }

        return match ($status) {
            CustomerReceiptStatus::DRAFT, null => [
                'state' => 'preparing',
                'label' => 'Em preparação',
                'tone' => 'neutral',
                'next_action' => 'Conferir e fechar a cobrança',
                'next_action_key' => 'review_draft',
            ],
            CustomerReceiptStatus::PENDING_PAYMENT => [
                'state' => 'ready_for_authorization',
                'label' => 'Pronta para autorização',
                'tone' => 'warning',
                'next_action' => 'Enviar à organização',
                'next_action_key' => 'send_authorization',
            ],
            CustomerReceiptStatus::PARTIALLY_PAID => [
                'state' => 'partially_received',
                'label' => 'Recebimento parcial',
                'tone' => 'info',
                'next_action' => 'Acompanhar o saldo restante',
                'next_action_key' => 'track_balance',
            ],
            CustomerReceiptStatus::PAID => [
                'state' => 'received',
                'label' => 'Recebido',
                'tone' => 'success',
                'next_action' => 'Consultar o dossiê',
                'next_action_key' => 'view_dossier',
            ],
        };
    }

    private function authorizedState(?array $fiscalGate): array
    {
        if ($fiscalGate === null) {
            return ['state' => 'authorized', 'label' => 'Autorizada', 'tone' => 'success', 'next_action' => 'Aguardar a fase fiscal', 'next_action_key' => 'await_fiscal'];
        }

        if ($fiscalGate['ready'] ?? false) {
            return ['state' => 'ready_for_fiscal', 'label' => 'Pronto para emissão', 'tone' => 'success', 'next_action' => 'Preparar emissão', 'next_action_key' => 'prepare_fiscal'];
        }

        $codes = collect($fiscalGate['blocks'] ?? [])->pluck('code');
        if ($codes->contains(fn (string $code): bool => str_starts_with($code, 'fiscal_profile_') || in_array($code, ['document_type_missing', 'fiscal_amount_source_missing'], true))) {
            return ['state' => 'fiscal_configuration_required', 'label' => 'Configuração fiscal necessária', 'tone' => 'warning', 'next_action' => 'Configurar emissão', 'next_action_key' => 'configure_fiscal'];
        }

        return ['state' => 'fiscal_blocked', 'label' => 'Bloqueado para faturamento', 'tone' => 'danger', 'next_action' => 'Corrigir inconsistência', 'next_action_key' => 'review_fiscal_block'];
    }
}
