<?php

namespace App\Services;

use App\Contracts\GoogleIdTokenVerifier;
use App\Exceptions\GoogleTokenVerificationException;
use App\ValueObjects\GoogleIdentity;
use Firebase\JWT\JWT;
use Google\Client as GoogleClient;
use Throwable;

class GoogleApiIdTokenVerifier implements GoogleIdTokenVerifier
{
    public function verify(string $idToken, string $expectedNonce): GoogleIdentity
    {
        $webClientId = trim((string) config('services.google.web_client_id'));
        if ($webClientId === '') {
            throw new GoogleTokenVerificationException('missing_client_id');
        }

        $previousLeeway = JWT::$leeway;
        $skew = max(0, min(60, (int) config('security.google_jwt_clock_skew_seconds', 10)));
        JWT::$leeway = $skew;

        try {
            $claims = $this->verifiedClaims($idToken, $webClientId);
        } catch (Throwable) {
            throw new GoogleTokenVerificationException('invalid_token');
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        if (! is_array($claims)) {
            throw new GoogleTokenVerificationException('invalid_token');
        }

        $issuer = (string) ($claims['iss'] ?? '');
        if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new GoogleTokenVerificationException('invalid_issuer');
        }
        if (! hash_equals($webClientId, (string) ($claims['aud'] ?? ''))) {
            throw new GoogleTokenVerificationException('invalid_audience');
        }

        $authorizedParty = trim((string) ($claims['azp'] ?? ''));
        if ($authorizedParty !== '' && ! in_array($authorizedParty, $this->allowedAuthorizedParties($webClientId), true)) {
            throw new GoogleTokenVerificationException('invalid_authorized_party');
        }

        $now = time();
        $issuedAt = filter_var($claims['iat'] ?? null, FILTER_VALIDATE_INT);
        $expiresAt = filter_var($claims['exp'] ?? null, FILTER_VALIDATE_INT);
        if ($issuedAt === false || $issuedAt <= 0 || $issuedAt > $now + $skew) {
            throw new GoogleTokenVerificationException('invalid_issued_at');
        }
        if ($expiresAt === false || $expiresAt <= $now - $skew) {
            throw new GoogleTokenVerificationException('expired_token');
        }

        $subject = trim((string) ($claims['sub'] ?? ''));
        if ($subject === '' || strlen($subject) > 191) {
            throw new GoogleTokenVerificationException('missing_subject');
        }

        $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new GoogleTokenVerificationException('invalid_email');
        }
        if (($claims['email_verified'] ?? null) !== true) {
            throw new GoogleTokenVerificationException('unverified_email');
        }

        $nonce = trim((string) ($claims['nonce'] ?? ''));
        if ($expectedNonce === '' || $nonce === '' || ! hash_equals($expectedNonce, $nonce)) {
            throw new GoogleTokenVerificationException('invalid_nonce');
        }

        $avatarUrl = trim((string) ($claims['picture'] ?? ''));
        if ($avatarUrl === '' || ! filter_var($avatarUrl, FILTER_VALIDATE_URL) || ! str_starts_with($avatarUrl, 'https://')) {
            $avatarUrl = null;
        }

        return new GoogleIdentity(
            subject: $subject,
            email: $email,
            name: $this->nullableString($claims['name'] ?? null),
            avatarUrl: $avatarUrl,
        );
    }

    /** @return array<string, mixed>|false */
    protected function verifiedClaims(string $idToken, string $webClientId): array|false
    {
        return (new GoogleClient(['client_id' => $webClientId]))->verifyIdToken($idToken);
    }

    /** @return array<int, string> */
    private function allowedAuthorizedParties(string $webClientId): array
    {
        return array_values(array_unique(array_filter([
            $webClientId,
            trim((string) config('services.google.android_client_id')),
        ])));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
