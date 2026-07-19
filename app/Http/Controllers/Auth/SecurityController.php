<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Passkey;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function index(Request $request)
    {
        $passkeys = Passkey::withoutGlobalScope('usable')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();
        $oauthAccounts = $request->user()->oauthAccounts()->orderBy('provider')->get();

        $recentlyAuthenticated = $request->user()->recentlyAuthenticated();

        return view('auth.security', compact('passkeys', 'oauthAccounts', 'recentlyAuthenticated'));
    }
}
