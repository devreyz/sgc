<?php

namespace App\Actions\Passkeys;

use Laravel\Passkeys\Actions\VerifyPasskey;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use Webauthn\CredentialRecord;

class VerifySecurePasskey extends VerifyPasskey
{
    public function updatePasskey(Passkey $passkey, CredentialRecord $source): void
    {
        $passkey->forceFill([
            'credential' => json_decode(WebAuthn::toJson($source), true, flags: JSON_THROW_ON_ERROR),
            'sign_count' => $source->counter,
            'backup_eligible' => (bool) $source->backupEligible,
            'backup_state' => (bool) $source->backupStatus,
            'user_verified' => (bool) $source->uvInitialized,
            'last_used_at' => now(),
        ])->save();
    }
}
