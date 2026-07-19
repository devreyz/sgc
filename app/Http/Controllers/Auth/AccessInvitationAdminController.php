<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateAccessInvitationRequest;
use App\Models\AccessInvitation;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\AccessInvitationService;
use App\Services\SecurityAuditService;
use App\Services\TenantIdentityService;
use App\Services\TenantSecurityAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessInvitationAdminController extends Controller
{
    public function index(
        Request $request,
        Tenant $tenant,
        string $associate,
        TenantSecurityAuthorization $authorization,
        TenantIdentityService $identities,
    ) {
        $record = $authorization->authorizeAssociate(
            $request->user(), $tenant->id, $associate, 'access-links.view'
        );
        $invitations = AccessInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('associate_id', $record->id)
            ->latest()
            ->limit(25)
            ->get();
        $issuerNames = $identities->namesForUsers($tenant->id, $invitations->pluck('issued_by_user_id'));

        return view('auth.access-invitations-admin', [
            'targetLabel' => trim((string) ($record->nickname ?: $record->property_name))
                ?: $record->display_name,
            'invitations' => $invitations,
            'issuerNames' => $issuerNames,
            'storeUrl' => route('security.associates.access.store', [
                'tenant' => $tenant->slug,
                'associate' => $record->id,
            ]),
            'sentUrlTemplate' => route('security.associates.access.sent', [
                'tenant' => $tenant->slug,
                'associate' => $record->id,
                'invitation' => '__ID__',
            ]),
            'revokeUrlTemplate' => route('security.associates.access.revoke', [
                'tenant' => $tenant->slug,
                'associate' => $record->id,
                'invitation' => '__ID__',
            ]),
        ]);
    }

    public function memberIndex(
        Request $request,
        Tenant $tenant,
        string $membership,
        TenantSecurityAuthorization $authorization,
        TenantIdentityService $identities,
    ) {
        $record = $authorization->authorizeMembership(
            $request->user(), $tenant->id, $membership, 'access-links.view'
        );
        $associateId = $this->associateIdFor($record);
        $invitations = AccessInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($record, $associateId): void {
                $query->where('tenant_user_id', $record->id);
                if ($associateId) {
                    $query->orWhere('associate_id', $associateId);
                }
            })
            ->latest()
            ->limit(25)
            ->get();
        $issuerNames = $identities->namesForUsers($tenant->id, $invitations->pluck('issued_by_user_id'));

        return view('auth.access-invitations-admin', [
            'targetLabel' => $record->display_name,
            'invitations' => $invitations,
            'issuerNames' => $issuerNames,
            'storeUrl' => route('security.members.access.store', [
                'tenant' => $tenant->slug,
                'membership' => $record->id,
            ]),
            'sentUrlTemplate' => route('security.members.access.sent', [
                'tenant' => $tenant->slug,
                'membership' => $record->id,
                'invitation' => '__ID__',
            ]),
            'revokeUrlTemplate' => route('security.members.access.revoke', [
                'tenant' => $tenant->slug,
                'membership' => $record->id,
                'invitation' => '__ID__',
            ]),
        ]);
    }

    public function store(
        CreateAccessInvitationRequest $request,
        Tenant $tenant,
        string $associate,
        TenantSecurityAuthorization $authorization,
        AccessInvitationService $service,
    ): JsonResponse {
        $record = $authorization->authorizeAssociate(
            $request->user(), $tenant->id, $associate, 'access-links.create'
        );
        $result = $service->issue(
            $request->user(),
            $record,
            $tenant->id,
            $request->integer('expires_in_hours') ?: null,
        );

        return response()->json([
            'id' => $result['invitation']->id,
            'link' => $result['link'],
            'code' => $result['code'],
            'expires_at' => $result['invitation']->expires_at->toIso8601String(),
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    public function memberStore(
        CreateAccessInvitationRequest $request,
        Tenant $tenant,
        string $membership,
        TenantSecurityAuthorization $authorization,
        AccessInvitationService $service,
    ): JsonResponse {
        $record = $authorization->authorizeMembership(
            $request->user(), $tenant->id, $membership, 'access-links.create'
        );
        $result = $service->issue(
            $request->user(),
            $record,
            $tenant->id,
            $request->integer('expires_in_hours') ?: null,
        );

        return response()->json([
            'id' => $result['invitation']->id,
            'link' => $result['link'],
            'code' => $result['code'],
            'expires_at' => $result['invitation']->expires_at->toIso8601String(),
        ], 201)->header('Cache-Control', 'no-store, private');
    }

    public function revoke(
        Request $request,
        Tenant $tenant,
        string $associate,
        string $invitation,
        TenantSecurityAuthorization $authorization,
        AccessInvitationService $service,
    ): JsonResponse {
        $record = $authorization->authorizeAssociate(
            $request->user(), $tenant->id, $associate, 'access-links.revoke'
        );
        $invite = AccessInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('associate_id', $record->id)
            ->whereKey($invitation)
            ->firstOrFail();
        $service->revoke($invite, $request->user(), $request);

        return response()->json(['message' => 'Convite revogado.']);
    }

    public function memberRevoke(
        Request $request,
        Tenant $tenant,
        string $membership,
        string $invitation,
        TenantSecurityAuthorization $authorization,
        AccessInvitationService $service,
    ): JsonResponse {
        $record = $authorization->authorizeMembership(
            $request->user(), $tenant->id, $membership, 'access-links.revoke'
        );
        $associateId = $this->associateIdFor($record);
        $invite = $this->memberInvitationQuery($tenant->id, $record->id, $associateId)
            ->whereKey($invitation)
            ->firstOrFail();
        $service->revoke($invite, $request->user(), $request);

        return response()->json(['message' => 'Convite revogado.']);
    }

    public function sent(
        Request $request,
        Tenant $tenant,
        string $associate,
        string $invitation,
        TenantSecurityAuthorization $authorization,
        SecurityAuditService $audit,
    ): JsonResponse {
        $record = $authorization->authorizeAssociate(
            $request->user(), $tenant->id, $associate, 'access-links.create'
        );
        $invite = AccessInvitation::query()
            ->where('tenant_id', $tenant->id)
            ->where('associate_id', $record->id)
            ->whereKey($invitation)
            ->whereIn('status', ['pending', 'claimed'])
            ->firstOrFail();

        $audit->record('access_invitation_sent', 'success', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $record->user_id,
            'associate_id' => $record->id,
            'invitation_id' => $invite->id,
            'context' => ['channel' => 'browser_share'],
        ], $request);

        return response()->json(['message' => 'Envio registrado.']);
    }

    public function memberSent(
        Request $request,
        Tenant $tenant,
        string $membership,
        string $invitation,
        TenantSecurityAuthorization $authorization,
        SecurityAuditService $audit,
    ): JsonResponse {
        $record = $authorization->authorizeMembership(
            $request->user(), $tenant->id, $membership, 'access-links.create'
        );
        $associateId = $this->associateIdFor($record);
        $invite = $this->memberInvitationQuery($tenant->id, $record->id, $associateId)
            ->whereKey($invitation)
            ->whereIn('status', ['pending', 'claimed'])
            ->firstOrFail();

        $audit->record('access_invitation_sent', 'success', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $record->user_id,
            'associate_id' => $associateId,
            'invitation_id' => $invite->id,
            'context' => ['channel' => 'browser_share'],
        ], $request);

        return response()->json(['message' => 'Envio registrado.']);
    }

    private function associateIdFor(TenantUser $membership): ?int
    {
        return \App\Models\Associate::withoutGlobalScopes()
            ->where('tenant_id', $membership->tenant_id)
            ->where('user_id', $membership->user_id)
            ->value('id');
    }

    private function memberInvitationQuery(int $tenantId, int $membershipId, ?int $associateId)
    {
        return AccessInvitation::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($membershipId, $associateId): void {
                $query->where('tenant_user_id', $membershipId);
                if ($associateId) {
                    $query->orWhere('associate_id', $associateId);
                }
            });
    }
}
