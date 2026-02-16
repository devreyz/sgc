<?php

namespace App\Http\Controllers;

use App\Services\TenantResolver;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    protected TenantResolver $tenantResolver;

    public function __construct(TenantResolver $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    /**
     * Show tenant selection page.
     */
    public function select()
    {
        $tenants = $this->tenantResolver->getAvailableTenants();

        return view('tenant.select', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Switch to a different tenant.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        try {
            $this->tenantResolver->setTenant($request->tenant_id);

            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                $tenant = $this->tenantResolver->current();
                return response()->json([
                    'message' => 'Organização alterada com sucesso.',
                    'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug] : null,
                ], 200);
            }

            return redirect()
                ->intended('/admin')
                ->with('success', 'Organização alterada com sucesso.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
