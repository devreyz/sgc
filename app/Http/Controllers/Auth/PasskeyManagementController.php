<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Passkeys\GenerateSecureRegistrationOptions;
use App\Actions\Passkeys\StoreSecurePasskey;
use App\Actions\Passkeys\VerifySecurePasskey;
use App\Http\Controllers\Controller;
use App\Http\Requests\SecurePasskeyRegistrationRequest;
use App\Http\Requests\SecurePasskeyVerificationRequest;
use App\Models\Passkey;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;

class PasskeyManagementController extends Controller
{
    public function reauthenticationOptions(
        Request $request,
        GenerateVerificationOptions $generate,
    ): JsonResponse {
        $options = $generate($request->user());
        $request->session()->put('sgc.passkeys.authentication', [
            'purpose' => 'reauthentication',
            'user_id' => $request->user()->id,
            'options' => WebAuthn::toJson($options),
            'expires_at' => now()->addSeconds((int) config('passkeys.challenge_ttl', 300))->timestamp,
        ]);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function reauthenticate(
        SecurePasskeyVerificationRequest $request,
        VerifySecurePasskey $verify,
        SecurityAuditService $audit,
    ): JsonResponse {
        try {
            $passkey = $verify(
                $request->credential(),
                $request->verificationOptions('reauthentication'),
                $request->user(),
            );

            $request->user()->forceFill(['last_authenticated_at' => now()])->saveQuietly();
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $audit->record('security_reauthenticated', 'success', [
                'actor_user_id' => $request->user()->id,
                'target_user_id' => $request->user()->id,
                'context' => ['method' => 'passkey', 'passkey_id' => $passkey->id],
            ], $request);

            return response()->json(['message' => 'Identidade confirmada.']);
        } catch (Throwable $exception) {
            report($exception);
            $audit->record('webauthn_failed', 'denied', [
                'actor_user_id' => $request->user()->id,
                'context' => ['stage' => 'reauthentication'],
            ], $request);

            return response()->json(['message' => 'Nao foi possivel confirmar sua identidade.'], 422);
        }
    }

    public function options(Request $request, GenerateSecureRegistrationOptions $generate): JsonResponse
    {
        $this->authorize('manageOwn', Passkey::class);
        $options = $generate($request->user());
        $request->session()->put('sgc.passkeys.registration', [
            'purpose' => 'management',
            'user_id' => $request->user()->id,
            'options' => WebAuthn::toJson($options),
            'expires_at' => now()->addSeconds((int) config('passkeys.challenge_ttl', 300))->timestamp,
        ]);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function store(
        SecurePasskeyRegistrationRequest $request,
        StoreSecurePasskey $store,
        SecurityAuditService $audit,
    ): JsonResponse {
        $this->authorize('manageOwn', Passkey::class);
        try {
            $options = $request->registrationOptions('management');
            $context = $request->ceremonyContext();

            if ((int) ($context['user_id'] ?? 0) !== (int) $request->user()->id) {
                throw new \RuntimeException('Ceremony owner mismatch.');
            }

            $passkey = DB::transaction(fn () => $store(
                $request->user(),
                $request->string('name')->toString(),
                $request->credential(),
                $options,
            ));

            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $audit->record('passkey_registered', 'success', [
                'actor_user_id' => $request->user()->id,
                'target_user_id' => $request->user()->id,
                'context' => ['passkey_id' => $passkey->id],
            ], $request);

            return response()->json(['message' => 'Passkey adicionada com sucesso.']);
        } catch (Throwable $exception) {
            report($exception);
            $audit->record('webauthn_failed', 'denied', [
                'actor_user_id' => $request->user()->id,
                'context' => ['stage' => 'registration'],
            ], $request);

            return response()->json(['message' => 'Nao foi possivel cadastrar a passkey.'], 422);
        }
    }

    public function revoke(Request $request, string $passkey, SecurityAuditService $audit): JsonResponse
    {
        $record = Passkey::withoutGlobalScope('usable')
            ->where('user_id', $request->user()->id)
            ->whereKey($passkey)
            ->firstOrFail();
        $this->authorize('revoke', $record);

        if ($record->revoked_at) {
            return response()->json(['message' => 'Esta passkey ja foi revogada.'], 422);
        }

        $activePasskeys = Passkey::query()->where('user_id', $request->user()->id)->count();
        $hasOAuth = $request->user()->oauthAccounts()->exists();

        if ($activePasskeys <= 1 && ! $hasOAuth) {
            return response()->json([
                'message' => 'Adicione outro metodo de acesso antes de revogar esta passkey.',
            ], 422);
        }

        $record->forceFill([
            'revoked_at' => now(),
            'revoked_by_user_id' => $request->user()->id,
            'revocation_reason' => 'Revogada pelo titular',
        ])->save();

        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $audit->record('passkey_revoked', 'success', [
            'actor_user_id' => $request->user()->id,
            'target_user_id' => $request->user()->id,
            'context' => ['passkey_id' => $record->id],
        ], $request);

        return response()->json(['message' => 'Passkey revogada.']);
    }
}
