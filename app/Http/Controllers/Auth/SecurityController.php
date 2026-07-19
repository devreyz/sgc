<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Passkey;
use App\Models\TenantUser;
use App\Support\PasskeyName;
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
        $activePasskeys = $passkeys->filter(fn (Passkey $passkey): bool => ! $passkey->revoked_at
            && (! $passkey->expires_at || $passkey->expires_at->isFuture()));
        $membership = TenantUser::query()
            ->where('user_id', $request->user()->id)
            ->where('status', true)
            ->when(session('tenant_id'), fn ($query, $tenantId) => $query->orderByRaw(
                'CASE WHEN tenant_id = ? THEN 0 ELSE 1 END',
                [$tenantId]
            ))
            ->first();
        $suggestedPasskeyName = PasskeyName::suggest($membership?->display_name);

        return view('auth.security', compact(
            'passkeys',
            'activePasskeys',
            'oauthAccounts',
            'recentlyAuthenticated',
            'suggestedPasskeyName',
        ));
    }
}
