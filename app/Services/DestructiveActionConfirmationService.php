<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DestructiveActionConfirmationService
{
    private const SESSION_KEY = 'sgc.destructive_confirmation';

    private const TTL_SECONDS = 300;

    /** @return array{id: string, code: ?string, expires_in: int, passkey_available: bool} */
    public function issue(Request $request, string $action, int $subjectId): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $id = (string) Str::uuid();
        $code = strtoupper(Str::random(3)).'-'.random_int(100, 999);
        $issuedAt = now()->timestamp;
        $passkeyAvailable = $user->passkeys()->exists();
        $request->session()->put(self::SESSION_KEY, [
            'id' => $id,
            'action' => $action,
            'subject_id' => $subjectId,
            'user_id' => (int) $user->id,
            'tenant_id' => (int) $request->session()->get('tenant_id'),
            'code_hash' => hash('sha256', $code),
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + self::TTL_SECONDS,
            'attempts' => 0,
            'passkey_required' => $passkeyAvailable,
        ]);

        return [
            'id' => $id,
            // A conta com passkey não recebe uma rota alternativa mais fraca.
            'code' => $passkeyAvailable ? null : $code,
            'expires_in' => self::TTL_SECONDS,
            'passkey_available' => $passkeyAvailable,
        ];
    }

    public function verifyAndConsume(
        Request $request,
        string $action,
        int $subjectId,
        ?string $confirmationId,
        ?string $confirmationCode,
    ): bool {
        $challenge = (array) $request->session()->get(self::SESSION_KEY, []);
        $user = $request->user();
        $validContext = $user instanceof User
            && filled($confirmationId)
            && hash_equals((string) ($challenge['id'] ?? ''), (string) $confirmationId)
            && hash_equals((string) ($challenge['action'] ?? ''), $action)
            && (int) ($challenge['subject_id'] ?? 0) === $subjectId
            && (int) ($challenge['user_id'] ?? 0) === (int) $user->id
            && (int) ($challenge['tenant_id'] ?? 0) === (int) $request->session()->get('tenant_id')
            && (int) ($challenge['expires_at'] ?? 0) >= now()->timestamp
            && (int) ($challenge['attempts'] ?? 0) < 5;

        if (! $validContext) {
            $request->session()->forget(self::SESSION_KEY);

            return false;
        }

        $issuedAt = (int) ($challenge['issued_at'] ?? 0);
        $confirmedByPasskey = $user->last_authenticated_at?->timestamp >= $issuedAt;
        $confirmedByCode = ! (bool) ($challenge['passkey_required'] ?? false)
            && filled($confirmationCode)
            && hash_equals(
                (string) ($challenge['code_hash'] ?? ''),
                hash('sha256', strtoupper(trim((string) $confirmationCode))),
            );

        if (! $confirmedByPasskey && ! $confirmedByCode) {
            $challenge['attempts'] = (int) ($challenge['attempts'] ?? 0) + 1;
            $request->session()->put(self::SESSION_KEY, $challenge);

            return false;
        }

        $request->session()->forget(self::SESSION_KEY);

        return true;
    }
}
