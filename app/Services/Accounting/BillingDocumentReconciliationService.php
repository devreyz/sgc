<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;
use App\Enums\ReceiptStatus;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Services\TenantIdentityService;
use App\Support\FinancialAmount;
use Illuminate\Support\Collection;

/** Read-only advisory audit. Never feeds the issuance/payment blocking rules. */
final class BillingDocumentReconciliationService
{
    public function inspect(CustomerBillingReceipt $billing): array
    {
        $tenantId = (int) $billing->tenant_id;
        $projectIds = $billing->projectIds();
        $lines = collect($billing->documentLines());
        $savedIds = $this->ids($billing->delivery_ids);
        $lineIds = $this->ids($lines->pluck('distribution_ids')->flatten()->all());
        $current = ProductionDelivery::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)->where('billing_receipt_id', $billing->id)->get();
        $currentIds = $current->reject(fn ($row) => $row->trashed())->pluck('id');
        // Empty saved arrays are intentional, unlike legacy NULL snapshots.
        $billingIds = $billing->delivery_ids !== null ? $savedIds : ($lineIds->isNotEmpty() ? $lineIds : $currentIds);
        $scopeIds = $billingIds->merge($current->pluck('id'))->merge($lineIds)->unique()->values();
        $billingRows = $this->rows($tenantId, $scopeIds);
        $linkedMemberIds = $billingRows->pluck('associate_receipt_id')->filter()->unique();
        $members = AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->where(fn ($query) => $query->whereIn('sales_project_id', $projectIds)
                ->orWhereIn('id', $linkedMemberIds))
            ->get()->filter(function (AssociateReceipt $member) use ($scopeIds, $linkedMemberIds): bool {
                return $linkedMemberIds->contains($member->id)
                    || ($member->status !== ReceiptStatus::CANCELLED
                        && $this->ids($member->delivery_ids)->intersect($scopeIds)->isNotEmpty());
            })->values();
        $memberLive = ProductionDelivery::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)
            ->whereIn('associate_receipt_id', $members->reject(fn ($member) => $member->status === ReceiptStatus::CANCELLED)->pluck('id'))->get();
        $allIds = $scopeIds->merge($memberLive->pluck('id'))
            ->merge($members->reject(fn ($member) => $member->status === ReceiptStatus::CANCELLED)
                ->flatMap(fn ($member) => $this->ids($member->delivery_ids)))->unique()->values();
        $rows = $this->rows($tenantId, $allIds);
        $parents = ProductionDelivery::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)
            ->whereIn('id', $rows->pluck('parent_delivery_id')->filter()->unique())->get()->keyBy('id');
        $alerts = [];
        $add = function (string $code, string $message, string $solution, array $ids = [], string $level = 'warning') use (&$alerts): void {
            $alerts[] = compact('code', 'message', 'solution', 'ids', 'level');
        };
        if ($billingIds->isEmpty()) {
            $add('empty_billing', 'A folha não possui distribuições na seleção documental.', 'Confira a seleção de entregas no editor do faturamento.');
        }
        if (count($billing->delivery_ids ?? []) !== $savedIds->count()) {
            $add('duplicate_billing_selection', 'A seleção da folha contém referências repetidas ou inválidas.', 'Confira a seleção e salve novamente o faturamento pelo editor.');
        }
        if ($billing->status !== CustomerReceiptStatus::DRAFT && $billingIds->sort()->values()->all() !== $currentIds->sort()->values()->all()) {
            $add('billing_links_changed', 'Os vínculos atuais diferem da seleção salva na folha.', 'Confira inclusões e remoções e atualize o faturamento pelo fluxo de edição/reemissão.', $billingIds->diff($currentIds)->merge($currentIds->diff($billingIds))->values()->all());
        }
        if ($lineIds->isNotEmpty() && $lineIds->sort()->values()->all() !== $billingIds->sort()->values()->all()) {
            $add('billing_line_ids_changed', 'As linhas impressas e a seleção salva da folha contêm distribuições diferentes.', 'Confira a seleção e gere uma nova versão do faturamento.', $lineIds->diff($billingIds)->merge($billingIds->diff($lineIds))->values()->all());
        }

        $occurrences = [];
        $memberReport = [];
        $allMemberRows = collect();
        $memberStoredGross = '0';
        $memberStoredNet = '0';
        $hasGross = true;
        $names = app(TenantIdentityService::class)->namesForUsers($tenantId,
            $members->load(['associate' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId)
                ->select(['id', 'tenant_id', 'user_id', 'nickname'])])->pluck('associate.user_id')->filter());
        foreach ($members as $member) {
            $liveIds = $memberLive->where('associate_receipt_id', $member->id)
                ->reject(fn ($row) => $row->trashed())->pluck('id');
            $ids = $member->delivery_ids !== null ? $this->ids($member->delivery_ids) : $liveIds;
            $number = $member->receipt_label ?: str_pad((string) $member->receipt_number, 4, '0', STR_PAD_LEFT).'/'.$member->receipt_year;
            if ($member->status === ReceiptStatus::CANCELLED) {
                $add('cancelled_member_link', "Comprovante {$number} cancelado ainda possui vínculo com esta folha.", 'Revise o vínculo e use um comprovante ativo. O documento cancelado foi excluído das somas.');
                continue;
            }
            if (in_array($member->status, [ReceiptStatus::DRAFT, ReceiptStatus::OBSOLETE], true)) {
                $add('member_not_current', "Comprovante {$number}: ".$member->status->getLabel().'.', 'Confira as distribuições e emita/atualize o comprovante do membro antes de usar a via impressa.');
            }
            if ($member->delivery_ids === null) {
                $add('member_snapshot_unavailable', "Comprovante {$number} sem seleção histórica salva.", 'A comparação usa os vínculos atuais; confira a via impressa antes de atualizar o documento.', [], 'info');
            } elseif ($ids->sort()->values()->all() !== $liveIds->sort()->values()->all()) {
                $add('member_links_changed', "A seleção salva e os vínculos atuais do comprovante {$number} são diferentes.", 'Confira os itens removidos/adicionados e atualize o comprovante pelo fluxo autorizado.', $ids->diff($liveIds)->merge($liveIds->diff($ids))->values()->all());
            }
            if (count($member->delivery_ids ?? []) !== $this->ids($member->delivery_ids)->count()) {
                $add('duplicate_member_selection', "Comprovante {$number} contém referências repetidas ou inválidas.", 'Revise a seleção e atualize o comprovante pelo fluxo autorizado.');
            }
            foreach ($ids as $id) {
                $occurrences[$id] = ($occurrences[$id] ?? 0) + 1;
                $row = $rows->get($id);
                if ($row && ((int) $row->associate_id !== (int) $member->associate_id
                    || (int) $row->sales_project_id !== (int) $member->sales_project_id)) {
                    $add('member_context_mismatch', "Distribuição #{$id} não corresponde ao membro/projeto do comprovante {$number}.", 'Revise a distribuição e os vínculos dos comprovantes; não altere documentos pagos diretamente.', [$id]);
                }
            }
            $memberRows = $rows->only($ids->all());
            $allMemberRows = $allMemberRows->concat($memberRows->values());
            $extraIds = $ids->diff($billingIds)->values()->all();
            if ($extraIds !== []) {
                $add('member_only_distributions', "Comprovante {$number} reúne ".count($extraIds).' distribuição(ões) além desta folha — agrupamento permitido.', 'É permitido reunir várias folhas do mesmo cliente ou de clientes diferentes para pagar o membro integralmente. Este aviso é apenas informativo: confira a parcela correspondente a esta folha. Se o agrupamento estiver correto, nenhuma correção é necessária.', $extraIds, 'info');
            }
            $gross = $member->getAttribute('total_gross');
            if ($gross !== null && $member->getAttribute('total_fees') !== null
                && $this->differentMoney(bcsub((string) $gross, (string) $member->total_fees, 8), $member->total_net)) {
                $add('member_financial_totals_mismatch', "Os totais salvos do comprovante {$number} não fecham: bruto − taxas ≠ líquido.", 'Confira os descontos/acréscimos e atualize o comprovante pelo fluxo autorizado; preserve documentos pagos e seu histórico.');
            }
            $hasGross = $hasGross && $gross !== null;
            $memberStoredGross = bcadd($memberStoredGross, (string) ($gross ?? 0), 8);
            $memberStoredNet = bcadd($memberStoredNet, (string) ($member->total_net ?? 0), 8);
            if ($gross !== null && $ids->diff($memberRows->keys())->isEmpty()
                && $this->differentMoney($gross, $this->sumGross($memberRows))) {
                $add('member_gross_changed', "O bruto salvo do comprovante {$number} difere do bruto das suas distribuições atuais.", 'Confira quantidades e preços alterados. Atualize o comprovante somente pelo fluxo autorizado, preservando o histórico financeiro.', $ids->all());
            }
            $memberReport[] = [
                'id' => $member->id, 'number' => $number,
                'member' => $names[$member->associate?->user_id] ?? $member->associate?->nickname ?? 'Membro não identificado',
                'status' => $member->status?->getLabel() ?? 'Não informado',
                'distributions' => $ids->count(), 'in_billing' => $ids->intersect($billingIds)->count(),
                'outside_billing' => count($extraIds), 'quantities' => $this->unitTotals($memberRows),
                'gross' => $gross, 'net' => (string) ($member->total_net ?? 0),
            ];
        }

        $missing = $billingIds->reject(fn ($id) => isset($occurrences[$id]))->values()->all();
        if ($missing !== []) {
            $add('billing_only_distributions', count($missing).' distribuição(ões) da folha não aparecem em comprovantes ativos dos membros.', 'Use “Criar comprovantes dos membros” a partir deste faturamento ou revise os comprovantes existentes.', $missing);
        }
        $duplicates = array_keys(array_filter($occurrences, fn ($count) => $count > 1));
        if ($duplicates !== []) {
            $add('duplicate_member_distributions', 'Uma mesma distribuição consta na seleção salva de mais de um comprovante ativo.', 'Confira as vias e mantenha a distribuição em apenas um comprovante ativo, usando o fluxo seguro de correção/cancelamento.', $duplicates);
        }
        foreach ($allIds as $id) {
            $row = $rows->get($id);
            if (! $row) {
                $add('missing_distribution', "Distribuição #{$id} referenciada em documento não foi encontrada neste contexto (possível entrega fantasma).", 'Confira o histórico. Restaure o registro correto, se aplicável, ou corrija a seleção e reemita o documento; não crie uma entrega duplicada.', [$id]);
                continue;
            }
            if ($row->trashed()) {
                $add('deleted_distribution', "Distribuição #{$id} foi excluída, mas ainda consta nos documentos/vínculos.", 'Confira a exclusão e restaure o registro ou atualize os documentos pelo fluxo autorizado.', [$id]);
            }
            if ($scopeIds->contains($id) && $row->associate_receipt_id && ! $members->contains('id', $row->associate_receipt_id)) {
                $add('missing_member_document', "Distribuição #{$id} aponta para um comprovante de membro não encontrado neste contexto.", 'Confira o histórico e corrija o vínculo usando o comprovante correto, sem duplicar a entrega.', [$id]);
            }
            $parent = $parents->get($row->parent_delivery_id);
            if (! $row->parent_delivery_id || ! $parent || $parent->trashed() || $parent->parent_delivery_id
                || (int) $parent->sales_project_id !== (int) $row->sales_project_id
                || (int) $parent->associate_id !== (int) $row->associate_id
                || (int) $parent->product_id !== (int) $row->product_id) {
                $add('invalid_parent', "Distribuição #{$id} sem entrega de origem válida.", 'Confira e recupere a entrega de origem ou corrija o vínculo no cadastro de entregas.', [$id]);
            }
            if (! in_array((int) $row->sales_project_id, $projectIds, true) && $scopeIds->contains($id)) {
                $add('invalid_project', "Distribuição #{$id} pertence a outro projeto.", 'Revise o contexto do faturamento e a seleção de distribuições.', [$id]);
            }
            if ($scopeIds->contains($id) && ($billing->customer_id
                    ? (int) $row->customer_id !== (int) $billing->customer_id
                    : (! $row->customer || (int) $row->customer->organization_id !== (int) $billing->organization_id))) {
                $add('invalid_recipient', "Distribuição #{$id} não corresponde ao destinatário da folha.", 'Confira o cliente/organização da distribuição e a seleção deste faturamento.', [$id]);
            }
            if (bccomp((string) $row->quantity, '0', 8) <= 0 || bccomp((string) $row->unit_price, '0', 8) <= 0) {
                $add('invalid_amount', "Distribuição #{$id} com quantidade ou preço não positivo.", 'Confira a entrega e a tabela de preços antes de atualizar os documentos.', [$id]);
            }
            if ($this->differentMoney($row->getRawOriginal('gross_value'), bcmul((string) $row->quantity, (string) $row->unit_price, 8))) {
                $add('distribution_gross_mismatch', "O bruto registrado da distribuição #{$id} difere de quantidade × preço.", 'Confira a edição da entrega e recalcule os valores pelo fluxo do sistema antes de atualizar os documentos.', [$id]);
            }
        }

        $allocated = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereIn('parent_delivery_id', $parents->keys())->selectRaw('parent_delivery_id, SUM(quantity) AS allocated_quantity')
            ->groupBy('parent_delivery_id')->get();
        foreach ($allocated as $allocation) {
            $parent = $parents->get($allocation->parent_delivery_id);
            if ($parent && bccomp((string) $allocation->allocated_quantity, (string) $parent->quantity, 4) > 0) {
                $add('overallocated_parent', "Entrega de origem #{$parent->id} possui mais quantidade distribuída do que recebida.", 'Revise todas as distribuições desta entrega e corrija excessos/duplicidades antes de atualizar os documentos.', $rows->where('parent_delivery_id', $parent->id)->keys()->all());
            }
        }

        $selected = $rows->only($billingIds->all());
        $covered = $selected->filter(fn ($row) => isset($occurrences[$row->id]));
        $products = $this->productReport($lines, $selected, $covered);
        if ($this->differentMoney(bcsub((string) ($billing->total_gross ?? 0), (string) ($billing->total_fees ?? 0), 8), $billing->total_net)) {
            $add('billing_financial_totals_mismatch', 'Os totais salvos da folha não fecham: bruto − taxas ≠ líquido.', 'Confira os descontos/acréscimos e atualize a folha pelo fluxo autorizado.');
        }
        foreach ($products as $product) {
            if ($lines->isNotEmpty() && (bccomp($product['sheet_quantity'], $product['current_quantity'], 8) !== 0
                || $this->differentMoney($product['sheet_gross'], $product['current_gross']))) {
                $add('product_totals_changed', 'Quantidade/bruto documental diverge dos registros atuais: '.$product['product'].' ('.$product['unit'].').', 'Confira quantidade, preço e produto nas distribuições. Depois atualize o faturamento, se a alteração estiver correta.');
            }
        }
        if ($lines->isEmpty()) {
            $add('billing_lines_unavailable', 'A folha não possui linhas documentais congeladas para comparar quantidades históricas.', 'Os relatórios usam as distribuições atuais. Confira a via emitida; em rascunhos, salve a seleção para atualizar a prévia.', [], 'info');
            if ($this->differentMoney($billing->total_gross, $this->sumGross($selected))) {
                $add('billing_gross_changed', 'O bruto salvo da folha difere do bruto dos registros atuais.', 'Confira quantidades e preços e atualize a folha pelo fluxo autorizado.');
            }
        } else {
            $lineGross = $lines->reduce(fn ($sum, $line) => bcadd($sum, (string) ($line['document_gross'] ?? bcmul((string) ($line['quantity'] ?? 0), (string) ($line['unit_price'] ?? 0), 8)), 8), '0');
            $lineNet = $lines->reduce(fn ($sum, $line) => bcadd($sum, (string) ($line['document_amount'] ?? 0), 8), '0');
            if ($this->differentMoney($lineGross, $billing->total_gross) || $this->differentMoney($lineNet, $billing->total_net)) {
                $add('billing_totals_mismatch', 'A soma das linhas documentais difere dos totais salvos no faturamento.', 'Confira o snapshot e atualize o faturamento pelo fluxo de edição/reemissão.');
            }
        }

        $units = collect($products)->groupBy(fn ($item) => mb_strtolower($item['unit']))->map(function ($group) {
            $result = ['unit' => $group->first()['unit']];
            foreach (['sheet_quantity', 'current_quantity', 'covered_quantity'] as $key) {
                $result[$key] = $group->reduce(fn ($sum, $item) => bcadd($sum, $item[$key], 8), '0');
            }
            $result['uncovered_quantity'] = bcsub($result['current_quantity'], $result['covered_quantity'], 8);
            return $result;
        })->values()->all();

        return [
            'advisory_only' => true, 'checked_at' => now()->format('d/m/Y H:i:s'),
            'sheet_quantities_available' => $lines->isNotEmpty(),
            'warning_count' => collect($alerts)->where('level', 'warning')->count(),
            'info_count' => collect($alerts)->where('level', 'info')->count(),
            'alerts' => $alerts, 'units' => $units, 'products' => $products, 'members' => $memberReport,
            'summary' => [
                'distributions' => $billingIds->count(), 'covered' => $billingIds->count() - count($missing),
                'uncovered' => count($missing), 'member_documents' => count($memberReport),
                'sheet_gross' => (string) ($billing->total_gross ?? 0), 'sheet_net' => (string) ($billing->total_net ?? 0),
                'current_gross' => $this->sumGross($selected), 'covered_gross' => $this->sumGross($covered),
                'all_members_gross' => $hasGross ? $memberStoredGross : null, 'all_members_net' => $memberStoredNet,
                'all_members_quantities' => $this->unitTotals($allMemberRows),
            ],
            'limitations' => [
                'Quantidades dos comprovantes dos membros são reconstruídas pelas distribuições da seleção salva; os documentos antigos não congelam quantidade/preço individual. Igualdade atual não certifica a via histórica impressa.',
                'Brutos são comparáveis no mesmo conjunto de distribuições. Taxas e líquidos do cliente e dos membros podem ser diferentes por regra contratual; diferença de líquido, sozinha, não indica erro.',
                'Um comprovante do membro pode reunir várias folhas/períodos, do mesmo cliente ou de clientes diferentes, para pagamento integral. Isso é permitido e não gera bloqueio; os totais integrais são apresentados separadamente da parcela desta folha.',
                'Valores monetários usam decimais e arredondamento em centavos; diferenças de até R$ 0,01 são toleradas. Anexos PDF externos não são lidos por OCR.',
            ],
        ];
    }

    private function ids(mixed $ids): Collection
    {
        return collect($ids)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values();
    }

    private function rows(int $tenantId, Collection $ids): Collection
    {
        return ProductionDelivery::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)->with([
                'product' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
                'customer' => fn ($query) => $query->withoutGlobalScopes()->where('tenant_id', $tenantId),
            ])
            ->get()->toBase()->keyBy('id');
    }

    private function sumGross(Collection $rows): string
    {
        return $rows->reduce(fn ($sum, $row) => bcadd($sum, bcmul((string) $row->quantity, (string) $row->unit_price, 8), 8), '0');
    }

    private function differentMoney(mixed $a, mixed $b): bool
    {
        $difference = bcsub(FinancialAmount::cents($a), FinancialAmount::cents($b), 2);
        return bccomp($difference, '0.01', 2) > 0 || bccomp($difference, '-0.01', 2) < 0;
    }

    private function unitTotals(Collection $rows): array
    {
        return $rows->groupBy(fn ($row) => mb_strtolower(trim($row->product?->unit ?: 'un')))->map(fn ($group) => [
            'unit' => trim($group->first()->product?->unit ?: 'un'),
            'quantity' => $group->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->quantity, 8), '0'),
        ])->values()->all();
    }

    private function productReport(Collection $lines, Collection $selected, Collection $covered): array
    {
        $report = [];
        $entry = function ($projectId, $productId, $unit, $name) use (&$report): string {
            $key = $projectId.'|'.$productId.'|'.mb_strtolower(trim($unit));
            $report[$key] ??= ['project_id' => (int) $projectId, 'product' => $name, 'unit' => trim($unit),
                'sheet_quantity' => '0', 'current_quantity' => '0', 'covered_quantity' => '0',
                'sheet_gross' => '0', 'current_gross' => '0', 'covered_gross' => '0'];
            return $key;
        };
        foreach ($lines as $line) {
            $key = $entry($line['project_id'] ?? 0, $line['product_id'] ?? 0, $line['unit'] ?? 'un', $line['product'] ?? 'Não identificado');
            $report[$key]['sheet_quantity'] = bcadd($report[$key]['sheet_quantity'], (string) ($line['quantity'] ?? 0), 8);
            $report[$key]['sheet_gross'] = bcadd($report[$key]['sheet_gross'], (string) ($line['raw_amount'] ?? bcmul((string) ($line['quantity'] ?? 0), (string) ($line['unit_price'] ?? 0), 8)), 8);
        }
        foreach ($selected as $row) {
            $key = $entry($row->sales_project_id, $row->product_id, $row->product?->unit ?: 'un', $row->product?->name ?: 'Produto #'.$row->product_id);
            $gross = bcmul((string) $row->quantity, (string) $row->unit_price, 8);
            $report[$key]['current_quantity'] = bcadd($report[$key]['current_quantity'], (string) $row->quantity, 8);
            $report[$key]['current_gross'] = bcadd($report[$key]['current_gross'], $gross, 8);
            if ($covered->has($row->id)) {
                $report[$key]['covered_quantity'] = bcadd($report[$key]['covered_quantity'], (string) $row->quantity, 8);
                $report[$key]['covered_gross'] = bcadd($report[$key]['covered_gross'], $gross, 8);
            }
        }
        return array_values($report);
    }
}
