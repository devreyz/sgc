<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Models\AssociateReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;

class DeliveryProjectIntegrityService
{
    public function inspect(int $tenantId, SalesProject $project): array
    {
        $critical = [];
        $warning = [];
        $info = [];

        $parents = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->whereNull('parent_delivery_id')
            ->with(['associate.user', 'product', 'projectDemand.product', 'distributions.customer', 'distributions.associateReceipt'])
            ->get();

        $distributions = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->whereNotNull('parent_delivery_id')
            ->with(['associate.user', 'product', 'customer', 'parentDelivery', 'associateReceipt'])
            ->get();

        foreach ($parents as $parent) {
            $productName = $parent->product?->name ?? $parent->projectDemand?->product?->name ?? 'Produto';
            $associateName = $parent->associate?->display_name ?? 'Associado';
            $validChildren = $parent->distributions
                ->whereNotIn('status', [DeliveryStatus::REJECTED, DeliveryStatus::CANCELLED]);
            $distributed = (float) $validChildren->sum('quantity');
            $received = (float) $parent->quantity;

            if ($parent->status === DeliveryStatus::APPROVED && $distributed <= 0) {
                $warning[] = $this->item(
                    'Entrega sem distribuicao',
                    "{$associateName} entregou {$productName}, mas ainda nao ha destino distribuido.",
                    'Distribua a quantidade recebida ou mantenha como saldo operacional.',
                    $parent->id,
                    'open_distribution'
                );
            } elseif ($parent->status === DeliveryStatus::APPROVED && $distributed + 0.0005 < $received) {
                $warning[] = $this->item(
                    'Entrega parcialmente distribuida',
                    "{$productName}: " . number_format($distributed, 3, ',', '.') . ' de ' . number_format($received, 3, ',', '.') . ' distribuidos.',
                    'Comprovante parcial pode ser gerado somente com as distribuicoes existentes.',
                    $parent->id,
                    'open_distribution'
                );
            }

            if ($distributed > $received + 0.0005) {
                $critical[] = $this->item(
                    'Distribuicao maior que recebimento',
                    "{$productName}: " . number_format($distributed, 3, ',', '.') . ' distribuidos para ' . number_format($received, 3, ',', '.') . ' recebidos.',
                    'Reduza ou corrija as distribuicoes antes de gerar comprovantes.',
                    $parent->id,
                    'open_distribution'
                );
            }
        }

        foreach ($distributions as $distribution) {
            $label = '#' . $distribution->id . ' - ' . ($distribution->product?->name ?? 'Produto');

            if (! $distribution->parentDelivery) {
                $critical[] = $this->item(
                    'Distribuicao orfa',
                    "{$label} esta sem entrega-pai valida.",
                    'Exclua a distribuicao orfa se ela nao tiver efeito financeiro.',
                    null,
                    'delete_orphan_distribution',
                    $distribution->id
                );
            }

            if (! $distribution->customer_id) {
                $critical[] = $this->item(
                    'Distribuicao sem cliente',
                    "{$label} esta sem cliente/destino.",
                    'Edite a distribuicao e informe o cliente correto.',
                    $distribution->parent_delivery_id,
                    'edit_distribution',
                    $distribution->id
                );
            }

            if ((float) ($distribution->unit_price ?? 0) <= 0) {
                $critical[] = $this->item(
                    'Distribuicao sem preco',
                    "{$label} esta sem preco valido.",
                    'Edite a distribuicao para recalcular o preco do cliente.',
                    $distribution->parent_delivery_id,
                    'edit_distribution',
                    $distribution->id
                );
            }

            if ((float) $distribution->gross_value <= 0) {
                $critical[] = $this->item(
                    'Valor bruto zerado',
                    "{$label} esta com valor financeiro zerado.",
                    'Edite a distribuicao para corrigir quantidade e preco.',
                    $distribution->parent_delivery_id,
                    'edit_distribution',
                    $distribution->id
                );
            }

            if (
                $distribution->parentDelivery
                && (
                    (int) $distribution->parentDelivery->associate_id !== (int) $distribution->associate_id
                    || (int) $distribution->parentDelivery->sales_project_id !== (int) $distribution->sales_project_id
                )
            ) {
                $critical[] = $this->item(
                    'Vinculo incompativel',
                    "{$label} nao bate com associado ou projeto da entrega-pai.",
                    'Revise a distribuicao e a entrega-pai antes de seguir.',
                    $distribution->parent_delivery_id,
                    'open_distribution',
                    $distribution->id
                );
            }

            if ($distribution->associate_receipt_id && ! $distribution->associateReceipt) {
                $critical[] = $this->item(
                    'Comprovante inexistente',
                    "{$label} aponta para um comprovante que nao existe.",
                    'Desvincule o comprovante inexistente para a distribuicao voltar a ficar disponivel.',
                    $distribution->parent_delivery_id,
                    'detach_missing_associate_receipt',
                    $distribution->id
                );
            }
        }

        $pendingReceipt = $distributions
            ->where('status', DeliveryStatus::APPROVED)
            ->where('paid', false)
            ->filter(fn ($delivery) => $delivery->billing_status !== BillingStatus::PAID && ! $delivery->associate_receipt_id);

        $pendingReceipt->groupBy('associate_id')->each(function ($items) use (&$warning): void {
            $associate = $items->first()?->associate;
            $associateName = $associate?->display_name ?? 'Associado';
            $warning[] = $this->item(
                'Distribuicoes sem comprovante',
                $associateName.' possui '.$items->count().' distribuicao(oes) aprovadas aguardando comprovante.',
                'Abra o produtor e inclua as distribuicoes pendentes em um comprovante.',
                $items->first()?->parent_delivery_id,
                'open_producers',
                null,
                (int) $items->first()->associate_id,
                $associateName,
            );
        });

        $receiptAssociateIds = AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->pluck('associate_id')
            ->unique();

        $associatesWithPendingAfterReceipt = $pendingReceipt
            ->whereIn('associate_id', $receiptAssociateIds)
            ->pluck('associate_id')
            ->unique()
            ->count();

        if ($associatesWithPendingAfterReceipt > 0) {
            $warning[] = $this->item(
                'Novas distribuicoes apos comprovante',
                "{$associatesWithPendingAfterReceipt} associado(s) ja possuem comprovante e tambem novas distribuicoes pendentes.",
                'Gere comprovantes adicionais somente com as novas distribuicoes.',
                null,
                'open_producers'
            );
        }

        if (empty($critical) && empty($warning)) {
            $info[] = $this->item(
                'Projeto sem pendencias criticas',
                'Nenhuma inconsistencia financeira encontrada neste momento.',
                'Continue operando normalmente.'
            );
        }

        return [
            'critical' => $critical,
            'warning' => $warning,
            'info' => $info,
            'counts' => [
                'critical' => count($critical),
                'warning' => count($warning),
                'info' => count($info),
            ],
        ];
    }

    private function item(
        string $title,
        string $message,
        string $action,
        ?int $deliveryId = null,
        ?string $actionKey = null,
        ?int $distributionId = null,
        ?int $associateId = null,
        ?string $associateName = null,
    ): array {
        return compact('title', 'message', 'action', 'deliveryId', 'actionKey', 'distributionId', 'associateId', 'associateName');
    }
}
