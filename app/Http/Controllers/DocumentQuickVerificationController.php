<?php

namespace App\Http\Controllers;

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\FinancialDocumentIdentity;
use App\Models\ProductionDelivery;
use App\Models\Tenant;
use App\Services\FinancialIntegrityAuditService;
use App\Services\TenantIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentQuickVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant($request);
        $portal = str_starts_with((string) $request->route()?->getName(), 'finance.') ? 'finance' : 'accounting';
        $this->authorizePortal($request, $portal);

        return view('documents.quick-verification', compact('tenant', 'portal'));
    }

    public function verify(Request $request, FinancialIntegrityAuditService $audit, TenantIdentityService $names): JsonResponse
    {
        $tenant = $this->tenant($request);
        $portal = str_starts_with((string) $request->route()?->getName(), 'finance.') ? 'finance' : 'accounting';
        $this->authorizePortal($request, $portal);
        $data = $request->validate(['code' => ['required', 'string', 'max:500']]);
        $token = $this->token((string) $data['code']);

        $identity = FinancialDocumentIdentity::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($token): void {
                $query->where('public_id', mb_strtolower($token))->orWhere('reference_code', mb_strtoupper($token));
            })->latest('revision')->latest('id')->first();

        if (! $identity) {
            return response()->json([
                'ok' => false,
                'verdict' => 'not_found',
                'headline' => 'Documento não reconhecido',
                'message' => 'O QR Code não pertence a um documento desta organização ou utiliza uma identificação antiga desconhecida.',
                'issues' => [['severity' => 'critical', 'message' => 'Identidade documental não encontrada neste ambiente.']],
            ], 404, ['Cache-Control' => 'no-store, private']);
        }

        $document = $identity->documentable()->withoutGlobalScopes()->first();
        $currentIdentity = FinancialDocumentIdentity::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('documentable_type', $identity->documentable_type)
            ->where('documentable_id', $identity->documentable_id)
            ->whereNull('invalidated_at')->latest('revision')->latest('id')->first();
        if (! $document) {
            return $this->result($identity, null, [], [[
                'severity' => 'critical', 'message' => 'A identidade existe, mas o documento relacionado não foi encontrado.',
            ]], $currentIdentity);
        }

        if ($document instanceof AssociateReceipt) {
            $document->loadMissing(['associate.user', 'project']);
            $distributions = ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $tenant->id)->where('associate_receipt_id', $document->id)
                ->with(['product:id,name,unit', 'customer:id,name,trade_name'])
                ->orderBy('delivery_date')->orderBy('id')->get();
            $auditResult = $audit->audit($tenant->id, (int) $document->sales_project_id);
            $distributionIds = $distributions->pluck('id')->map(fn ($id): int => (int) $id);
            $issues = collect($auditResult['issues'])->filter(fn (array $issue): bool =>
                ($issue['record_type'] === 'associate_receipt' && (int) $issue['record_id'] === (int) $document->id)
                || ($issue['record_type'] === 'production_delivery' && $distributionIds->contains((int) $issue['record_id']))
            )->map(fn (array $issue): array => ['severity' => $issue['severity'], 'message' => $issue['message'], 'code' => $issue['code']])->values()->all();

            $summary = [
                'type' => 'Comprovante do associado', 'number' => $document->formatted_number,
                'party' => $names->displayNameForAssociate($document->associate),
                'project' => $document->project?->title ?? 'Projeto não identificado',
                'status' => $document->status?->getLabel() ?? 'Rascunho',
                'issued_at' => $document->issued_at?->format('d/m/Y'),
                'total' => (float) ($document->total_net ?? 0),
                'distribution_count' => $distributions->count(),
            ];
            $items = $distributions->map(fn (ProductionDelivery $row): array => [
                'id' => (int) $row->id, 'date' => $row->delivery_date?->format('d/m/Y'),
                'product' => $row->product?->name ?? 'Produto não identificado',
                'quantity' => (string) $row->quantity, 'unit' => $row->product?->unit ?: 'un',
                'recipient' => $row->customer?->trade_name ?: $row->customer?->name ?: 'Cliente não identificado',
                'removed' => $row->deleted_at !== null,
            ])->all();

            if ($document->status?->value === 'obsolete') {
                array_unshift($issues, ['severity' => 'critical', 'message' => 'Este comprovante está obsoleto e não deve ser aceito como versão vigente.']);
            }

            $printUrl = $document->status?->value !== 'obsolete'
                ? route('delivery.projects.receipt-reprint', ['tenant' => $tenant->slug, 'project' => $document->sales_project_id, 'receipt' => $document->id, 'preview' => 1])
                : null;

            return $this->result($identity, $summary, $items, $issues, $currentIdentity, $printUrl);
        }

        if ($document instanceof CustomerBillingReceipt) {
            $document->loadMissing(['project', 'customer', 'organization']);
            $distributions = ProductionDelivery::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $tenant->id)->where('billing_receipt_id', $document->id)
                ->with(['product:id,name,unit'])->orderBy('delivery_date')->orderBy('id')->get();
            $auditResult = $audit->audit($tenant->id, (int) $document->sales_project_id);
            $distributionIds = $distributions->pluck('id')->map(fn ($id): int => (int) $id);
            $issues = collect($auditResult['issues'])->filter(fn (array $issue): bool =>
                ($issue['record_type'] === 'customer_billing_receipt' && (int) $issue['record_id'] === (int) $document->id)
                || ($issue['record_type'] === 'production_delivery' && $distributionIds->contains((int) $issue['record_id']))
            )->map(fn (array $issue): array => ['severity' => $issue['severity'], 'message' => $issue['message'], 'code' => $issue['code']])->values()->all();
            $summary = [
                'type' => 'Faturamento do cliente', 'number' => $document->formatted_number,
                'party' => $document->recipient_name, 'project' => $document->project?->title ?? 'Múltiplos projetos',
                'status' => $document->status?->getLabel() ?? 'Rascunho', 'issued_at' => $document->issued_at?->format('d/m/Y'),
                'total' => (float) ($document->total_net ?? 0), 'distribution_count' => $distributions->count(),
            ];
            $items = $distributions->map(fn (ProductionDelivery $row): array => [
                'id' => (int) $row->id, 'date' => $row->delivery_date?->format('d/m/Y'),
                'product' => $row->product?->name ?? 'Produto não identificado',
                'quantity' => (string) $row->quantity, 'unit' => $row->product?->unit ?: 'un',
                'recipient' => $document->recipient_name, 'removed' => $row->deleted_at !== null,
            ])->all();

            return $this->result($identity, $summary, $items, $issues, $currentIdentity);
        }

        $genericStatus = $document->status ?? null;
        $genericStatusLabel = is_object($genericStatus) && method_exists($genericStatus, 'getLabel')
            ? $genericStatus->getLabel()
            : (string) (is_object($genericStatus) ? ($genericStatus->value ?? 'Emitido') : ($genericStatus ?? 'Emitido'));

        return $this->result($identity, [
            'type' => class_basename($document), 'number' => $document->formatted_number ?? $identity->reference_code,
            'party' => $document->payer_name ?? 'Documento financeiro', 'project' => null,
            'status' => $genericStatusLabel,
            'issued_at' => ($document->issued_at ?? $document->received_on ?? null)?->format('d/m/Y'),
            'total' => (float) ($document->total_net ?? $document->total_amount ?? 0), 'distribution_count' => 0,
        ], [], [], $currentIdentity);
    }

    private function result(FinancialDocumentIdentity $identity, ?array $summary, array $items, array $issues, ?FinancialDocumentIdentity $currentIdentity = null, ?string $printUrl = null): JsonResponse
    {
        if ($identity->invalidated_at) {
            array_unshift($issues, ['severity' => 'critical', 'message' => 'Esta via foi invalidada'.($identity->invalidation_reason ? ': '.$identity->invalidation_reason : '.').'']);
        }
        $isOldVersion = $currentIdentity && (int) $currentIdentity->id !== (int) $identity->id;
        if ($isOldVersion) {
            array_unshift($issues, ['severity' => 'critical', 'message' => 'O QR Code pertence a uma versão antiga. Utilize a versão vigente indicada abaixo.']);
        }
        if ($summary && ($summary['distribution_count'] ?? 0) === 0 && in_array($summary['type'], ['Comprovante do associado', 'Faturamento do cliente'], true)) {
            $issues[] = ['severity' => 'warning', 'message' => 'Nenhuma distribuição ativa está vinculada ao documento.'];
        }
        $critical = collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'critical');
        $warning = collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'warning');
        $verdict = $critical ? 'invalid' : ($warning ? 'attention' : 'valid');

        return response()->json([
            'ok' => ! $critical, 'verdict' => $verdict,
            'headline' => match ($verdict) { 'valid' => 'Documento íntegro', 'attention' => 'Documento exige atenção', default => 'Documento inválido' },
            'message' => match ($verdict) { 'valid' => 'Identidade, situação e vínculos conferidos sem falhas conhecidas.', 'attention' => 'A leitura é autêntica, mas há pontos que precisam de conferência.', default => 'Não utilize este documento antes de corrigir ou confirmar as falhas.' },
            'identity' => [
                'reference' => $identity->reference_code, 'revision' => $identity->revision,
                'invalidated_at' => $identity->invalidated_at?->format('d/m/Y H:i'), 'is_current' => ! $isOldVersion,
                'current_reference' => $currentIdentity?->reference_code, 'current_revision' => $currentIdentity?->revision,
            ],
            'document' => $summary, 'issues' => array_values($issues), 'distributions' => $items,
            'print_url' => $printUrl,
        ], 200, ['Cache-Control' => 'no-store, private']);
    }

    private function token(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/\b(CP-[A-Z0-9-]+)\b/i', $value, $match)) return mb_strtoupper($match[1]);
        if (preg_match('/([0-9a-f]{8}-[0-9a-f-]{27,})/i', $value, $match)) return mb_strtolower($match[1]);
        return Str::limit($value, 500, '');
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant && (int) session('tenant_id') === (int) $tenant->id, 403);
        return $tenant;
    }

    private function authorizePortal(Request $request, string $portal): void
    {
        if ($portal === 'accounting') abort_unless($request->user()?->can('view_accounting_processes'), 403);
    }
}
