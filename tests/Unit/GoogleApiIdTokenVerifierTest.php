<?php

namespace Tests\Unit;

use App\Exceptions\GoogleTokenVerificationException;
use App\Services\GoogleApiIdTokenVerifier;
use Tests\TestCase;

class GoogleApiIdTokenVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google.web_client_id', 'web-client.apps.googleusercontent.com');
        config()->set('services.google.android_client_id', 'android-client.apps.googleusercontent.com');
    }

    public function test_verified_google_claims_are_converted_to_a_trusted_identity(): void
    {
        $identity = $this->verifier($this->claims())->verify('signed-token', 'expected-nonce');

        $this->assertSame('google-subject', $identity->subject);
        $this->assertSame('member@example.test', $identity->email);
        $this->assertSame('Member Name', $identity->name);
        $this->assertSame('https://lh3.googleusercontent.com/avatar', $identity->avatarUrl);
        $this->assertFalse($identity->emailAuthoritative);
    }

    public function test_google_workspace_hosted_domain_marks_email_as_authoritative(): void
    {
        $claims = $this->claims();
        $claims['hd'] = 'example.test';

        $identity = $this->verifier($claims)->verify('signed-token', 'expected-nonce');

        $this->assertTrue($identity->emailAuthoritative);
    }

    public function test_unverified_email_is_rejected(): void
    {
        $claims = $this->claims();
        $claims['email_verified'] = false;

        $this->assertVerificationFails(
            'unverified_email',
            fn () => $this->verifier($claims)->verify('signed-token', 'expected-nonce'),
        );
    }

    public function test_wrong_nonce_is_rejected(): void
    {
        $this->assertVerificationFails(
            'invalid_nonce',
            fn () => $this->verifier($this->claims())->verify('signed-token', 'different-nonce'),
        );
    }

    public function test_unknown_android_authorized_party_is_rejected(): void
    {
        $claims = $this->claims();
        $claims['azp'] = 'attacker-client.apps.googleusercontent.com';

        $this->assertVerificationFails(
            'invalid_authorized_party',
            fn () => $this->verifier($claims)->verify('signed-token', 'expected-nonce'),
        );
    }

    public function test_future_issued_at_is_rejected(): void
    {
        $claims = $this->claims();
        $claims['iat'] = time() + 120;

        $this->assertVerificationFails(
            'invalid_issued_at',
            fn () => $this->verifier($claims)->verify('signed-token', 'expected-nonce'),
        );
    }

    /** @param array<string, mixed> $claims */
    private function verifier(array $claims): GoogleApiIdTokenVerifier
    {
        return new class($claims) extends GoogleApiIdTokenVerifier
        {
            /** @param array<string, mixed> $claims */
            public function __construct(private readonly array $claims) {}

            protected function verifiedClaims(string $idToken, string $webClientId): array|false
            {
                return $this->claims;
            }
        };
    }

    /** @return array<string, mixed> */
    private function claims(): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client.apps.googleusercontent.com',
            'azp' => 'android-client.apps.googleusercontent.com',
            'iat' => time() - 10,
            'exp' => time() + 3500,
            'sub' => 'google-subject',
            'email' => 'Member@Example.Test',
            'email_verified' => true,
            'nonce' => 'expected-nonce',
            'name' => 'Member Name',
            'picture' => 'https://lh3.googleusercontent.com/avatar',
        ];
    }

    private function assertVerificationFails(string $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected Google token verification to fail.');
        } catch (GoogleTokenVerificationException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }
}
