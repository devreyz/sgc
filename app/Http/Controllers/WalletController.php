<?php

namespace App\Http\Controllers;

use App\Models\Associate;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment as Payment;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Show the digital wallet page.
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        // Get current tenant from route or session
        $tenant = null;
        if ($request->route('tenant')) {
            $routeTenant = $request->route('tenant');
            $tenant = is_string($routeTenant)
                ? Tenant::where('slug', $routeTenant)->first()
                : $routeTenant;
        } elseif (session('tenant_id')) {
            $tenant = Tenant::find(session('tenant_id'));
        }

        if (! $tenant) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        // Check if user is a member of this tenant
        if (! $user->belongsToTenant($tenant->id)) {
            return redirect()->route('home')->with('error', 'Você não tem acesso a esta organização.');
        }

        // Get user's relation with tenant
        $tenantUser = DB::table('tenant_user')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        // Get associate data if user is an associate
        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        // Calculate financial summary
        $financialSummary = $this->calculateFinancialSummary($user, $tenant);

        // Get recent transactions
        $recentTransactions = $this->getRecentTransactions($user, $tenant);

        // Get membership card data
        $memberNumber = str_pad($user->id, 6, '0', STR_PAD_LEFT);
        if ($associate) {
            $memberNumber = $associate->member_code 
                ?? $associate->registration_number 
                ?? str_pad($associate->id, 6, '0', STR_PAD_LEFT);
        }

        $membershipCard = [
            'member_since' => $tenantUser->created_at ?? now(),
            'member_number' => $memberNumber,
            'status' => 'active',
        ];

        return view('wallet.show', [
            'user' => $user,
            'tenant' => $tenant,
            'financialSummary' => $financialSummary,
            'recentTransactions' => $recentTransactions,
            'membershipCard' => $membershipCard,
        ]);
    }

    /**
     * Calculate financial summary for user in tenant.
     */
    private function calculateFinancialSummary($user, $tenant)
    {
        $summary = [
            'total_earned' => 0,
            'total_paid' => 0,
            'pending_payment' => 0,
            'balance' => 0,
        ];

        // If user is a service provider, calculate earnings
        if ($user->hasAnyRole(['service_provider', 'tratorista', 'motorista', 'diarista', 'tecnico'])) {
            // Get the service provider record for this user
            $serviceProvider = \App\Models\ServiceProvider::where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($serviceProvider) {
                $orders = ServiceOrder::where('tenant_id', $tenant->id)
                    ->where('service_provider_id', $serviceProvider->id)
                    ->get();

                $summary['total_earned'] = $orders->where('provider_payment_status', 'paid')->sum('provider_payment');
                $summary['pending_payment'] = $orders->whereIn('provider_payment_status', ['pending', 'approved'])->sum('provider_payment');
            }
        }

        // If user is an associate, calculate payments made and received
        if ($user->hasRole('associado')) {
            $associate = Associate::where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($associate) {
                // Payments made by the associate
                $payments = Payment::where('type', 'client')
                    ->whereHas('serviceOrder', function ($q) use ($associate, $tenant) {
                        $q->where('associate_id', $associate->id)
                            ->where('tenant_id', $tenant->id);
                    })
                    ->get();

                $summary['total_paid'] = $payments->where('status', 'paid')->sum('amount');

                // Payments received by the associate (for services provided)
                $associateProvider = \App\Models\ServiceProvider::where('user_id', $user->id)
                    ->where('tenant_id', $tenant->id)
                    ->first();

                if ($associateProvider) {
                    $providerOrders = ServiceOrder::where('tenant_id', $tenant->id)
                        ->where('service_provider_id', $associateProvider->id)
                        ->get();

                    $summary['total_earned'] += $providerOrders->where('provider_payment_status', 'paid')->sum('provider_payment');
                    $summary['pending_payment'] += $providerOrders->whereIn('provider_payment_status', ['pending', 'approved'])->sum('provider_payment');
                }
            }
        }

        $summary['balance'] = $summary['total_earned'] - $summary['total_paid'];

        return $summary;
    }

    /**
     * Get recent transactions for user in tenant.
     */
    private function getRecentTransactions($user, $tenant)
    {
        $transactions = collect();

        // Get service orders if provider
        if ($user->hasAnyRole(['service_provider', 'tratorista', 'motorista', 'diarista', 'tecnico'])) {
            // Get the service provider record for this user
            $serviceProvider = \App\Models\ServiceProvider::where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($serviceProvider) {
                $orders = ServiceOrder::where('tenant_id', $tenant->id)
                    ->where('service_provider_id', $serviceProvider->id)
                    ->whereNotNull('execution_date')
                    ->whereIn('status', ['in_progress', 'completed', 'approved'])
                    ->with(['service', 'associate'])
                    ->orderBy('execution_date', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($orders as $order) {
                    $transactions->push([
                        'date' => $order->execution_date ?? $order->created_at,
                        'description' => $order->service->name ?? 'Serviço',
                        'project' => $order->associate->name ?? 'N/A',
                        'amount' => $order->provider_payment,
                        'type' => 'income',
                        'status' => $order->provider_payment_status?->value ?? 'pending',
                    ]);
                }
            }
        }

        // Get payments if associate
        if ($user->hasRole('associado')) {
            $associate = Associate::where('user_id', $user->id)
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($associate) {
                $payments = Payment::where('type', 'client')
                    ->whereHas('serviceOrder', function ($q) use ($associate, $tenant) {
                        $q->where('associate_id', $associate->id)
                            ->where('tenant_id', $tenant->id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($payments as $payment) {
                    $transactions->push([
                        'date' => $payment->payment_date ?? $payment->created_at,
                        'description' => $payment->description ?? 'Pagamento',
                        'project' => $payment->serviceOrder?->service->name ?? 'N/A',
                        'amount' => $payment->amount,
                        'type' => 'expense',
                        'status' => $payment->status,
                    ]);
                }
            }
        }

        return $transactions->sortByDesc('date')->take(10)->values();
    }

    /**
     * Generate printable membership card.
     */
    public function printCard(Request $request)
    {
        $user = Auth::user();

        $tenant = null;
        if ($request->route('tenant')) {
            $routeTenant = $request->route('tenant');
            $tenant = is_string($routeTenant)
                ? Tenant::where('slug', $routeTenant)->first()
                : $routeTenant;
        }

        if (! $tenant || ! $user->belongsToTenant($tenant->id)) {
            return redirect()->route('home')->with('error', 'Acesso negado.');
        }

        // Check if user is an associate
        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $associate) {
            return redirect()->back()->with('error', 'Apenas associados podem imprimir a carteirinha.');
        }

        // Generate validation token if not exists
        if (! $associate->validation_token) {
            $associate->validation_token = \Illuminate\Support\Str::random(64);
            $associate->save();
        }

        // Generate validation URL
        $validationUrl = url('/validate-card/' . $associate->validation_token);

        return view('wallet.print-card', [
            'user' => $user,
            'tenant' => $tenant,
            'associate' => $associate,
            'validationUrl' => $validationUrl,
        ]);
    }
}
