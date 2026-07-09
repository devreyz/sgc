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
            $associateName = $parent->associate?->user?->name ?? 'Associado';
            $validChildren = $parent->distributions
                ->whereNotIn('status', [DeliveryStatus::REJECTED, DeliveryStatus::CANCELLED]);
            $distributed = (float) $validChildren->sum('quantity');
            $received = (float) $parent->quantity;

            if ($parent->status === DeliveryStatus::APPROVED && $distributed <= 0) {
                $warning[] = $this->item(
                    'Entrega sem distribuicao',
                    "{$associateName} entregou {$productName}, mas ainda nao ha destino distribuido.",
                    'Distribua a quantidade recebida ou mantenha como saldo operacional.',
                    $parent->id
                );
            } elseif ($parent->status === DeliveryStatus::APPROVED && $distributed + 0.0005 < $received) {
                $warning[] = $this->item(
                    'Entrega parcialmente distribuida',
                    "{$productName}: " . number_format($distributed, 3, ',', '.') . ' de ' . number_format($received, 3, ',', '.') . ' distribuidos.',
                    'Comprovante parcial pode ser gerado somente com as distribuicoes existentes.',
                    $parent->id
                );
            }

            if ($distributed > $received + 0.0005) {
                $critical[] = $this->item(
                    'Distribuicao maior que recebimento',
                    "{$productName}: " . number_format($distributed, 3, ',', '.') . ' distribuidos para ' . number_format($received, 3, ',', '.') . ' recebidos.',
                    'Reduza ou corrija as distribuicoes antes de gerar comprovantes.',
                    $parent->id
                );
            }
        }

        foreach ($distributions as $distribution) {
            $label = '#' . $distribution->id . ' - ' . ($distribution->product?->name ?? 'Produto');

            if (! $distribution->parentDelivery) {
                $critical[] = $this->item('Distribuicao orfa', "{$label} esta sem entrega-pai valida.", 'Recrie ou cancele a distribuicao.', $distribution->parent_delivery_id);
            }

            if (! $distribution->customer_id) {
                $critical[] = $this->item('Distribuicao sem cliente', "{$label} esta sem cliente/destino.", 'Edite a distribuicao e informe o cliente correto.', $distribution->parent_delivery_id);
            }

            if ((float) ($distribution->unit_price ?? 0) <= 0) {
                $critical[] = $this->item('Distribuicao sem preco', "{$label} esta sem preco valido.", 'Configure a tabela de precos e edite a distribuicao.', $distribution->parent_delivery_id);
            }

            if ((float) $distribution->gross_value <= 0) {
                $critical[] = $this->item('Valor bruto zerado', "{$label} esta com valor financeiro zerado.", 'Corrija quantidade/preco antes de gerar comprovante.', $distribution->parent_delivery_id);
            }

            if (
                $distribution->parentDelivery
                && (
                    (int) $distribution->parentDelivery->associate_id !== (int) $distribution->associate_id
                    || (int) $distribution->parentDelivery->sales_project_id !== (int) $distribution->sales_project_id
                )
            ) {
                $critical[] = $this->item('Vinculo incompatível', "{$label} nao bate com associado ou projeto da entrega-pai.", 'Corrija manualmente antes de seguir.', $distribution->parent_delivery_id);
            }

            if ($distribution->associate_receipt_id && ! $distribution->associateReceipt) {
                $critical[] = $this->item('Comprovante inexistente', "{$label} aponta para um comprovante que nao existe.", 'Desvincule ou gere novamente o comprovante.', $distribution->parent_delivery_id);
            }
        }

        $pendingReceipt = $distributions
            ->where('status', DeliveryStatus::APPROVED)
            ->where('paid', false)
            ->filter(fn ($d) => $d->billing_status !== BillingStatus::PAID && ! $d->associate_receipt_id);

        if ($pendingReceipt->isNotEmpty()) {
            $warning[] = $this->item(
                'Distribuicoes sem comprovante',
                $pendingReceipt->count() . ' distribuicao(oes) aprovadas ainda nao entraram em comprovante de produtor.',
                'Abra os comprovantes dos produtores e gere um comprovante parcial ou completo.'
            );
        }

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
                'Gere comprovantes adicionais somente com as novas distribuicoes.'
            );
        }

        if (empty($critical) && empty($warning)) {
            $info[] = $this->item('Projeto sem pendencias criticas', 'Nenhuma inconsistencia financeira encontrada neste momento.', 'Continue operando normalmente.');
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

    private function item(string $title, string $message, string $action, ?int $deliveryId = null): array
    {
        return compact('title', 'message', 'action', 'deliveryId');
    }
}
