<?php

use App\Http\Controllers\Associate\AssociateDashboardController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Delivery\DeliveryRegistrationController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\Provider\ProviderDashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();

        // Priority: admins go to admin panel
        if ($user->hasAnyRole(['super_admin', 'admin', 'financeiro'])) {
            return redirect('/admin');
        }

        // Registrador de entregas vai direto para o dashboard
        if ($user->hasRole('registrador_entregas') && !$user->hasAnyRole(['service_provider', 'associado'])) {
            return redirect('/delivery');
        }

        // Portal users - Service providers têm prioridade
        if ($user->hasAnyRole(['service_provider', 'tratorista', 'motorista', 'diarista', 'tecnico'])) {
            return redirect('/provider/dashboard');
        } elseif ($user->hasRole('associado')) {
            return redirect('/associate/dashboard');
        }

        return redirect('/admin');
    }

    return view('auth.login');
})->name('home');

// Login route (named) — used by authentication redirects
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// Google OAuth Routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

// Public Document Verification Routes (no authentication required)
Route::get('/verify/{hash}', [DocumentVerificationController::class, 'verify'])->name('document.verify');
Route::get('/qrcode/{hash}', [DocumentVerificationController::class, 'qrcode'])->name('document.qrcode');

// Service Provider Portal Routes
Route::prefix('provider')->name('provider.')->middleware(['auth', 'any.role:service_provider,tratorista,motorista,diarista,tecnico'])->group(function () {
    Route::get('/dashboard', [ProviderDashboardController::class, 'index'])->name('dashboard');

    // Service Orders - Provider can create and manage
    Route::get('/orders', [ProviderDashboardController::class, 'orders'])->name('orders');
    Route::get('/orders/create', [ProviderDashboardController::class, 'createOrder'])->name('orders.create');
    Route::post('/orders', [ProviderDashboardController::class, 'storeOrder'])->name('orders.store');
    Route::get('/orders/{order}', [ProviderDashboardController::class, 'showOrder'])->name('orders.show');
    Route::get('/orders/{order}/edit', [ProviderDashboardController::class, 'editOrder'])->name('orders.edit');
    Route::put('/orders/{order}', [ProviderDashboardController::class, 'updateOrder'])->name('orders.update');
    Route::post('/orders/{order}/complete', [ProviderDashboardController::class, 'completeOrder'])->name('orders.complete');

    // Work records
    Route::get('/orders/{order}/work', [ProviderDashboardController::class, 'createWork'])->name('work.create');
    Route::post('/orders/{order}/work', [ProviderDashboardController::class, 'storeWork'])->name('work.store');
    Route::get('/works', [ProviderDashboardController::class, 'works'])->name('works');
});

// Associate Portal Routes
Route::prefix('associate')->name('associate.')->middleware(['auth', 'role:associado'])->group(function () {
    Route::get('/dashboard', [AssociateDashboardController::class, 'index'])->name('dashboard');
    Route::get('/projects', [AssociateDashboardController::class, 'projects'])->name('projects');
    Route::get('/projects/{project}', [AssociateDashboardController::class, 'showProject'])->name('projects.show');
    Route::get('/deliveries', [AssociateDashboardController::class, 'deliveries'])->name('deliveries');
    Route::get('/ledger', [AssociateDashboardController::class, 'ledger'])->name('ledger');
});

// Delivery Registration Routes (Mobile-friendly for delivery recorders)
Route::prefix('delivery')->name('delivery.')->middleware(['auth', 'any.role:registrador_entregas'])->group(function () {
    Route::get('/', [DeliveryRegistrationController::class, 'index'])->name('dashboard');
    Route::get('/register/{project?}', [DeliveryRegistrationController::class, 'register'])->name('register');
    Route::post('/register', [DeliveryRegistrationController::class, 'store'])->name('store');
    Route::get('/projects/{project}/demands', [DeliveryRegistrationController::class, 'getProjectDemands'])->name('projects.demands');
    Route::get('/projects/{project}/associates/{associate}/deliveries', [DeliveryRegistrationController::class, 'getAssociateDeliveries'])->name('associates.deliveries');
    Route::get('/projects/{project}/deliveries', [DeliveryRegistrationController::class, 'projectDeliveries'])->name('projects.deliveries');
    Route::post('/deliveries/{delivery}/approve', [DeliveryRegistrationController::class, 'approveDelivery'])->name('deliveries.approve');
    Route::post('/deliveries/{delivery}/reject', [DeliveryRegistrationController::class, 'rejectDelivery'])->name('deliveries.reject');
});
