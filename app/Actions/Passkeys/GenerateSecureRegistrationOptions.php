<?php

namespace App\Actions\Passkeys;

use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Passkeys;
use Webauthn\PublicKeyCredentialRpEntity;

class GenerateSecureRegistrationOptions extends GenerateRegistrationOptions
{
    protected function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            name: (string) config('passkeys.relying_party_name', 'ZeCoop SGC'),
            id: Passkeys::relyingPartyId(),
        );
    }
}
