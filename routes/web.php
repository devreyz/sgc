<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Provider\ProviderDashboardController;
use App\Http\Controllers\Associate\AssociateDashboardController;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        
        // Priority: admins go to admin panel
        if ($user->hasAnyRole(['super_admin', 'admin', 'financeiro'])) {
            return redirect('/admin');
        }
        
        // Portal users
        if ($user->hasRole('service_provider')) {
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

// Service Provider Portal Routes
Route::prefix('provider')->name('provider.')->middleware(['auth', 'role:service_provider'])->group(function () {
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
