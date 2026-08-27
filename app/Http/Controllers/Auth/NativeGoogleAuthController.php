<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\GoogleIdTokenVerifier;
use App\Exceptions\AccountProofRequiredException;
use App\Exceptions\GoogleTokenVerificationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\NativeGoogleLoginRequest;
use App\Services\AuthenticationRedirector;
use App\Services\GoogleAccountService;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;

class NativeGoogleAuthController extends Controller
{
    private const NONCE_SESSION_KEY = 'google_native_nonce';

    private const NONCE_EXPIRY_SESSION_KEY = 'google_native_nonce_expires_at';

    public function challenge(Request $request): JsonResponse
    {
        $this->requireSecureTransport($request);

        $ttl = max(60, min(600, (int) config('security.native_google_nonce_ttl_seconds', 300)));
        $nonce = Base64UrlSafe::encodeUnpadded(random_bytes(32));

        $request->session()->put([
            self::NONCE_SESSION_KEY => $nonce,
            self::NONCE_EXPIRY_SESSION_KEY => now()->addSeconds($ttl)->timestamp,
        ]);

        return response()->json(['nonce' => $nonce, 'expires_in' => $ttl])
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }

    public function login(
        NativeGoogleLoginRequest $request,
        GoogleIdTokenVerifier $verifier,
        GoogleAccountService $accounts,
        AuthenticationRedirector $redirector,
        SecurityAuditService $audit,
    ): JsonResponse {
        $this->requireSecureTransport($request);

        $nonce = (string) $request->session()->pull(self::NONCE_SESSION_KEY, '');
        $expiresAt = (int) $request->session()->pull(self::NONCE_EXPIRY_SESSION_KEY, 0);

        if ($nonce === '' || $expiresAt < now()->timestamp) {
            $audit->record('google_native_login_failed', 'denied', [
                'context' => ['stage' => 'challenge'],
            ], $request);
            $this->logFailure($request, 'challenge', 'expired_or_missing_nonce');

            return response()->json([
                'message' => 'A solicitacao de login expirou. Tente novamente.',
            ], 422);
        }

        try {
            $identity = $verifier->verify((string) $request->validated('id_token'), $nonce);
            [$user] = $accounts->resolve(
                'login',
                $identity->subject,
                $identity->email,
                null,
                null,
                $identity->emailAuthoritative,
            );

            $attributes = ['last_authenticated_at' => now()];
            if (! $user->hasLocallyStoredAvatar() && $identity->avatarUrl) {
                $attributes['avatar'] = $identity->avatarUrl;
            }
            $user->forceFill($attributes)->saveQuietly();

            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            $audit->record('google_native_login', 'success', [
                'target_user_id' => $user->id,
                'context' => ['platform' => 'android'],
            ], $request);

            return response()->json([
                'authenticated' => true,
                'redirect' => $redirector->pathAfterLogin($user),
            ]);
        } catch (GoogleTokenVerificationException $exception) {
            $audit->record('google_native_login_failed', 'denied', [
                'context' => ['stage' => 'token_verification', 'reason' => $exception->reason],
            ], $request);
            $this->logFailure($request, 'token_verification', $exception->reason);

            return response()->json([
                'message' => 'Nao foi possivel validar esta conta Google.',
                'code' => $exception->reason,
            ], 422);
        } catch (AccountProofRequiredException) {
            $audit->record('google_native_login_failed', 'denied', [
                'context' => ['stage' => 'account_proof_required'],
            ], $request);
            $this->logFailure($request, 'account_proof_required');

            return response()->json([
                'message' => 'Esta conta Google ainda nao esta vinculada a um acesso autorizado.',
            ], 403);
        } catch (Throwable $exception) {
            Log::channel('app')->error('Unexpected native Google authentication error.', [
                'exception' => $exception,
                'platform' => 'android',
                'ip_hash' => $audit->hashIp($request->ip()),
            ]);
            $audit->record('google_native_login_failed', 'denied', [
                'context' => ['stage' => 'account_resolution'],
            ], $request);
            $this->logFailure($request, 'account_resolution', 'unexpected_error');

            return response()->json(['message' => 'Nao foi possivel concluir a autenticacao.'], 403);
        }
    }

    private function requireSecureTransport(Request $request): void
    {
        if (app()->environment('production') && ! $request->isSecure()) {
            abort(400, 'HTTPS e obrigatorio para autenticacao.');
        }
    }

    private function logFailure(Request $request, string $stage, ?string $reason = null): void
    {
        Log::channel('app')->warning('Native Google authentication denied.', [
            'stage' => $stage,
            'reason' => $reason,
            'ip_hash' => app(SecurityAuditService::class)->hashIp($request->ip()),
            'correlation_id' => $request->attributes->get('security_correlation_id'),
            'platform' => 'android',
        ]);
    }
}
