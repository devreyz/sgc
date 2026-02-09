<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\RedirectResponse;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth page
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // SECURITY: Only allow pre-registered users to login (no auto-registration)
            $user = User::where('email', $googleUser->email)->first();

            if (!$user) {
                Log::warning('Unauthorized Google OAuth login attempt', [
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                ]);
                
                return redirect('/login')->with('error', 'Acesso negado. Usuário não cadastrado no sistema. Entre em contato com o administrador.');
            }

            // Update Google ID and avatar if not set
            if (!$user->google_id) {
                $user->update([
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                ]);
            }

            Auth::login($user, true);

            // Redirect based on user role/type
            if ($user->hasRole('service_provider')) {
                return redirect()->intended('/provider/dashboard');
            } elseif ($user->hasRole('associate')) {
                return redirect()->intended('/associate/dashboard');
            }

            return redirect()->intended('/admin');

        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => request()->all(),
            ]);

            return redirect('/login')->with('error', 'Falha na autenticação com Google. Tente novamente.');
        }
    }

    /**
     * Logout
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
