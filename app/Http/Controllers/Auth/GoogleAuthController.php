<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\OrganizationAuthorizedEmail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $email = mb_strtolower($googleUser->email);

            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $user) {
                $buyerAccess = OrganizationAuthorizedEmail::withoutGlobalScope('tenant')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('active', true)
                    ->with('organization')
                    ->first();

                if (! $buyerAccess || ! $buyerAccess->organization?->active) {
                    Log::warning('Unauthorized Google OAuth login attempt', [
                        'email' => $googleUser->email,
                        'google_id' => $googleUser->id,
                    ]);

                    return redirect('/login')->with('error', 'Acesso negado. Usuario nao cadastrado no sistema. Entre em contato com o administrador.');
                }

                $user = User::create([
                    'name' => $googleUser->name ?: ($buyerAccess->name ?: $buyerAccess->email),
                    'email' => $googleUser->email,
                    'password' => Str::random(40),
                    'status' => true,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                ]);

                $user->tenants()->syncWithoutDetaching([
                    $buyerAccess->tenant_id => [
                        'is_admin' => false,
                        'roles' => json_encode(['buyer_organization']),
                    ],
                ]);
            }

            if (! $user->google_id) {
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                ]);
            }

            OrganizationAuthorizedEmail::withoutGlobalScope('tenant')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('active', true)
                ->get()
                ->each(function (OrganizationAuthorizedEmail $buyerAccess) use ($user) {
                    $tenantId = (int) $buyerAccess->tenant_id;
                    $roles = json_encode(array_unique(array_merge(
                        $user->getRolesForTenant($tenantId),
                        ['buyer_organization']
                    )));

                    if ($user->tenants()->where('tenant_id', $tenantId)->exists()) {
                        $user->tenants()->updateExistingPivot($tenantId, ['roles' => $roles]);
                    } else {
                        $user->tenants()->attach($tenantId, [
                            'is_admin' => false,
                            'roles' => $roles,
                        ]);
                    }
                });

            Auth::login($user, true);

            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames() : [];

            if (is_countable($roles) && count($roles) > 1) {
                return redirect()->intended(route('home'));
            }

            if ($user->hasRole('service_provider')) {
                return redirect()->intended('/provider/dashboard');
            }

            if ($user->hasRole('associate')) {
                return redirect()->intended('/associate/dashboard');
            }

            return redirect()->intended(route('home'));
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => request()->all(),
            ]);

            return redirect('/login')->with('error', 'Falha na autenticacao com Google. Tente novamente.');
        }
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
