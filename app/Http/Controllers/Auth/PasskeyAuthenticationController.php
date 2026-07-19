<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Passkeys\VerifySecurePasskey;
use App\Http\Controllers\Controller;
use App\Http\Requests\SecurePasskeyVerificationRequest;
use App\Models\TenantUser;
use App\Services\AuthenticationRedirector;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Support\WebAuthn;
use Throwable;
use Webauthn\Exception\CounterException;

class PasskeyAuthenticationController extends Controller
{
    public function options(Request $request, GenerateVerificationOptions $generate): JsonResponse
    {
        $options = $generate();
        $request->session()->put('sgc.passkeys.authentication', [
            'purpose' => 'authentication',
            'options' => WebAuthn::toJson($options),
            'expires_at' => now()->addSeconds((int) config('passkeys.challenge_ttl', 300))->timestamp,
        ]);

        return response()->json(['options' => WebAuthn::toBrowserArray($options)]);
    }

    public function verify(
        SecurePasskeyVerificationRequest $request,
        VerifySecurePasskey $verify,
        AuthenticationRedirector $redirector,
        SecurityAuditService $audit,
    ): JsonResponse {
        try {
            $passkey = $verify($request->credential(), $request->verificationOptions());
            $user = $passkey->user;

            $hasMembership = $user && ($user->isSuperAdmin() || TenantUser::query()
                ->where('user_id', $user->id)
                ->where('status', true)
                ->exists());

            if (! $user?->status || ! $hasMembership) {
                throw new \RuntimeException('Inactive account.');
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $user->forceFill(['last_authenticated_at' => now()])->saveQuietly();

            $audit->record('passkey_used', 'success', [
                'target_user_id' => $user->id,
                'context' => ['passkey_id' => $passkey->id],
            ], $request);

            return response()->json(['redirect' => $redirector->pathFor($user)]);
        } catch (CounterException $exception) {
            report($exception);
            $audit->record('webauthn_sign_count_anomaly', 'denied', [
                'context' => ['stage' => 'authentication'],
            ], $request);

            return response()->json([
                'message' => 'Nao foi possivel concluir a autenticacao.',
            ], 422);
        } catch (Throwable $exception) {
            report($exception);
            $audit->record('webauthn_failed', 'denied', [
                'context' => ['stage' => 'authentication'],
            ], $request);

            return response()->json([
                'message' => 'Nao foi possivel concluir a autenticacao.',
            ], 422);
        }
    }
}
