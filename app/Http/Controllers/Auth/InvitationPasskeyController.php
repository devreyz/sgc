<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Passkeys\GenerateSecureRegistrationOptions;
use App\Actions\Passkeys\StoreSecurePasskey;
use App\Http\Controllers\Controller;
use App\Http\Requests\SecurePasskeyRegistrationRequest;
use App\Models\Associate;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AccessInvitationService;
use App\Services\AuthenticationRedirector;
use App\Services\SecurityAuditService;
use App\Support\PasskeyName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use RuntimeException;
use Throwable;

class InvitationPasskeyController extends Controller
{
    public function show(Request $request, AccessInvitationService $service)
    {
        try {
            $invitation = $service->invitationForGrant($request);
        } catch (RuntimeException) {
            return redirect()->route('access.invitation.verify')
                ->with('error', 'O convite nao esta disponivel.');
        }

        $associateName = $invitation->associate_id
            ? Associate::withoutGlobalScopes()
                ->where('tenant_id', $invitation->tenant_id)
                ->whereKey($invitation->associate_id)
                ->first()?->display_name
            : null;
        $memberName = $invitation->tenant_user_id
            ? TenantUser::query()
                ->where('tenant_id', $invitation->tenant_id)
                ->whereKey($invitation->tenant_user_id)
                ->first()?->display_name
            : null;

        return view('auth.invitation-passkey', [
            'suggestedPasskeyName' => PasskeyName::suggest($associateName ?: $memberName),
        ]);
    }

