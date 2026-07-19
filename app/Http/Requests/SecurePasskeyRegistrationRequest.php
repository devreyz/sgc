<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;

class SecurePasskeyRegistrationRequest extends FormRequest
{
    private PublicKeyCredential $parsedCredential;

    private array $ceremonyContext = [];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'credential' => ['required', 'array'],
            'credential.id' => ['required', 'string'],
            'credential.rawId' => ['required', 'string'],
            'credential.type' => ['required', 'in:public-key'],
            'credential.response' => ['required', 'array'],
        ];
    }

    protected function passedValidation(): void
    {
        try {
            $this->parsedCredential = WebAuthn::fromJson(
                json_encode($this->input('credential'), JSON_THROW_ON_ERROR),
                PublicKeyCredential::class
            );
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'credential' => 'Nao foi possivel cadastrar a passkey.',
            ]);
        }
    }

    public function credential(): PublicKeyCredential
    {
        return $this->parsedCredential;
    }

    public function registrationOptions(string $expectedPurpose): PublicKeyCredentialCreationOptions
    {
        $this->ceremonyContext = (array) $this->session()->pull('sgc.passkeys.registration', []);

        if (! is_string($this->ceremonyContext['options'] ?? null)
            || (int) ($this->ceremonyContext['expires_at'] ?? 0) < now()->timestamp
            || ($this->ceremonyContext['purpose'] ?? null) !== $expectedPurpose) {
            throw ValidationException::withMessages([
                'credential' => 'Nao foi possivel cadastrar a passkey.',
            ]);
        }

        return WebAuthn::fromJson(
            $this->ceremonyContext['options'],
            PublicKeyCredentialCreationOptions::class
        );
    }

    public function ceremonyContext(): array
    {
        return $this->ceremonyContext;
    }
}
