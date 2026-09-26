<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingAccessScope;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\Accounting\AccountingAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingAccessController extends Controller
{
    public function index(Request $request, AccountingAccessService $access): View
    {
        $tenant = $this->tenant($request);
        abort_unless($access->canManage($request->user(), $tenant->id), 403);
        $memberships = TenantUser::query()->where('tenant_id', $tenant->id)->where('status', true)->with('user:id,name,email')->get()
            ->filter(fn (TenantUser $membership): bool => in_array('contador', $membership->roles ?? [], true))->values();

        $sourceReceiptIds = ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereNotNull('billing_receipt_id')->whereNotNull('associate_receipt_id')->distinct()->pluck('associate_receipt_id');

        return view('accounting.access.index', [
            'tenant' => $tenant,
            'memberships' => $memberships,
            'projects' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderByDesc('reference_year')->orderBy('title')->get(['id', 'title', 'code', 'type']),
            'projectTypes' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'billings' => CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->latest('issued_at')->limit(200)->get(['id', 'receipt_label', 'receipt_number', 'receipt_year']),
            'sourceReceipts' => AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('id', $sourceReceiptIds)->latest('issued_at')->limit(200)->get(),
            'scopes' => AccountingAccessScope::query()->where('tenant_id', $tenant->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, AccountingAccessService $access): RedirectResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($access->canManage($request->user(), $tenant->id), 403);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'scope_type' => ['required', Rule::in(['project', 'project_type', 'customer_billing_receipt', 'associate_receipt'])],
            'scope_id' => ['nullable', 'integer'],
            'scope_value' => ['nullable', 'string', 'max:120'],
        ]);
        $membership = TenantUser::query()->where('tenant_id', $tenant->id)->where('user_id', $data['user_id'])->where('status', true)->firstOrFail();
        abort_unless(in_array('contador', $membership->roles ?? [], true), 422, 'O usuário selecionado não possui a função Contabilidade.');
        $value = $data['scope_type'] === 'project_type' ? trim((string) ($data['scope_value'] ?? '')) : (int) ($data['scope_id'] ?? 0);
        abort_if(blank($value), 422, 'Selecione o item que será liberado.');
        $this->assertTarget($tenant->id, $data['scope_type'], $value);
        $key = $data['scope_type'].':'.$value;
        AccountingAccessScope::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $membership->user_id, 'scope_key' => $key],
            ['granted_by' => $request->user()->id, 'scope_type' => $data['scope_type'],
                'scope_id' => $data['scope_type'] === 'project_type' ? null : $value,
                'scope_value' => $data['scope_type'] === 'project_type' ? $value : null, 'active' => true],
        );

        return back()->with('success', 'Acesso contábil liberado.');
    }

    public function destroy(Request $request, AccountingAccessScope $scope, AccountingAccessService $access): RedirectResponse
    {
        $tenant = $this->tenant($request);
        abort_unless($access->canManage($request->user(), $tenant->id), 403);
        abort_unless((int) $scope->tenant_id === (int) $tenant->id, 404);
        $scope->update(['active' => false, 'granted_by' => $request->user()->id]);

        return back()->with('success', 'Acesso contábil removido.');
    }

    private function assertTarget(int $tenantId, string $type, string|int $value): void
    {
        $valid = match ($type) {
            'project' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($value)->exists(),
            'project_type' => SalesProject::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', $value)->exists(),
            'customer_billing_receipt' => CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($value)->exists(),
            'associate_receipt' => AssociateReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereKey($value)->exists(),
        };
        abort_unless($valid, 422, 'O item selecionado não pertence à organização atual.');
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant && (int) session('tenant_id') === (int) $tenant->id, 403);

        return $tenant;
    }
}
