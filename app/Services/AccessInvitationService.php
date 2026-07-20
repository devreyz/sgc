<?php

namespace App\Services;

use App\Models\AccessInvitation;
use App\Models\Associate;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class AccessInvitationService
{
    public function __construct(private readonly SecurityAuditService $audit) {}

    public function issue(User $issuer, Associate|TenantUser $target, int $tenantId, ?int $ttlHours = null): array
    {
        if ((int) $target->tenant_id !== $tenantId) {
            $this->audit->record('cross_tenant_attempt', 'denied', [
                'tenant_id' => $tenantId,
                'actor_user_id' => $issuer->id,
                'associate_id' => $target instanceof Associate ? $target->id : null,
            ]);
            throw new RuntimeException('Nao foi possivel criar o convite.');
        }

        $membership = $target instanceof TenantUser
            ? $target
            : TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $target->user_id)
                ->first();
        $associate = $target instanceof Associate
            ? $target
            : Associate::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $target->user_id)
                ->first();

        if ($membership && ! $membership->status) {
            throw new RuntimeException('Nao foi possivel criar o convite para um membro inativo.');
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('APP_URL precisa ser uma URL valida para gerar convites.');
        }

        $token = Str::random(8).$this->base64Url(random_bytes(32));
        $code = $this->randomCode();
        $expiresAt = now()->addHours($ttlHours ?? (int) config('security.invitation_ttl_hours', 36));

        $invitation = DB::transaction(function () use ($issuer, $associate, $membership, $tenantId, $token, $code, $expiresAt) {
            if ($membership) {
                TenantUser::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($membership->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } elseif ($associate) {
                Associate::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($associate->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                throw new RuntimeException('Nao foi possivel identificar o membro do convite.');
            }

            AccessInvitation::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($query) use ($associate, $membership): void {
                    if ($membership) {
                        $query->where('tenant_user_id', $membership->id);
                    }
                    if ($associate) {
                        $method = $membership ? 'orWhere' : 'where';
                        $query->{$method}('associate_id', $associate->id);
                    }
                })
                ->whereIn('status', ['pending', 'claimed'])
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

            $invitation = new AccessInvitation;
            $invitation->forceFill([
                'tenant_id' => $tenantId,
                'associate_id' => $associate?->id,
                'tenant_user_id' => $membership?->id,
                'issued_by_user_id' => $issuer->id,
                'token_hash' => hash('sha256', $token),
                'code_hash' => Hash::driver('argon2id')->make($this->codeMaterial($code)),
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'metadata' => [
                    'version' => 2,
                    'purpose' => ($membership || $associate?->user_id) ? 'recovery' : 'first_access',
                ],
            ]);
            $invitation->save();

            return $invitation;
        });

        $this->audit->record('access_invitation_created', 'success', [
            'tenant_id' => $tenantId,
            'actor_user_id' => $issuer->id,
            'target_user_id' => $membership?->user_id ?? $associate?->user_id,
            'associate_id' => $associate?->id,
            'invitation_id' => $invitation->id,
            'context' => ['expires_at' => $expiresAt->toIso8601String()],
        ]);

        return [
            'invitation' => $invitation,
            'link' => $baseUrl.'/acesso/'.rawurlencode($token),
            'code' => $code,
        ];
    }

    public function findPendingByToken(string $token): ?AccessInvitation
    {
        $invitation = AccessInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        // Compatibilidade com convites validados antes desta correcao. A etapa
        // de OTP nao consome o convite; ao reabrir o link, ela recomeca.
        if ($invitation?->status === 'claimed' && $invitation->expires_at->isFuture()) {
            $invitation->forceFill([
                'status' => 'pending',
                'claimed_at' => null,
                'claimed_session_hash' => null,
                'enrollment_expires_at' => null,
            ])->save();
        }

        if (! $invitation || $invitation->status !== 'pending') {
            return null;
        }

        if ($invitation->expires_at->isPast()) {
            $invitation->forceFill(['status' => 'expired'])->save();

            return null;
        }

        return $invitation;
    }

    public function pendingInvitationFromSession(Request $request): ?AccessInvitation
    {
        $invitation = AccessInvitation::query()
            ->whereKey((string) $request->session()->get('access_invitation_id', ''))
            ->first();

        if (! $invitation || $invitation->status !== 'pending') {
            return null;
        }

        if ($invitation->expires_at->isPast()) {
            $invitation->forceFill(['status' => 'expired'])->save();

            return null;
        }

        return $invitation;
    }

    public function prepareForCodeEntry(AccessInvitation $invitation, Request $request): ?AccessInvitation
    {
        $prepared = DB::transaction(function () use ($invitation) {
            $locked = AccessInvitation::query()->whereKey($invitation->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending' || $locked->expires_at->isPast()) {
                return null;
            }

            $locked->forceFill([
                'claimed_at' => null,
                'claimed_session_hash' => null,
                'enrollment_expires_at' => null,
            ])->save();

            return $locked;
        });

        $request->session()->forget([
            'access_enrollment',
            'access_provisional_handle',
            'sgc.passkeys.registration',
        ]);

        return $prepared;
    }

    public function claim(string $invitationId, string $code, Request $request): array
    {
        $result = DB::transaction(function () use ($invitationId, $code) {
            $invitation = AccessInvitation::query()->whereKey($invitationId)->lockForUpdate()->first();

            if (! $invitation || $invitation->status !== 'pending') {
                $this->consumeDummyHash($code);

                return null;
            }

            if ($invitation->expires_at->isPast()) {
                $invitation->forceFill(['status' => 'expired'])->save();
                $this->consumeDummyHash($code);

                return null;
            }

            $valid = Hash::driver('argon2id')->check($this->codeMaterial($code), $invitation->code_hash);

            if (! $valid) {
                $attempts = $invitation->failed_attempts + 1;
                $locked = $attempts >= (int) config('security.invitation_max_attempts', 5);
                $invitation->forceFill([
                    'failed_attempts' => $attempts,
                    'last_attempt_at' => now(),
                    'status' => $locked ? 'locked' : 'pending',
                ])->save();

                return ['invitation' => $invitation, 'failed' => true, 'locked' => $locked];
            }

            $grant = $this->base64Url(random_bytes(32));
            $invitation->forceFill([
                // O convite permanece reutilizavel ate a passkey ser salva.
                'status' => 'pending',
                'claimed_at' => now(),
                'claimed_session_hash' => null,
                'enrollment_expires_at' => now()->addSeconds((int) config('passkeys.enrollment_ttl', 600)),
                'last_attempt_at' => now(),
            ])->save();

            return ['invitation' => $invitation, 'grant' => $grant, 'failed' => false];
        });

        if (! $result || ($result['failed'] ?? false)) {
            $invitation = $result['invitation'] ?? null;
            $this->audit->record(($result['locked'] ?? false) ? 'access_invitation_locked' : 'access_invitation_code_failed', 'denied', [
                'tenant_id' => $invitation?->tenant_id,
                'associate_id' => $invitation?->associate_id,
                'invitation_id' => $invitation?->id,
            ], $request);
            throw new RuntimeException('Nao foi possivel validar este acesso.');
        }

        return $result;
    }

    public function bindClaimToSession(AccessInvitation $invitation, string $grant, Request $request): void
    {
        $fingerprint = $this->sessionFingerprint($request, $grant);

        $updated = AccessInvitation::query()
            ->whereKey($invitation->id)
            ->whereIn('status', ['pending', 'claimed'])
            ->whereNull('claimed_session_hash')
            ->update(['claimed_session_hash' => $fingerprint, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw new RuntimeException('Nao foi possivel validar este acesso.');
        }

        $this->audit->record('access_invitation_claimed', 'success', [
            'tenant_id' => $invitation->tenant_id,
            'associate_id' => $invitation->associate_id,
            'invitation_id' => $invitation->id,
        ], $request);
    }

    public function invitationForGrant(Request $request, bool $lock = false): AccessInvitation
    {
        $grant = (array) $request->session()->get('access_enrollment', []);
        $query = AccessInvitation::query()->whereKey($grant['invitation_id'] ?? '');
        if ($lock) {
            $query->lockForUpdate();
        }

        $invitation = $query->first();
        $rawGrant = is_string($grant['grant'] ?? null) ? $grant['grant'] : '';
        $expires = (int) ($grant['expires_at'] ?? 0);

        if ($invitation && $invitation->expires_at->isPast()) {
            $invitation->forceFill([
                'status' => 'expired',
                'claimed_session_hash' => null,
                'enrollment_expires_at' => null,
            ])->save();
        } elseif ($invitation
            && ($expires < now()->timestamp || ! $invitation->enrollment_expires_at?->isFuture())) {
            // Expirar a autorizacao de cadastro nao expira o convite de 24h.
            $invitation->forceFill([
                'status' => 'pending',
                'claimed_at' => null,
                'claimed_session_hash' => null,
                'enrollment_expires_at' => null,
            ])->save();
        }

        if (! $invitation
            || ! $invitation->isClaimed()
            || $expires < now()->timestamp
            || ! hash_equals((string) $invitation->claimed_session_hash, $this->sessionFingerprint($request, $rawGrant))) {
            throw new RuntimeException('O convite nao esta disponivel.');
        }

        return $invitation;
    }

    public function revoke(AccessInvitation $invitation, User $actor, Request $request): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = AccessInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['consumed', 'revoked', 'expired', 'locked'], true)) {
                throw new RuntimeException('Este convite nao pode ser revogado.');
            }
            $locked->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();
        });

        $this->audit->record('access_invitation_revoked', 'success', [
            'tenant_id' => $invitation->tenant_id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $invitation->membership?->user_id ?? $invitation->associate?->user_id,
            'associate_id' => $invitation->associate_id,
            'invitation_id' => $invitation->id,
        ], $request);
    }

    public function normalizeCode(string $code): string
    {
        return (string) preg_replace('/\D+/', '', trim($code));
    }

    private function codeMaterial(string $code): string
    {
        return $this->normalizeCode($code).(string) config('security.invitation_code_pepper');
    }

    private function consumeDummyHash(string $code): void
    {
        $hash = Hash::driver('argon2id')->make('invalid'.(string) config('security.invitation_code_pepper'));
        Hash::driver('argon2id')->check($this->codeMaterial($code), $hash);
    }

    private function randomCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function sessionFingerprint(Request $request, string $grant): string
    {
        return hash_hmac(
            'sha256',
            $request->session()->getId().'|'.$grant,
            (string) config('security.audit_pepper')
        );
    }
}
