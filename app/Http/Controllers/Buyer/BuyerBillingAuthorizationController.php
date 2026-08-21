<?php

namespace App\Http\Controllers\Buyer;

use App\Enums\BillingAuthorizationStatus;
use App\Exceptions\BillingAuthorizationBlockedException;
use App\Http\Controllers\Controller;
use App\Models\BillingAuthorization;
use App\Models\CustomerBillingReceipt;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationAuthorizedEmail;
use App\Services\Accounting\BillingAuthorizationWorkflowService;
use App\Services\DocumentUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuyerBillingAuthorizationController extends Controller
{
    public function index(Request $request): View
    {
        $organization = $this->organization($request);
        $rounds = BillingAuthorization::withoutGlobalScopes()
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->whereHas('receipt.project.organizations', fn ($query) => $query->where('organizations.id', $organization->id))
            ->with(['receipt:id,tenant_id,sales_project_id,receipt_year,receipt_number,receipt_label,tenant_receipt_year,tenant_receipt_number,project_receipt_year,project_receipt_number,from_date,to_date,total_net',
                'receipt.project:id,tenant_id,title,code,receipt_numbering_scope,receipt_number_format,receipt_project_reference'])
            ->latest('sent_at')->paginate(20);

        $organizations = collect($request->attributes->get('buyer_accesses', []))
            ->pluck('organization')->filter()->unique('id')->sortBy('name')->values();

        return view('buyer.authorizations.index', compact('organization', 'organizations', 'rounds'));
    }

    public function show(Request $request): View
    {
        $organization = $this->organization($request);
        $round = $this->round($request, $organization)->load('receipt');

        return view('buyer.authorizations.show', [
            'organization' => $organization,
            'round' => $round,
            'snapshot' => $round->snapshot,
            'canRespond' => $round->status === BillingAuthorizationStatus::SENT,
        ]);
    }

    public function authorizeBilling(Request $request, BillingAuthorizationWorkflowService $workflow): RedirectResponse
    {
        $organization = $this->organization($request);
        $round = $this->round($request, $organization);
        $validated = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        try {
            $workflow->authorize($round, $request->user(), $this->access($request), $validated['message'] ?? null);
        } catch (BillingAuthorizationBlockedException $exception) {
            return back()->withErrors(['authorization' => collect($exception->issues)->pluck('message')->implode(' ')]);
        }

        return back()->with('success', 'Faturamento autorizado.');
    }

    public function requestCorrection(Request $request, BillingAuthorizationWorkflowService $workflow): RedirectResponse
    {
        $organization = $this->organization($request);
        $round = $this->round($request, $organization);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        try {
            $workflow->requestCorrection($round, $request->user(), $this->access($request), $validated['reason']);
        } catch (BillingAuthorizationBlockedException $exception) {
            return back()->withErrors(['authorization' => collect($exception->issues)->pluck('message')->implode(' ')]);
        }

        return back()->with('success', 'Solicitação de correção enviada.');
    }

    public function downloadAttachment(Request $request, DocumentUploadService $documents)
    {
        $organization = $this->organization($request);
        $round = $this->round($request, $organization);
        $documentId = (int) $request->route('document');
        $isFrozenAttachment = collect(data_get($round->snapshot, 'document.attachments', []))
            ->contains(fn (array $attachment): bool => (int) ($attachment['id'] ?? 0) === $documentId);
        abort_unless($isFrozenAttachment, 404);

        $document = Document::withoutGlobalScopes()
            ->where('tenant_id', $round->tenant_id)
            ->where('documentable_type', CustomerBillingReceipt::class)
            ->where('documentable_id', $round->customer_billing_receipt_id)
            ->findOrFail($documentId);
        abort_unless(filled($document->path) && filled($document->disk), 404);

        return $documents->download($document);
    }

    private function round(Request $request, Organization $organization): BillingAuthorization
    {
        return BillingAuthorization::withoutGlobalScopes()
            ->where('tenant_id', $organization->tenant_id)
            ->where('organization_id', $organization->id)
            ->whereHas('receipt.project.organizations', fn ($query) => $query->where('organizations.id', $organization->id))
            ->findOrFail((int) $request->route('billingAuthorization'));
    }

    private function organization(Request $request): Organization
    {
        $organization = $request->attributes->get('buyer_organization');
        abort_unless($organization instanceof Organization, 404);

        return $organization;
    }

    private function access(Request $request): OrganizationAuthorizedEmail
    {
        $access = $request->attributes->get('buyer_access');
        // Tenant administrators may preview the buyer portal, but may not answer for a buyer.
        abort_unless($access instanceof OrganizationAuthorizedEmail, 403);

        return $access;
    }
}