    public function options(
        Request $request,
        AccessInvitationService $service,
        GenerateSecureRegistrationOptions $generate,
    ): JsonResponse {
        try {
            $invitation = $service->invitationForGrant($request);
            $membership = $invitation->tenant_user_id
                ? TenantUser::query()
                    ->where('tenant_id', $invitation->tenant_id)
                    ->whereKey($invitation->tenant_user_id)
                    ->where('status', true)
                    ->firstOrFail()
                : null;
            $associate = $invitation->associate_id
                ? Associate::withoutGlobalScopes()
                    ->where('tenant_id', $invitation->tenant_id)
                    ->whereKey($invitation->associate_id)
                    ->firstOrFail()
                : null;

            if (! $membership && ! $associate) {
                throw new RuntimeException('Invitation target missing.');
            }

            $targetUserId = $membership?->user_id ?? $associate?->user_id;
            $user = $targetUserId ? User::query()->findOrFail($targetUserId) : new User;
            if ($user->exists && ! $user->status) {
                throw new RuntimeException('Inactive account.');
            }

            if (! $user->exists) {
                $handle = (string) $request->session()->get('access_provisional_handle');
                if ($handle === '') {
                    $handle = Base64UrlSafe::encodeUnpadded(random_bytes(32));
                    $request->session()->put('access_provisional_handle', $handle);
                }
                $user->forceFill([
                    'name' => 'Conta ZeCoop',
                    'webauthn_user_handle' => $handle,
                    'status' => true,
                ]);
            }

            $options = $generate($user);
            $request->session()->put('sgc.passkeys.registration', [
                'purpose' => 'invitation',
                'invitation_id' => $invitation->id,
                'user_id' => $user->exists ? $user->id : null,
                'provisional_handle' => $user->webauthn_user_handle,
                'options' => WebAuthn::toJson($options),
                'expires_at' => now()->addSeconds((int) config('passkeys.challenge_ttl', 300))->timestamp,
            ]);

            return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'O convite nao esta disponivel.'], 422);
        }
    }

    public function store(
        SecurePasskeyRegistrationRequest $request,
        AccessInvitationService $service,
        StoreSecurePasskey $store,
        AuthenticationRedirector $redirector,
        SecurityAuditService $audit,
    ): JsonResponse {
        try {
            $options = $request->registrationOptions('invitation');
            $context = $request->ceremonyContext();

            [$user, $passkey, $invitation] = DB::transaction(function () use ($request, $service, $store, $options, $context) {
                $invitation = $service->invitationForGrant($request, lock: true);
                if (! hash_equals((string) $invitation->id, (string) ($context['invitation_id'] ?? ''))) {
                    throw new RuntimeException('Invitation ceremony mismatch.');
                }

                $membership = $invitation->tenant_user_id
                    ? TenantUser::query()
                        ->where('tenant_id', $invitation->tenant_id)
                        ->whereKey($invitation->tenant_user_id)
                        ->lockForUpdate()
                        ->firstOrFail()
                    : null;
                $associate = $invitation->associate_id
                    ? Associate::withoutGlobalScopes()
                        ->where('tenant_id', $invitation->tenant_id)
                        ->whereKey($invitation->associate_id)
                        ->lockForUpdate()
                        ->firstOrFail()
                    : null;

                if (! $membership && ! $associate) {
                    throw new RuntimeException('Invitation target missing.');
                }

                if ($membership && ! $membership->status) {
                    throw new RuntimeException('Inactive membership.');
                }

                $targetUserId = $membership?->user_id ?? $associate?->user_id;
                if ($targetUserId) {
                    $user = User::query()->whereKey($targetUserId)->lockForUpdate()->firstOrFail();
                    if ((int) ($context['user_id'] ?? 0) !== (int) $user->id || ! $user->status) {
                        throw new RuntimeException('Invitation target mismatch.');
                    }

                    if ($membership && $associate?->user_id && (int) $associate->user_id !== (int) $user->id) {
                        throw new RuntimeException('Invitation target mismatch.');
                    }
                } else {
                    if (($context['user_id'] ?? null) !== null || ! is_string($context['provisional_handle'] ?? null)) {
                        throw new RuntimeException('Invalid provisional identity.');
                    }

                    $user = User::query()->create([
                        'name' => 'Conta ZeCoop',
                        'email' => null,
                        'password' => null,
                        'status' => true,
                        'webauthn_user_handle' => $context['provisional_handle'],
                    ]);
                    $associate?->forceFill(['user_id' => $user->id])->save();
                }

                $membership ??= TenantUser::query()
                    ->where('tenant_id', $invitation->tenant_id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($membership && ! $membership->status) {
                    throw new RuntimeException('Inactive membership.');
                }

                if (! $membership) {
                    $tenantName = trim((string) ($associate?->nickname ?: $associate?->property_name));
                    $membership = new TenantUser;
                    $membership->forceFill([
                        'tenant_id' => $invitation->tenant_id,
                        'user_id' => $user->id,
                        'is_admin' => false,
                        'roles' => ['associado'],
                        'status' => true,
                        'tenant_name' => $tenantName !== '' ? $tenantName : 'Associado sem nome cadastrado',
                    ])->save();
                }

                if (! $invitation->tenant_user_id) {
                    $invitation->tenant_user_id = $membership->id;
                }

                $passkey = $store(
                    $user,
                    $request->string('name')->toString(),
                    $request->credential(),
                    $options,
                );

                $invitation->forceFill([
                    'status' => 'consumed',
                    'consumed_at' => now(),
                    'token_hash' => hash('sha256', random_bytes(32)),
                    'code_hash' => 'consumed:'.Str::random(48),
                    'claimed_session_hash' => null,
                    'enrollment_expires_at' => null,
                ])->save();

                return [$user, $passkey, $invitation];
            });

            $request->session()->forget([
                'access_enrollment', 'access_provisional_handle', 'sgc.passkeys.registration',
            ]);
            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();

            $audit->record('access_invitation_consumed', 'success', [
                'tenant_id' => $invitation->tenant_id,
                'target_user_id' => $user->id,
                'associate_id' => $invitation->associate_id,
                'invitation_id' => $invitation->id,
                'context' => ['passkey_id' => $passkey->id],
            ], $request);
            $audit->record('passkey_registered', 'success', [
                'tenant_id' => $invitation->tenant_id,
                'target_user_id' => $user->id,
                'associate_id' => $invitation->associate_id,
                'invitation_id' => $invitation->id,
                'context' => ['source' => 'invitation', 'passkey_id' => $passkey->id],
            ], $request);

            return response()->json(['redirect' => $redirector->pathFor($user)]);
        } catch (Throwable $exception) {
            report($exception);
            $audit->record('webauthn_failed', 'denied', [
                'context' => ['stage' => 'invitation_registration'],
            ], $request);

            return response()->json(['message' => 'Nao foi possivel concluir o acesso.'], 422);
        }
    }
}
