<?php

namespace App\Actions\Passkeys;

use App\Services\SecurityAuditService;
use App\Support\PasskeyName;
use Laravel\Passkeys\Actions\StorePasskey;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Laravel\Passkeys\Support\WebAuthn;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\CredentialRecord;

class StoreSecurePasskey extends StorePasskey
{
    public function __construct(private readonly SecurityAuditService $audit) {}

    public function createPasskey(PasskeyUser $user, string $name, CredentialRecord $source): Passkey
    {
        $credentialId = Base64UrlSafe::encodeUnpadded($source->publicKeyCredentialId);
        $name = PasskeyName::validate($name);

        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => $credentialId,
            'credential' => json_decode(WebAuthn::toJson($source), true, flags: JSON_THROW_ON_ERROR),
            'public_key' => Base64UrlSafe::encodeUnpadded($source->credentialPublicKey),
            'sign_count' => $source->counter,
            'transports' => $source->transports,
            'aaguid' => $source->aaguid->toRfc4122(),
            'backup_eligible' => (bool) $source->backupEligible,
            'backup_state' => (bool) $source->backupStatus,
            'user_verified' => (bool) $source->uvInitialized,
            'rp_id' => Passkeys::relyingPartyId(),
            'created_ip_hash' => $this->audit->hashIp(request()->ip()),
            'expires_at' => now()->addDays((int) config('passkeys.lifetime_days', 365)),
        ]);
    }
}
