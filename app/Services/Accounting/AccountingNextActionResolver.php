<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;

class AccountingNextActionResolver
{
    /**
     * @return array{state: string, label: string, tone: string, next_action: string, next_action_key: string}
     */
    public function resolve(CustomerReceiptStatus|string|null $status, int $criticalIssues = 0, string $authorizationState = 'legacy_unsubmitted'): array
    {
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
                'authorized' => ['state' => 'authorized', 'label' => 'Autorizada', 'tone' => 'success', 'next_action' => 'Aguardar a fase fiscal', 'next_action_key' => 'await_fiscal'],
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
}
