<?php

namespace App\Http\Controllers\Auth;

use App\Auth\SecureGoogleProvider;
use App\Http\Controllers\Controller;
use App\Services\AuthenticationRedirector;
use App\Services\GoogleAccountService;
use App\Services\SecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (app()->environment('production') && ! $request->isSecure()) {
            abort(400, 'HTTPS e obrigatorio para autenticacao.');
        }

        $intent = $request->string('intent')->toString() ?: 'login';
        if (! in_array($intent, ['login', 'link', 'reauth'], true)) {
            $intent = 'login';
        }

        if (in_array($intent, ['link', 'reauth'], true) && ! $request->user()) {
            return redirect()->route('login')->with('error', 'Entre na sua conta para continuar.');
        }

        if ($intent === 'link' && ! $request->user()->recentlyAuthenticated()) {
            return redirect()->route('security.index')
                ->with('error', 'Confirme sua identidade antes de vincular o Google.');
        }

        $nonce = Base64UrlSafe::encodeUnpadded(random_bytes(32));
        $request->session()->put([
            'google_oidc_nonce' => $nonce,
            'google_oauth_intent' => $intent,
            'google_oauth_user_id' => $request->user()?->id,
        ]);

        return $this->provider()
            ->with(['nonce' => $nonce, 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(
        Request $request,
        AuthenticationRedirector $redirector,
        GoogleAccountService $accounts,
        SecurityAuditService $audit,
    ): RedirectResponse {
        try {
            $intent = (string) $request->session()->pull('google_oauth_intent', 'login');
            $expectedUserId = $request->session()->pull('google_oauth_user_id');
            $googleUser = $this->provider()->user();
            $claims = $googleUser->getRaw();
            $subject = (string) ($claims['sub'] ?? '');
            $email = isset($claims['email']) ? mb_strtolower((string) $claims['email']) : null;

            [$user] = $accounts->resolve(
                $intent,
                $subject,
                $email,
                Auth::user(),
                $expectedUserId,
            );
            $attributes = ['last_authenticated_at' => now()];
            if (! $user->hasLocallyStoredAvatar() && $googleUser->avatar) {
                $attributes['avatar'] = $googleUser->avatar;
            }
            $user->forceFill($attributes)->saveQuietly();

            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            $audit->record($intent === 'link' ? 'google_linked' : 'google_login', 'success', [
                'target_user_id' => $user->id,
                'context' => ['intent' => $intent],
            ], $request);

            if (in_array($intent, ['link', 'reauth'], true)) {
                return redirect()->route('security.index')->with('success', 'Identidade confirmada.');
            }

            return redirect()->to($redirector->pathFor($user));
        } catch (Throwable $exception) {
            report($exception);
            $request->session()->forget(['google_oidc_nonce', 'google_oauth_intent', 'google_oauth_user_id']);
            $audit->record('google_login_failed', 'denied', [
                'context' => ['stage' => 'callback'],
            ], $request);

            return redirect()->route('login')
                ->with('error', 'Nao foi possivel concluir a autenticacao.');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function provider(): AbstractProvider
    {
        return Socialite::buildProvider(SecureGoogleProvider::class, config('services.google'))
            ->enablePKCE();
    }
}
