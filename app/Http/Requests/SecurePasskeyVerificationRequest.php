<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

class SecurePasskeyVerificationRequest extends FormRequest
{
    private PublicKeyCredential $parsedCredential;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
                'credential' => 'Nao foi possivel concluir a autenticacao.',
            ]);
        }
    }

    public function credential(): PublicKeyCredential
    {
        return $this->parsedCredential;
    }

    public function verificationOptions(string $purpose = 'authentication'): PublicKeyCredentialRequestOptions
    {
        $envelope = (array) $this->session()->pull('sgc.passkeys.authentication', []);

        if (! is_string($envelope['options'] ?? null)
            || (int) ($envelope['expires_at'] ?? 0) < now()->timestamp
            || ($envelope['purpose'] ?? null) !== $purpose) {
            throw ValidationException::withMessages([
                'credential' => 'Nao foi possivel concluir a autenticacao.',
            ]);
        }

        return WebAuthn::fromJson($envelope['options'], PublicKeyCredentialRequestOptions::class);
    }
}
