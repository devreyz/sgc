<?php

namespace App\Http\Controllers;

use App\Services\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TenantController extends Controller
{
    protected $tenantResolver;

    public function __construct(TenantResolver $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
        $this->middleware('auth');
    }

    /**
     * Show tenant selection page
     */
    public function select()
    {
        $user = Auth::user();

        // Super admin sees all tenants
        if ($user->is_super_admin) {
            $tenants = \App\Models\Tenant::all();
        } else {
            $tenants = $user->tenants;
        }

        // If user has only one tenant, auto-select it
        if ($tenants->count() === 1) {
            return $this->switch($tenants->first()->id);
        }

        // If no tenants, show error
        if ($tenants->isEmpty()) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->with('error', 'Você não está associado a nenhuma organização. Entre em contato com o administrador.');
        }

        return view('tenant.select', compact('tenants'));
    }

    /**
     * Switch to a specific tenant
     */
    public function switch(Request $request, $tenantId = null)
    {
        // Allow tenant ID to be passed as parameter or in request
        $tenantId = $tenantId ?? $request->input('tenant_id');

        $user = Auth::user();
        $tenant = \App\Models\Tenant::find($tenantId);

        if (!$tenant) {
            return back()->with('error', 'Organização não encontrada.');
        }

        // Check if user has access to this tenant
        if (!$user->is_super_admin && !$tenant->hasUser($user)) {
            return back()->with('error', 'Você não tem acesso a essa organização.');
        }

        // Set tenant in session
        $this->tenantResolver->setTenant($tenantId);

        // Redirect to admin panel
        return redirect()->route('filament.admin.pages.dashboard')
            ->with('success', "Organização alterada para: {$tenant->name}");
    }

    /**
     * Clear tenant from session  
     */
    public function clear()
    {
        $this->tenantResolver->setTenant(null);

        return redirect()->route('tenant.select')
            ->with('success', 'Organização desvinculada.');
    }

    /**
     * Get current tenant info (API)
     */
    public function current()
    {
        $tenant = $this->tenantResolver->current();

        if (!$tenant) {
            return response()->json(['error' => 'No active tenant'], 404);
        }

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ]);
    }
}
