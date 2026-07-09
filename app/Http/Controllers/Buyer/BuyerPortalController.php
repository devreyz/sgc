<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SalesProject;
use App\Services\BuyerRequestFulfillmentService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BuyerPortalController extends Controller
{
    public function dashboard(Request $request, BuyerRequestFulfillmentService $fulfillment)
    {
        $organization = $this->organization($request);
        $projects = $this->projectsQuery($organization)
            ->with(['buyerRequests' => fn ($query) => $query->where('organization_id', $organization->id)])
            ->get()
            ->map(function (SalesProject $project) use ($organization) {
                $requests = $project->buyerRequests->where('organization_id', $organization->id);

                return [
                    'project' => $project,
                    'requests_count' => $requests->count(),
                    'open_count' => $requests->whereIn('status', [
                        BuyerRequest::STATUS_OPEN,
                        BuyerRequest::STATUS_PARTIALLY_FULFILLED,
                    ])->count(),
                ];
            });

        $recentRequests = BuyerRequest::where('organization_id', $organization->id)
            ->with(['salesProject', 'customer', 'items.product'])
            ->latest()
            ->limit(6)
            ->get();

        return view('buyer.dashboard', compact('organization', 'projects', 'recentRequests'));
    }

    public function projects(Request $request)
    {
        $organization = $this->organization($request);
        $projects = $this->projectsQuery($organization)
            ->withCount(['buyerRequests' => fn ($query) => $query->where('organization_id', $organization->id)])
            ->paginate(12);

        return view('buyer.projects', compact('organization', 'projects'));
    }

    public function showProject(Request $request, BuyerRequestFulfillmentService $fulfillment)
    {
        $project = $this->resolveProject($request, $request->route('project'));
        $organization = $this->authorizeProject($request, $project);
        $customers = $this->projectCustomers($project, $organization);

        $buyerRequests = BuyerRequest::where('sales_project_id', $project->id)
            ->where('organization_id', $organization->id)
            ->with(['customer', 'items.product'])
            ->latest()
            ->get()
            ->each(fn (BuyerRequest $buyerRequest) => $fulfillment->updateStatus($buyerRequest));

        $reportRows = $fulfillment->organizationReport($project, $organization);
        $limitEnabled = $fulfillment->limitIsEnabled($project, $organization);

        return view('buyer.project-show', compact(
            'organization',
            'project',
            'customers',
            'buyerRequests',
            'reportRows',
            'limitEnabled'
        ));
    }

    public function createRequest(Request $request)
    {
        $project = $this->resolveProject($request, $request->route('project'));
        $organization = $this->authorizeProject($request, $project);
        $customers = $this->projectCustomers($project, $organization);
        $productsByCustomer = $customers->mapWithKeys(function (Customer $customer) use ($project) {
            return [$customer->id => $this->availableProductsForCustomer($project, $customer)];
        });

        return view('buyer.request-create', compact('organization', 'project', 'customers', 'productsByCustomer'));
    }

    public function storeRequest(Request $request, PricingService $pricingService)
    {
        $project = $this->resolveProject($request, $request->route('project'));
        $organization = $this->authorizeProject($request, $project);
        $customers = $this->projectCustomers($project, $organization);
        $customerIds = $customers->pluck('id')->all();

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'in:'.implode(',', $customerIds)],
            'reference_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.customer_id' => ['nullable', 'integer', 'in:'.implode(',', $customerIds)],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = (int) $project->tenant_id;

        try {
            $buyerRequest = DB::transaction(function () use ($validated, $project, $organization, $pricingService, $tenantId) {
                $buyerRequest = BuyerRequest::create([
                    'tenant_id' => $tenantId,
                    'sales_project_id' => $project->id,
                    'organization_id' => $organization->id,
                    'customer_id' => $validated['customer_id'] ?? null,
                    'created_by' => Auth::id(),
                    'reference_date' => $validated['reference_date'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'submitted_at' => now(),
                ]);

                foreach ($validated['items'] as $line) {
                    $customerId = $line['customer_id'] ?? $buyerRequest->customer_id;
                    $customer = Customer::where('tenant_id', $tenantId)
                        ->where('organization_id', $organization->id)
                        ->findOrFail($customerId);
                    $product = Product::where('tenant_id', $tenantId)->active()->findOrFail($line['product_id']);

                    if (! $this->productIsAvailableForCustomer($project, $customer, $product)) {
                        throw ValidationException::withMessages([
                            'items' => 'O produto "'.$product->name.'" nao esta disponivel para '.$customer->name.' neste projeto.',
                        ]);
                    }

                    $price = $pricingService->resolvePrice($product, $customer, $project);
                    if ($price['source'] === 'unpriced' || bccomp((string) $price['sale_price'], '0', 4) === 0) {
                        throw ValidationException::withMessages([
                            'items' => 'O produto "'.$product->name.'" nao tem preco configurado para '.$customer->name.'.',
                        ]);
                    }

                    BuyerRequestItem::create([
                        'tenant_id' => $tenantId,
                        'buyer_request_id' => $buyerRequest->id,
                        'customer_id' => $customer->id,
                        'product_id' => $product->id,
                        'requested_quantity' => $line['quantity'],
                        'unit_price_snapshot' => $price['sale_price'],
                        'price_table_id' => $price['price_table_id'],
                        'price_source' => $price['source'],
                        'notes' => $line['notes'] ?? null,
                    ]);
                }

                activity()
                    ->performedOn($buyerRequest)
                    ->causedBy(Auth::user())
                    ->withProperties(['tenant_id' => $tenantId, 'organization_id' => $organization->id])
                    ->log('Solicitacao criada no portal da organizacao compradora');

                return $buyerRequest;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()
            ->route('buyer.requests.show', ['tenant' => $request->route('tenant'), 'buyerRequest' => $buyerRequest])
            ->with('success', 'Solicitacao enviada com sucesso.');
    }

    public function showRequest(Request $request, BuyerRequestFulfillmentService $fulfillment)
    {
        $buyerRequest = $this->resolveBuyerRequest($request, $request->route('buyerRequest'));
        $organization = $this->organization($request);

        if ((int) $buyerRequest->tenant_id !== (int) $organization->tenant_id || (int) $buyerRequest->organization_id !== (int) $organization->id) {
            Log::warning('Buyer portal request access denied', [
                'reason' => 'request_organization_mismatch',
                'user_id' => $request->user()?->id,
                'request_id' => $buyerRequest->id,
                'request_tenant_id' => $buyerRequest->tenant_id,
                'request_organization_id' => $buyerRequest->organization_id,
                'buyer_organization_id' => $organization->id,
                'buyer_tenant_id' => $organization->tenant_id,
            ]);
            abort(404);
        }

        $this->authorizeProject($request, $buyerRequest->salesProject);
        $buyerRequest->load(['salesProject', 'customer', 'items.product', 'items.customer']);
        $buyerRequest = $fulfillment->updateStatus($buyerRequest);
        $summary = $fulfillment->summaryForRequest($buyerRequest);

        return view('buyer.request-show', compact('organization', 'buyerRequest', 'summary'));
    }

    public function reports(Request $request, BuyerRequestFulfillmentService $fulfillment)
    {
        $project = $this->resolveProject($request, $request->route('project'));
        $organization = $this->authorizeProject($request, $project);
        $rows = $fulfillment->organizationReport($project, $organization);
        $total = $rows->sum('total_value');

        return view('buyer.report', compact('organization', 'project', 'rows', 'total'));
    }

    private function organization(Request $request): Organization
    {
        return $request->attributes->get('buyer_organization');
    }

    private function authorizeProject(Request $request, SalesProject $project): Organization
    {
        $organization = $this->organization($request);

        if ((int) $project->tenant_id !== (int) $organization->tenant_id) {
            Log::warning('Buyer portal project access denied', [
                'reason' => 'project_tenant_mismatch',
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'project_tenant_id' => $project->tenant_id,
                'buyer_organization_id' => $organization->id,
                'buyer_tenant_id' => $organization->tenant_id,
            ]);
            abort(404);
        }

        $participates = DB::table('sales_project_organizations')
            ->join('organizations', 'organizations.id', '=', 'sales_project_organizations.organization_id')
            ->where('sales_project_organizations.sales_project_id', $project->id)
            ->where('sales_project_organizations.organization_id', $organization->id)
            ->where('organizations.tenant_id', $organization->tenant_id)
            ->where('organizations.active', true)
            ->exists();

        if (! $participates) {
            Log::warning('Buyer portal project access denied', [
                'reason' => 'organization_not_participant',
                'user_id' => $request->user()?->id,
                'project_id' => $project->id,
                'project_tenant_id' => $project->tenant_id,
                'buyer_organization_id' => $organization->id,
                'buyer_tenant_id' => $organization->tenant_id,
            ]);
            abort(404);
        }

        return $organization;
    }

    private function resolveProject(Request $request, SalesProject|string|int $project): SalesProject
    {
        if ($project instanceof SalesProject) {
            return $project;
        }

        $organization = $this->organization($request);

        return SalesProject::withoutGlobalScope('tenant')
            ->where('tenant_id', $organization->tenant_id)
            ->findOrFail((int) $project);
    }

    private function resolveBuyerRequest(Request $request, BuyerRequest|string|int $buyerRequest): BuyerRequest
    {
        if ($buyerRequest instanceof BuyerRequest) {
            return $buyerRequest;
        }

        $organization = $this->organization($request);

        return BuyerRequest::withoutGlobalScope('tenant')
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->findOrFail((int) $buyerRequest);
    }

    private function projectsQuery(Organization $organization)
    {
        return SalesProject::where('tenant_id', $organization->tenant_id)
            ->where('status', ProjectStatus::ACTIVE->value)
            ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organization->id))
            ->orderByDesc('start_date')
            ->orderBy('title');
    }

    private function projectCustomers(SalesProject $project, Organization $organization)
    {
        $projectCustomerIds = $project->customers()->pluck('customers.id');

        return Customer::where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->where('status', true)
            ->when($projectCustomerIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $projectCustomerIds))
            ->orderBy('name')
            ->get();
    }

    private function availableProductsForCustomer(SalesProject $project, Customer $customer)
    {
        $query = Product::where('tenant_id', $project->tenant_id)->active()->orderBy('name');

        if (! $project->allow_any_product) {
            $productIds = $project->demands()
                ->where(function ($query) use ($customer) {
                    $query->whereNull('customer_id')->orWhere('customer_id', $customer->id);
                })
                ->pluck('product_id')
                ->unique();

            $query->whereIn('id', $productIds);
        }

        return $query->get()->filter(function (Product $product) use ($project, $customer) {
            return $this->productIsAvailableForCustomer($project, $customer, $product);
        })->values();
    }

    private function productIsAvailableForCustomer(SalesProject $project, Customer $customer, Product $product): bool
    {
        if ((int) $product->tenant_id !== (int) $project->tenant_id || (int) $customer->tenant_id !== (int) $project->tenant_id) {
            return false;
        }

        if (! $project->allow_any_product) {
            $hasDemand = $project->demands()
                ->where('product_id', $product->id)
                ->where(function ($query) use ($customer) {
                    $query->whereNull('customer_id')->orWhere('customer_id', $customer->id);
                })
                ->exists();

            if (! $hasDemand) {
                return false;
            }
        }

        $price = app(PricingService::class)->resolvePrice($product, $customer, $project);

        return $price['source'] !== 'unpriced' && bccomp((string) $price['sale_price'], '0', 4) > 0;
    }
}
