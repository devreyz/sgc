<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CustomerHierarchyService
{
    /** @var array<string, bool> */
    private array $referenceAvailability = [];

    /**
     * Referencias que tornam a identidade e a hierarquia do cliente historicas.
     * As consultas usam o query builder para incluir inclusive registros removidos
     * por soft delete.
     *
     * @var array<string, array{column: string, label: string}>
     */
    private const CUSTOMER_REFERENCES = [
        'production_deliveries' => ['column' => 'customer_id', 'label' => 'entregas ou distribuicoes'],
        'customer_billing_receipts' => ['column' => 'customer_id', 'label' => 'comprovantes de cliente'],
        'sales_projects' => ['column' => 'customer_id', 'label' => 'projetos de venda'],
        'sales_project_customers' => ['column' => 'customer_id', 'label' => 'participacoes em projetos'],
        'project_demands' => ['column' => 'customer_id', 'label' => 'demandas de projeto'],
        'buyer_requests' => ['column' => 'customer_id', 'label' => 'solicitacoes de compradores'],
        'buyer_request_items' => ['column' => 'customer_id', 'label' => 'itens de solicitacao'],
        'revenues' => ['column' => 'customer_id', 'label' => 'recebimentos financeiros'],
        'quick_sales' => ['column' => 'customer_id', 'label' => 'vendas rapidas'],
        'customer_product_prices' => ['column' => 'customer_id', 'label' => 'historico de precos'],
    ];

    /** @var array<string, array{column: string, label: string}> */
    private const ORGANIZATION_REFERENCES = [
        'customer_billing_receipts' => ['column' => 'organization_id', 'label' => 'comprovantes de cliente'],
        'sales_project_organizations' => ['column' => 'organization_id', 'label' => 'participacoes em projetos'],
        'buyer_requests' => ['column' => 'organization_id', 'label' => 'solicitacoes de compradores'],
        'billing_authorizations' => ['column' => 'organization_id', 'label' => 'autorizacoes de faturamento'],
        'organization_authorized_emails' => ['column' => 'organization_id', 'label' => 'acessos autorizados'],
    ];

    public function validateForSave(Customer $customer): void
    {
        $tenantId = (int) ($customer->tenant_id ?: session('tenant_id'));
        if ($tenantId <= 0) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Não foi possível identificar a organização atual.',
            ]);
        }

        $customer->tenant_id = $tenantId;
        $customer->unit_type = in_array($customer->unit_type, ['independent', 'headquarters', 'branch'], true)
            ? $customer->unit_type
            : 'independent';

        $this->ensureHistoricalIdentityIsStable($customer);

        $this->validateOrganization($customer, $tenantId);
        $parent = $this->validateParent($customer, $tenantId);
        $this->validateFamilyOrganizations($customer, $parent, $tenantId);
        $this->validateSharedDocument($customer, $tenantId);
    }

    public function afterSaved(Customer $customer): void
    {
        if ($customer->wasChanged('organization_id')) {
            $this->syncFamilyOrganization($customer);
        }

        if ($customer->unit_type === 'branch' && $customer->parent_customer_id) {
            Customer::withoutEvents(function () use ($customer): void {
                Customer::withoutGlobalScopes()
                    ->where('tenant_id', $customer->tenant_id)
                    ->whereKey($customer->parent_customer_id)
                    ->where('unit_type', 'independent')
                    ->update(['unit_type' => 'headquarters']);
            });
        }
    }

    public function ensureCanDelete(Customer $customer): void
    {
        if (Customer::withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('parent_customer_id', $customer->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'Esta matriz possui filiais vinculadas e nao pode ser excluida.',
            ]);
        }

        if ($customer->parent_customer_id || $this->organizationLinkIsLocked($customer)) {
            throw ValidationException::withMessages([
                'customer' => 'Este cliente pertence a uma família matriz/filial ou a uma organização com comprovantes vinculados e não pode ser excluído. Desative o cadastro para preservar o histórico.',
            ]);
        }

        $references = $this->linkedDataLabels($customer);
        if ($references !== []) {
            throw ValidationException::withMessages([
                'customer' => 'Este cliente possui historico em '.implode(', ', array_slice($references, 0, 3)).'. Desative o cadastro para impedir novos usos sem apagar o historico.',
            ]);
        }
    }

    public function ensureCanDissociate(Customer $customer): void
    {
        if ($this->organizationLinkIsLocked($customer)) {
            throw ValidationException::withMessages([
                'organization_id' => 'A organização já possui comprovante vinculado a entregas. O agrupamento não pode mais ser desfeito; desative o cliente se necessário.',
            ]);
        }
    }

    public function organizationLinkIsLocked(Customer $customer): bool
    {
        $organizationId = (int) $customer->organization_id;
        if ($organizationId <= 0 || ! Schema::hasTable('customer_billing_receipts')) {
            return false;
        }

        $receipts = DB::table('customer_billing_receipts')
            ->where('tenant_id', $customer->tenant_id)
            ->where('organization_id', $organizationId);

        if (Schema::hasTable('production_deliveries')
            && Schema::hasColumn('production_deliveries', 'billing_receipt_id')
            && $receipts->clone()->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('production_deliveries')
                    ->whereColumn('production_deliveries.billing_receipt_id', 'customer_billing_receipts.id');
            })->exists()) {
            return true;
        }

        if (! Schema::hasColumn('customer_billing_receipts', 'delivery_ids')) {
            return false;
        }

        return $receipts->whereNotNull('delivery_ids')
            ->pluck('delivery_ids')
            ->contains(function ($value): bool {
                $ids = is_array($value) ? $value : json_decode((string) $value, true);

                return is_array($ids) && $ids !== [];
            });
    }

    public function ensureOrganizationCanDelete(Organization $organization): void
    {
        $hasCustomers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->exists();

        if ($hasCustomers) {
            throw ValidationException::withMessages([
                'organization' => 'Esta organizacao possui clientes vinculados e nao pode ser excluida. Preserve o agrupamento historico e desative a organizacao.',
            ]);
        }

        $references = $this->organizationLinkedDataLabels($organization);
        if ($references !== []) {
            throw ValidationException::withMessages([
                'organization' => 'Esta organizacao possui historico em '.implode(', ', array_slice($references, 0, 3)).' e nao pode ser excluida. Desative-a para preservar os registros.',
            ]);
        }
    }

    public function organizationDeletionBlockReason(Organization $organization): ?string
    {
        if (Customer::withoutGlobalScopes()
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->exists()) {
            return 'A organização possui clientes vinculados.';
        }

        $references = $this->organizationLinkedDataLabels($organization);

        return $references === []
            ? null
            : 'Ha historico vinculado: '.implode(', ', array_slice($references, 0, 3)).'.';
    }

    public function hasLinkedData(Customer $customer): bool
    {
        if (! $customer->exists || ! $customer->getKey()) {
            return false;
        }

        foreach (self::CUSTOMER_REFERENCES as $table => $reference) {
            if ($this->referenceExists($customer, $table, $reference['column'])) {
                return true;
            }
        }

        return $this->hasCustomerDocuments($customer);
    }

    /** @return list<string> */
    public function linkedDataLabels(Customer $customer): array
    {
        if (! $customer->exists || ! $customer->getKey()) {
            return [];
        }

        $labels = [];
        foreach (self::CUSTOMER_REFERENCES as $table => $reference) {
            if ($this->referenceExists($customer, $table, $reference['column'])) {
                $labels[] = $reference['label'];
            }
        }

        if ($this->hasCustomerDocuments($customer)) {
            $labels[] = 'documentos anexados';
        }

        return array_values(array_unique($labels));
    }

    public function deletionBlockReason(Customer $customer): ?string
    {
        if (Customer::withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('parent_customer_id', $customer->id)
            ->exists()) {
            return 'A matriz possui filiais vinculadas.';
        }

        if ($customer->parent_customer_id || $this->organizationLinkIsLocked($customer)) {
            return 'O cliente pertence a uma família matriz/filial ou organização com comprovantes; desative-o em vez de excluir.';
        }

        $references = $this->linkedDataLabels($customer);

        return $references === []
            ? null
            : 'Ha historico vinculado: '.implode(', ', array_slice($references, 0, 3)).'.';
    }

    private function ensureHistoricalIdentityIsStable(Customer $customer): void
    {
        if (! $customer->exists
            || ! $customer->isDirty(['organization_id', 'parent_customer_id', 'unit_type', 'cnpj'])) {
            return;
        }

        $messages = [];
        if ($customer->isDirty('organization_id') && $customer->getOriginal('organization_id')) {
            $original = clone $customer;
            $original->organization_id = $customer->getOriginal('organization_id');
            if ($this->organizationLinkIsLocked($original)) {
                $messages['organization_id'] = 'A organização já possui comprovante vinculado a entregas e não pode ser removida ou trocada. Desative o cliente para preservar o histórico.';
            }
        }

        $hadParent = (bool) $customer->getOriginal('parent_customer_id');
        $hasBranches = Customer::withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('parent_customer_id', $customer->getKey())
            ->exists();
        if ($customer->isDirty(['parent_customer_id', 'unit_type']) && ($hadParent || $hasBranches)) {
            $messages['parent_customer_id'] = 'O vínculo entre matriz e filial não pode ser removido ou trocado. Desative a unidade se necessário.';
        }

        if ($customer->isDirty('cnpj') && $this->hasLinkedData($customer)) {
            $messages['cnpj'] = 'O CNPJ nao pode ser alterado porque esta unidade ja possui dados historicos.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function syncFamilyOrganization(Customer $customer): void
    {
        $rootId = $customer->parent_customer_id ?: $customer->id;

        Customer::withoutEvents(function () use ($customer, $rootId): void {
            Customer::withoutGlobalScopes()
                ->where('tenant_id', $customer->tenant_id)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($rootId): void {
                    $query->whereKey($rootId)->orWhere('parent_customer_id', $rootId);
                })
                ->whereKeyNot($customer->getKey())
                ->update([
                    'organization_id' => $customer->organization_id,
                    'updated_at' => now(),
                ]);
        });
    }

    private function referenceExists(Customer $customer, string $table, string $column): bool
    {
        $availableKey = $table.'.'.$column;
        $available = $this->referenceAvailability[$availableKey]
            ??= Schema::hasTable($table) && Schema::hasColumn($table, $column);

        if (! $available) {
            return false;
        }

        $query = DB::table($table)->where($column, $customer->getKey());
        $tenantColumnKey = $table.'.tenant_id';
        $hasTenantColumn = $this->referenceAvailability[$tenantColumnKey]
            ??= Schema::hasColumn($table, 'tenant_id');

        if ($hasTenantColumn) {
            $query->where('tenant_id', $customer->tenant_id);
        }

        return $query->exists();
    }

    private function hasCustomerDocuments(Customer $customer): bool
    {
        $available = $this->referenceAvailability['documents.documentable']
            ??= Schema::hasTable('documents')
                && Schema::hasColumn('documents', 'documentable_type')
                && Schema::hasColumn('documents', 'documentable_id');

        return $available && DB::table('documents')
            ->where('documentable_type', Customer::class)
            ->where('documentable_id', $customer->getKey())
            ->exists();
    }

    /** @return list<string> */
    private function organizationLinkedDataLabels(Organization $organization): array
    {
        $labels = [];
        foreach (self::ORGANIZATION_REFERENCES as $table => $reference) {
            $availableKey = $table.'.'.$reference['column'];
            $available = $this->referenceAvailability[$availableKey]
                ??= Schema::hasTable($table) && Schema::hasColumn($table, $reference['column']);

            if (! $available) {
                continue;
            }

            $query = DB::table($table)->where($reference['column'], $organization->getKey());
            $tenantColumnKey = $table.'.tenant_id';
            $hasTenantColumn = $this->referenceAvailability[$tenantColumnKey]
                ??= Schema::hasColumn($table, 'tenant_id');
            if ($hasTenantColumn) {
                $query->where('tenant_id', $organization->tenant_id);
            }

            if ($query->exists()) {
                $labels[] = $reference['label'];
            }
        }

        return array_values(array_unique($labels));
    }

    private function validateOrganization(Customer $customer, int $tenantId): void
    {
        if (! $customer->organization_id) {
            return;
        }

        $exists = Organization::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($customer->organization_id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'organization_id' => 'A organização selecionada não pertence à organização atual.',
            ]);
        }
    }

    private function validateParent(Customer $customer, int $tenantId): ?Customer
    {
        if ($customer->unit_type !== 'branch') {
            $customer->parent_customer_id = null;

            return null;
        }

        if (! $customer->parent_customer_id || (int) $customer->parent_customer_id === (int) $customer->id) {
            throw ValidationException::withMessages([
                'parent_customer_id' => 'Selecione uma matriz válida para esta filial.',
            ]);
        }

        $parent = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($customer->parent_customer_id)
            ->whereNull('deleted_at')
            ->first();

        if (! $parent || $parent->unit_type === 'branch') {
            throw ValidationException::withMessages([
                'parent_customer_id' => 'A matriz selecionada é inválida ou pertence a outra organização.',
            ]);
        }

        $isChangingFamilyOrganization = $customer->exists && $customer->isDirty('organization_id');

        if ($parent->organization_id && ! $customer->organization_id && ! $isChangingFamilyOrganization) {
            $customer->organization_id = $parent->organization_id;
        }

        if ($parent->organization_id && $customer->organization_id
            && (int) $parent->organization_id !== (int) $customer->organization_id
            && ! $isChangingFamilyOrganization) {
            throw ValidationException::withMessages([
                'organization_id' => 'Matriz e filial devem pertencer à mesma organização de clientes.',
            ]);
        }

        return $parent;
    }

    private function validateFamilyOrganizations(Customer $customer, ?Customer $parent, int $tenantId): void
    {
        if ($customer->exists && $customer->isDirty('organization_id')) {
            return;
        }

        $rootId = $parent?->id ?: ($customer->parent_customer_id ?: $customer->id);
        if (! $rootId) {
            return;
        }

        $organizationIds = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($rootId): void {
                $query->whereKey($rootId)->orWhere('parent_customer_id', $rootId);
            })
            ->when($customer->exists, fn ($query) => $query->where('id', '!=', $customer->id))
            ->pluck('organization_id')
            ->filter()
            ->push($customer->organization_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($organizationIds->count() > 1) {
            throw ValidationException::withMessages([
                'organization_id' => 'Todas as unidades da mesma matriz devem pertencer à mesma organização de clientes.',
            ]);
        }
    }

    private function validateSharedDocument(Customer $customer, int $tenantId): void
    {
        $document = $this->digits($customer->cnpj);
        if ($document === '') {
            return;
        }

        /** @var Collection<int, Customer> $conflicts */
        $conflicts = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->when($customer->exists, fn ($query) => $query->where('id', '!=', $customer->id))
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', ''), ' ', '') = ?", [$document])
            ->get(['id', 'tenant_id', 'organization_id', 'parent_customer_id', 'unit_type', 'cnpj']);

        foreach ($conflicts as $conflict) {
            $sameOrganization = $customer->organization_id
                && $conflict->organization_id
                && (int) $customer->organization_id === (int) $conflict->organization_id;

            if ($sameOrganization || $this->sameFamily($customer, $conflict)) {
                continue;
            }

            throw ValidationException::withMessages([
                'cnpj' => 'Este CNPJ já pertence a outro cliente. Para compartilhá-lo, vincule as unidades à mesma matriz ou à mesma organização de clientes.',
            ]);
        }
    }

    private function sameFamily(Customer $customer, Customer $other): bool
    {
        $customerRoot = $customer->parent_customer_id ?: $customer->id;
        $otherRoot = $other->parent_customer_id ?: $other->id;

        return $customerRoot && $otherRoot && (int) $customerRoot === (int) $otherRoot;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }
}
