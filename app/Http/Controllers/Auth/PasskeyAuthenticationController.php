<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Passkeys\VerifySecurePasskey;
use App\Exceptions\PasskeyChallengeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SecurePasskeyVerificationRequest;
use App\Models\TenantUser;
use App\Services\AuthenticationRedirector;
use App\Services\AuthenticationFailureLogger;
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

        return response()
            ->json(['options' => WebAuthn::toBrowserArray($options)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function verify(
        SecurePasskeyVerificationRequest $request,
        VerifySecurePasskey $verify,
        AuthenticationRedirector $redirector,
        SecurityAuditService $audit,
        AuthenticationFailureLogger $failureLogger,
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
                'context' => [
                    'passkey_id' => $passkey->id,
                    'platform' => $this->platform($request),
                ],
            ], $request);

            return response()->json(['redirect' => $redirector->pathAfterLogin($user)]);
        } catch (CounterException $exception) {
            $failureLogger->record(
                $request,
                'passkey',
                'server_verification',
                'counter_anomaly',
                422,
                $exception,
            );

            return response()->json([
                'message' => 'Nao foi possivel concluir a autenticacao.',
                'code' => 'PASSKEY_COUNTER_ANOMALY',
            ], 422);
        } catch (Throwable $exception) {
            $failureLogger->record(
                $request,
                'passkey',
                'server_verification',
                $this->failureReason($exception),
                422,
                $exception,
            );

            return response()->json([
                'message' => 'Nao foi possivel concluir a autenticacao.',
                'code' => 'PASSKEY_ASSERTION_REJECTED',
            ], 422);
        }
    }

    private function platform(Request $request): string
    {
        return $request->header('X-SGC-Platform') === 'android'
            ? 'android'
            : 'web';
    }

    private function failureReason(Throwable $exception): string
    {
        if ($exception instanceof PasskeyChallengeException) {
            return $exception->reason;
        }

        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'inactive account') => 'account_inactive_or_without_membership',
            str_contains($message, 'origin') => 'origin_rejected',
            str_contains($message, 'relying party'), str_contains($message, 'rp id') => 'rp_id_rejected',
            str_contains($message, 'signature') => 'signature_rejected',
            str_contains($message, 'user handle'), str_contains($message, 'userhandle') => 'user_handle_rejected',
            str_contains($message, 'credential') && str_contains($message, 'not found') => 'credential_not_found',
            str_contains($message, 'revoked') => 'credential_revoked',
            str_contains($message, 'expired') => 'credential_expired',
            default => 'assertion_rejected',
        };
    }
}
