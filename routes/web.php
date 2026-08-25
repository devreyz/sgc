<?php

use App\Http\Controllers\Accounting\AccountingFiscalController;
use App\Http\Controllers\Accounting\AccountingPortalController;
use App\Http\Controllers\Associate\AssociateDashboardController;
use App\Http\Controllers\Associate\AssociatePortalDataController;
use App\Http\Controllers\Associate\AssociateProjectPortalController;
use App\Http\Controllers\Auth\AccessInvitationAdminController;
use App\Http\Controllers\Auth\AccessInvitationController;
use App\Http\Controllers\Auth\AuthenticationStateController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\GoogleDriveOAuthController;
use App\Http\Controllers\Auth\InvitationPasskeyController;
use App\Http\Controllers\Auth\PasskeyAuthenticationController;
use App\Http\Controllers\Auth\PasskeyManagementController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\Buyer\BuyerBillingAuthorizationController;
use App\Http\Controllers\Buyer\BuyerPortalController;
use App\Http\Controllers\Delivery\AssociateProjectController;
use App\Http\Controllers\Delivery\DeliveryConferenceSheetController;
use App\Http\Controllers\Delivery\DeliveryRegistrationController;
use App\Http\Controllers\Delivery\DeliveryReportController;
use App\Http\Controllers\Delivery\DeliverySheetController;
use App\Http\Controllers\Delivery\DeliveryViewerController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\Finance\FinanceManagementController;
use App\Http\Controllers\Finance\FinancialPortalController;
use App\Http\Controllers\FinancialReceiptController;
use App\Http\Controllers\HubController;
use App\Http\Controllers\MemberCardValidationController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\Pdv\PdvController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Provider\ProviderDashboardController;
use App\Http\Controllers\PublicFormController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Secretary\SecretaryPortalController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WalletController;
use App\Services\AuthenticationRedirector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Route::get('/storage/{path}', function (string $path) {
    $path = ltrim(str_replace('\\', '/', $path), '/');

    abort_if($path === '' || str_contains($path, '..') || Str::startsWith($path, '/'), 404);

    $disk = Storage::disk('public');

    abort_unless($disk->exists($path), 404);

    return response()->file($disk->path($path), [
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('storage.public');

// Home route - mostra welcome ou hub se logado
Route::get('/', [HubController::class, 'index'])->middleware('public.headers')->name('home');
Route::get('/sitemap.xml', function () {
    $pages = [
        '/',
        '/legal/',
        '/legal/privacidade.html',
        '/legal/termos.html',
        '/legal/cookies.html',
        '/legal/direitos.html',
        '/legal/exclusao.html',
        '/legal/uso-aceitavel.html',
        '/legal/seguranca.html',
        '/legal/subprocessadores.html',
        '/legal/acessibilidade.html',
    ];

    $urls = collect($pages)->map(fn (string $path) => '<url><loc>'.e(url($path)).'</loc></url>')->implode('');

    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
})->middleware('public.headers')->name('sitemap');
Route::post('/contato', [PublicFormController::class, 'contact'])
    ->middleware('throttle:5,10')
    ->name('public.contact');
Route::post('/solicitacoes-privacidade', [PublicFormController::class, 'privacy'])
    ->middleware('throttle:3,10')
    ->name('public.privacy');

// Login route (named) — used by authentication redirects
Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->to(app(AuthenticationRedirector::class)->pathFor(auth()->user()));
    }

    return view('auth.login');
})->name('login');

Route::get('/auth/state', AuthenticationStateController::class)
    ->middleware('throttle:auth-state')
    ->name('auth.state');

Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// Google OAuth Routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:google-callback')
    ->name('auth.google.callback');
Route::get('/auth/google-drive/callback', [GoogleDriveOAuthController::class, 'callback'])
    ->middleware(['auth', 'throttle:google-drive-oauth'])
    ->name('auth.google-drive.callback');
Route::post('/logout', [GoogleAuthController::class, 'logout'])->name('logout');

Route::middleware(['guest', 'webauthn.config'])->group(function () {
    Route::get('/auth/passkey/options', [PasskeyAuthenticationController::class, 'options'])
        ->middleware('throttle:passkey-options')->name('auth.passkey.options');
    Route::post('/auth/passkey/verify', [PasskeyAuthenticationController::class, 'verify'])
        ->middleware('throttle:passkey-verify')->name('auth.passkey.verify');
});

Route::middleware('invitation.headers')->group(function () {
    Route::get('/acesso/verificar', [AccessInvitationController::class, 'show'])
        ->name('access.invitation.verify');
    Route::post('/acesso/verificar-codigo', [AccessInvitationController::class, 'verifyCode'])
        ->middleware('throttle:invitation-code')->name('access.invitation.code');
    Route::get('/acesso/criar-passkey', [InvitationPasskeyController::class, 'show'])
        ->name('access.invitation.passkey');
    Route::get('/acesso/passkey/options', [InvitationPasskeyController::class, 'options'])
        ->middleware(['webauthn.config', 'throttle:passkey-options'])->name('access.invitation.passkey.options');
    Route::post('/acesso/passkey', [InvitationPasskeyController::class, 'store'])
        ->middleware(['webauthn.config', 'throttle:passkey-verify'])->name('access.invitation.passkey.store');
    Route::get('/acesso/{token}', [AccessInvitationController::class, 'consume'])
        ->middleware('throttle:invitation-token')->name('access.invitation.consume');
});

// Tenant Selection Routes (authenticated users)
Route::middleware('auth')->group(function () {
    Route::get('/financial-receipts/{financialReceipt}/print', [FinancialReceiptController::class, 'print'])
        ->name('financial-receipts.print');
    Route::get('/tenant/select', [TenantController::class, 'select'])->name('tenant.select');
    Route::post('/tenant/switch', [TenantController::class, 'switch'])->name('tenant.switch');
    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::get('/notifications/push/status', [PushSubscriptionController::class, 'status'])->name('notifications.push.status');
    Route::post('/notifications/push/subscriptions', [PushSubscriptionController::class, 'store'])
        ->middleware('throttle:20,1')->name('notifications.push.store');
    Route::delete('/notifications/push/subscriptions', [PushSubscriptionController::class, 'destroy'])
        ->middleware('throttle:20,1')->name('notifications.push.destroy');
    Route::get('/security/reauth/passkey/options', [PasskeyManagementController::class, 'reauthenticationOptions'])
        ->middleware(['webauthn.config', 'throttle:passkey-options'])->name('security.reauth.passkey.options');
    Route::post('/security/reauth/passkey', [PasskeyManagementController::class, 'reauthenticate'])
        ->middleware(['webauthn.config', 'throttle:passkey-verify'])->name('security.reauth.passkey.store');
    Route::middleware(['auth.recent', 'webauthn.config'])->group(function () {
        Route::get('/security/passkeys/options', [PasskeyManagementController::class, 'options'])
            ->middleware('throttle:passkey-options')->name('security.passkeys.options');
        Route::post('/security/passkeys', [PasskeyManagementController::class, 'store'])
            ->middleware('throttle:passkey-verify')->name('security.passkeys.store');
        Route::delete('/security/passkeys/{passkey}', [PasskeyManagementController::class, 'revoke'])
            ->name('security.passkeys.revoke');
    });
});

// Public Document Verification Routes (no authentication required)
Route::get('/verify/{hash}', [DocumentVerificationController::class, 'verify'])->name('document.verify');
Route::get('/qrcode/{hash}', [DocumentVerificationController::class, 'qrcode'])->name('document.qrcode');

// Public Member Card Validation Route (no authentication required)
Route::get('/validate-card/{token}', [MemberCardValidationController::class, 'verifyCard'])->name('member-card.validate');

// ===========================================================================================
// ROTAS COM PREFIXO DE TENANT (SLUG) - Portais Legados (Associate, Provider, Delivery)
// ===========================================================================================

Route::prefix('{tenant:slug}')->middleware(['auth', 'tenant.slug'])->group(function () {

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationCenterController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationCenterController::class, 'unreadCount'])->name('unread-count');
        Route::post('/read-all', [NotificationCenterController::class, 'markAllRead'])->name('read-all');
        Route::post('/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('read');
        Route::get('/{notification}/open', [NotificationCenterController::class, 'open'])->name('open');
    });

    Route::get('/settings/notifications', [NotificationPreferenceController::class, 'index'])
        ->name('notifications.settings');
    Route::put('/settings/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('notifications.settings.update');

    Route::get('/settings/google-drive/connect', [GoogleDriveOAuthController::class, 'connect'])
        ->middleware(['auth.recent', 'throttle:google-drive-oauth'])
        ->name('settings.google-drive.connect');

    Route::prefix('security/associates/{associate}/access')->name('security.associates.access.')->group(function () {
        Route::get('/', [AccessInvitationAdminController::class, 'index'])->name('index');
        Route::post('/invitations', [AccessInvitationAdminController::class, 'store'])
            ->middleware('throttle:invitation-create')->name('store');
        Route::post('/invitations/{invitation}/sent', [AccessInvitationAdminController::class, 'sent'])
            ->middleware('throttle:invitation-send')->name('sent');
        Route::delete('/invitations/{invitation}', [AccessInvitationAdminController::class, 'revoke'])
            ->name('revoke');
    });

    Route::prefix('security/members/{membership}/access')->name('security.members.access.')->group(function () {
        Route::get('/', [AccessInvitationAdminController::class, 'memberIndex'])->name('index');
        Route::post('/invitations', [AccessInvitationAdminController::class, 'memberStore'])
            ->middleware('throttle:invitation-create')->name('store');
        Route::post('/invitations/{invitation}/sent', [AccessInvitationAdminController::class, 'memberSent'])
            ->middleware('throttle:invitation-send')->name('sent');
        Route::delete('/invitations/{invitation}', [AccessInvitationAdminController::class, 'memberRevoke'])
            ->name('revoke');
    });

    // Profile Routes (Available for all authenticated users)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.remove-avatar');

    Route::prefix('finance')->name('finance.')->middleware(['any.role:financeiro,tesoureiro,operador_caixa'])->group(function () {
        Route::get('/', [FinancialPortalController::class, 'index'])->name('index');
        Route::get('/receipts', [FinancialPortalController::class, 'receipts'])->name('receipts.index');
        Route::get('/receipts/create', [FinancialPortalController::class, 'create'])->name('receipts.create');
        Route::post('/receipts', [FinancialPortalController::class, 'store'])->middleware('throttle:30,1')->name('receipts.store');
        Route::get('/receipts/{financialReceipt}', [FinancialPortalController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{financialReceipt}/edit', [FinancialPortalController::class, 'edit'])->name('receipts.edit');
        Route::put('/receipts/{financialReceipt}', [FinancialPortalController::class, 'update'])->middleware('throttle:30,1')->name('receipts.update');
        Route::post('/receipts/{financialReceipt}/issue', [FinancialPortalController::class, 'issue'])->middleware('throttle:15,1')->name('receipts.issue');
        Route::post('/receipts/{financialReceipt}/cancel', [FinancialPortalController::class, 'cancel'])->middleware('throttle:10,1')->name('receipts.cancel');
        Route::get('/receipts/{financialReceipt}/print', [FinancialPortalController::class, 'print'])->name('receipts.print');
        Route::get('/management/{module}', [FinanceManagementController::class, 'index'])->name('management.index');
        Route::get('/management/{module}/data', [FinanceManagementController::class, 'data'])->name('management.data');
        Route::get('/management/{module}/{record}', [FinanceManagementController::class, 'show'])->whereNumber('record')->name('management.show');
        Route::get('/management/{module}/{record}/print', [FinanceManagementController::class, 'print'])->whereNumber('record')->name('management.print');
        Route::post('/management/{module}/data', [FinanceManagementController::class, 'store'])->middleware('throttle:30,1')->name('management.store');
        Route::put('/management/{module}/data/{record}', [FinanceManagementController::class, 'update'])->middleware('throttle:30,1')->name('management.update');
        Route::delete('/management/{module}/data/{record}', [FinanceManagementController::class, 'destroy'])->middleware('throttle:20,1')->name('management.destroy');
    });

    Route::prefix('accounting')
        ->name('accounting.')
        ->group(function () {
            Route::get('/', [AccountingPortalController::class, 'index'])->name('index');
            Route::get('/data/queue', [AccountingPortalController::class, 'queue'])->name('data.queue');
            Route::get('/processes', [AccountingPortalController::class, 'processes'])->name('processes.index');
            Route::get('/data/processes', [AccountingPortalController::class, 'processesData'])->name('data.processes');
            Route::get('/processes/{receipt}', [AccountingPortalController::class, 'show'])
                ->whereNumber('receipt')
                ->name('processes.show');
            Route::get('/data/processes/{receipt}', [AccountingPortalController::class, 'processData'])
                ->whereNumber('receipt')
                ->name('data.processes.show');
            Route::post('/data/processes/{receipt}/authorization/send', [AccountingPortalController::class, 'sendAuthorization'])
                ->middleware('throttle:20,1')->whereNumber('receipt')->name('data.processes.authorization.send');
            Route::post('/data/processes/{receipt}/authorizations/{billingAuthorization}/cancel', [AccountingPortalController::class, 'cancelAuthorization'])
                ->middleware('throttle:10,1')->whereNumber('receipt')->whereNumber('billingAuthorization')->name('data.processes.authorization.cancel');
            Route::get('/fiscal', [AccountingFiscalController::class, 'index'])->name('fiscal.index');
            Route::get('/data/fiscal', [AccountingFiscalController::class, 'data'])->name('fiscal.data');
            Route::get('/fiscal/settings', [AccountingFiscalController::class, 'settings'])->name('fiscal.settings');
            Route::post('/fiscal/settings', [AccountingFiscalController::class, 'storeSettings'])->middleware('throttle:20,1')->name('fiscal.settings.store');
            Route::get('/fiscal/{receipt}', [AccountingFiscalController::class, 'show'])->whereNumber('receipt')->name('fiscal.show');
            Route::post('/fiscal/{receipt}/prepare', [AccountingFiscalController::class, 'prepare'])->middleware('throttle:20,1')->whereNumber('receipt')->name('fiscal.prepare');
        });

    Route::prefix('delivery/conference-sheets')->name('delivery.conference-sheets.')->group(function () {
        Route::get('/', [DeliveryConferenceSheetController::class, 'index'])->name('index');
        Route::post('/', [DeliveryConferenceSheetController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/eligible', [DeliveryConferenceSheetController::class, 'eligible'])->middleware('throttle:60,1')->name('eligible');
        Route::post('/prepare-billing', [DeliveryConferenceSheetController::class, 'prepareBilling'])->middleware('throttle:10,1')->name('prepare-billing');
        Route::get('/{sheet}', [DeliveryConferenceSheetController::class, 'show'])->whereNumber('sheet')->name('show');
        Route::get('/{sheet}/preview', [DeliveryConferenceSheetController::class, 'preview'])->whereNumber('sheet')->name('preview');
        Route::get('/{sheet}/pdf', [DeliveryConferenceSheetController::class, 'download'])->whereNumber('sheet')->name('pdf');
        Route::put('/{sheet}', [DeliveryConferenceSheetController::class, 'update'])->middleware('throttle:20,1')->whereNumber('sheet')->name('update');
        Route::post('/{sheet}/issue', [DeliveryConferenceSheetController::class, 'issue'])->middleware('throttle:10,1')->whereNumber('sheet')->name('issue');
        Route::post('/{sheet}/review', [DeliveryConferenceSheetController::class, 'review'])->middleware('throttle:20,1')->whereNumber('sheet')->name('review');
        Route::post('/{sheet}/documents', [DeliveryConferenceSheetController::class, 'upload'])->middleware('throttle:20,1')->whereNumber('sheet')->name('documents.store');
        Route::get('/{sheet}/documents/{document}', [DeliveryConferenceSheetController::class, 'document'])->whereNumber(['sheet', 'document'])->name('documents.download');
        Route::post('/{sheet}/revision', [DeliveryConferenceSheetController::class, 'revision'])->middleware('throttle:10,1')->whereNumber('sheet')->name('revision');
        Route::post('/{sheet}/cancel', [DeliveryConferenceSheetController::class, 'cancel'])->middleware('throttle:10,1')->whereNumber('sheet')->name('cancel');
    });

    Route::prefix('secretary')->name('secretary.')->middleware(['any.role:secretario'])->group(function () {
        Route::get('/', [SecretaryPortalController::class, 'index'])->name('index');
        Route::get('/data', [SecretaryPortalController::class, 'data'])->name('data');
        Route::get('/templates/create', [SecretaryPortalController::class, 'createTemplate'])->name('templates.create');
        Route::post('/templates', [SecretaryPortalController::class, 'storeTemplate'])->middleware('throttle:30,1')->name('templates.store');
        Route::get('/templates/{template}/edit', [SecretaryPortalController::class, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{template}', [SecretaryPortalController::class, 'updateTemplate'])->middleware('throttle:30,1')->name('templates.update');
        Route::delete('/templates/{template}', [SecretaryPortalController::class, 'destroyTemplate'])->middleware('throttle:12,1')->name('templates.destroy');
        Route::get('/templates/{template}/preview', [SecretaryPortalController::class, 'previewTemplate'])->name('templates.preview');
        Route::get('/layouts/create', [SecretaryPortalController::class, 'createLayout'])->name('layouts.create');
        Route::post('/layouts', [SecretaryPortalController::class, 'storeLayout'])->middleware('throttle:30,1')->name('layouts.store');
        Route::get('/layouts/{layout}/edit', [SecretaryPortalController::class, 'editLayout'])->name('layouts.edit');
        Route::put('/layouts/{layout}', [SecretaryPortalController::class, 'updateLayout'])->middleware('throttle:30,1')->name('layouts.update');
        Route::delete('/layouts/{layout}', [SecretaryPortalController::class, 'destroyLayout'])->middleware('throttle:12,1')->name('layouts.destroy');
        Route::get('/documents/create', [SecretaryPortalController::class, 'createDocument'])->name('documents.create');
        Route::post('/documents', [SecretaryPortalController::class, 'storeDocument'])->middleware('throttle:30,1')->name('documents.store');
        Route::get('/documents/{document}/edit', [SecretaryPortalController::class, 'editDocument'])->name('documents.edit');
        Route::put('/documents/{document}', [SecretaryPortalController::class, 'updateDocument'])->middleware('throttle:30,1')->name('documents.update');
        Route::delete('/documents/{document}', [SecretaryPortalController::class, 'destroyDocument'])->middleware('throttle:12,1')->name('documents.destroy');
        Route::get('/documents/{document}/preview', [SecretaryPortalController::class, 'previewDocument'])->name('documents.preview');
        Route::post('/images', [SecretaryPortalController::class, 'uploadImage'])->middleware('throttle:20,1')->name('images.store');
    });

    // Report Routes (Authenticated — relying on session tenant_id)
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/associate-deliveries/{associate}', [ReportController::class, 'associateDeliveries'])
            ->name('associate-deliveries');
    });

    // Wallet / Digital Card Routes (Available for all authenticated users)
    Route::get('/wallet', [WalletController::class, 'show'])->name('wallet.show');
    Route::get('/wallet/print-card', [WalletController::class, 'printCard'])->name('wallet.print-card');

    // Service Provider Portal Routes
    Route::prefix('provider')->name('provider.')->middleware(['any.role:service_provider,tratorista,motorista,diarista,tecnico'])->group(function () {
        Route::get('/dashboard', [ProviderDashboardController::class, 'index'])->name('dashboard');
        Route::get('/orders', [ProviderDashboardController::class, 'orders'])->name('orders');
        Route::get('/orders/create', [ProviderDashboardController::class, 'createOrder'])->name('orders.create');
        Route::post('/orders', [ProviderDashboardController::class, 'storeOrder'])->name('orders.store');
        Route::get('/orders/{order}', [ProviderDashboardController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/start', [ProviderDashboardController::class, 'startExecution'])->name('orders.start');
        Route::post('/orders/{order}/complete', [ProviderDashboardController::class, 'completeOrder'])->name('orders.complete');
        Route::get('/financial', [ProviderDashboardController::class, 'financial'])->name('financial');
        Route::get('/orders/{order}/register-payment', [ProviderDashboardController::class, 'registerClientPayment'])->name('orders.register-payment');
        Route::post('/orders/{order}/register-payment', [ProviderDashboardController::class, 'storeClientPayment'])->name('orders.store-payment');
        Route::get('/financial/request-payment/{order}', [ProviderDashboardController::class, 'requestPayment'])->name('financial.request-payment');
        Route::post('/financial/request-payment/{order}', [ProviderDashboardController::class, 'storePaymentRequest'])->name('financial.store-request');
    });

    // Associate Portal Routes
    Route::prefix('associate')->name('associate.')->middleware(['role:associado'])->group(function () {
        Route::get('/dashboard', [AssociateDashboardController::class, 'index'])->name('dashboard');
        Route::get('/data/dashboard', [AssociatePortalDataController::class, 'dashboard'])->name('data.dashboard');
        Route::get('/data/projects', [AssociatePortalDataController::class, 'projects'])->name('data.projects');
        Route::get('/data/deliveries', [AssociatePortalDataController::class, 'deliveries'])->name('data.deliveries');
        Route::get('/data/ledger/{section}', [AssociatePortalDataController::class, 'ledger'])
            ->whereIn('section', ['summary', 'receipts', 'payments', 'transactions'])
            ->name('data.ledger');
        Route::get('/projects', [AssociateDashboardController::class, 'projects'])->name('projects');
        Route::get('/projects/{project}', [AssociateProjectPortalController::class, 'show'])->name('projects.show');
        Route::get('/projects/{project}/simulate', [AssociateProjectPortalController::class, 'simulatorPage'])->name('projects.simulator');
        Route::get('/projects/{project}/data/{section}', [AssociateProjectPortalController::class, 'data'])->name('projects.data');
        Route::get('/projects/{project}/receipts/{receipt}/download', [AssociateProjectPortalController::class, 'downloadReceipt'])->name('projects.receipts.download');
        Route::get('/deliveries', [AssociateDashboardController::class, 'deliveries'])->name('deliveries');
        Route::get('/ledger', [AssociateDashboardController::class, 'ledger'])->name('ledger');
    });

    // Delivery Registration Routes (Mobile-friendly for delivery recorders)
    Route::prefix('delivery')->name('delivery.')->middleware(['any.role:registrador_entregas'])->group(function () {
        Route::get('/', [DeliveryRegistrationController::class, 'index'])->name('dashboard');
        Route::get('/projects/{project}/register', [DeliveryRegistrationController::class, 'register'])->name('register');
        Route::post('/projects/{project}/register', [DeliveryRegistrationController::class, 'store'])->name('store');
        Route::post('/projects/{project}/register-batch', [DeliveryRegistrationController::class, 'storeBatch'])->name('store-batch');
        Route::get('/customers', [DeliveryRegistrationController::class, 'getCustomers'])->name('customers');
        Route::delete('/deliveries/{delivery}', [DeliveryRegistrationController::class, 'deleteDelivery'])->name('deliveries.delete');
        Route::get('/projects/{project}/demands', [DeliveryRegistrationController::class, 'getProjectDemands'])->name('projects.demands');
        Route::get('/projects/{project}/deliveries-json', [DeliveryRegistrationController::class, 'getProjectDeliveries'])->name('projects.deliveries-json');
        Route::get('/projects/{project}/deliveries-data', [DeliveryRegistrationController::class, 'projectDeliveriesData'])->name('projects.deliveries-data');
        Route::get('/projects/{project}/integrity', [DeliveryRegistrationController::class, 'getProjectIntegrity'])->name('projects.integrity');
        Route::post('/projects/{project}/integrity/resolve', [DeliveryRegistrationController::class, 'resolveIntegrityIssue'])->name('projects.integrity.resolve');
        Route::get('/projects/{project}/stock-summary', [DeliveryRegistrationController::class, 'getProjectStockSummary'])->name('projects.stock-summary');
        Route::get('/projects/{project}/associates/{associate}/deliveries', [DeliveryRegistrationController::class, 'getAssociateDeliveries'])->name('associates.deliveries');
        Route::get('/projects/{project}/associates/{associate}/simulate', [DeliveryRegistrationController::class, 'simulateAssociate'])->name('projects.associates.simulator');
        Route::get('/projects/{project}/associates/{associate}/simulate-data', [DeliveryRegistrationController::class, 'simulateAssociateData'])->name('projects.associates.simulator.data');
        Route::get('/projects/{project}/deliveries', [DeliveryRegistrationController::class, 'projectDeliveries'])->name('projects.deliveries');
        Route::post('/deliveries/{delivery}/approve', [DeliveryRegistrationController::class, 'approveDelivery'])->name('deliveries.approve');
        Route::post('/deliveries/{delivery}/reject', [DeliveryRegistrationController::class, 'rejectDelivery'])->name('deliveries.reject');
        Route::post('/deliveries/{delivery}/distribute', [DeliveryRegistrationController::class, 'distribute'])->name('deliveries.distribute');
        Route::get('/deliveries/{delivery}/customers/{customer}/price', [DeliveryRegistrationController::class, 'distributionPrice'])->name('deliveries.customers.price');
        Route::put('/deliveries/{delivery}/customers/{customer}/price', [DeliveryRegistrationController::class, 'updateDistributionPrice'])->name('deliveries.customers.price.update');
        Route::delete('/distributions/{distribution}', [DeliveryRegistrationController::class, 'deleteDistribution'])->name('distributions.delete');
        Route::put('/distributions/{distribution}', [DeliveryRegistrationController::class, 'updateDistribution'])->name('distributions.update');
        Route::put('/deliveries/{delivery}', [DeliveryRegistrationController::class, 'updateDelivery'])->name('deliveries.update');
        Route::post('/projects/{project}/start', [DeliveryRegistrationController::class, 'startProject'])->name('projects.start');
        Route::post('/projects/{project}/finalize', [DeliveryRegistrationController::class, 'finalizeProject'])->name('projects.finalize');
        Route::post('/projects/{project}/deliver-to-client', [DeliveryRegistrationController::class, 'deliverToClient'])->name('projects.deliver-to-client');

        // Relatórios operacionais (filtros e dados resolvidos no backend do projeto)
        Route::get('/projects/{project}/reports/options', [DeliveryReportController::class, 'options'])->name('projects.reports.options');
        Route::put('/projects/{project}/reports/preferences', [DeliveryReportController::class, 'updatePreferences'])->name('projects.reports.preferences');
        Route::get('/projects/{project}/reports/export', [DeliveryReportController::class, 'export'])->name('projects.reports.export');

        // Lista pública (autenticada) de produtores por projeto
        Route::get('/projects', [DeliveryRegistrationController::class, 'projectsList'])->name('projects-list');
        Route::get('/projects/{project}/producers', [DeliveryRegistrationController::class, 'projectProducers'])->name('projects.producers');
        Route::get('/projects/{project}/producers-data', [DeliveryRegistrationController::class, 'projectProducersData'])->name('projects.producers-data');
        Route::get('/projects/{project}/associates', [AssociateProjectController::class, 'index'])->name('projects.associates.index');
        Route::get('/projects/{project}/associates-data', [AssociateProjectController::class, 'associatesData'])->name('projects.associates.list');
        Route::get('/projects/{project}/product-limits', [AssociateProjectController::class, 'productLimitsIndex'])->name('projects.product-limits.index');
        Route::get('/projects/{project}/product-limits/products', [AssociateProjectController::class, 'productLimitsProducts'])->name('projects.product-limits.products');
        Route::get('/projects/{project}/product-limits/{product}', [AssociateProjectController::class, 'productLimitsBoard'])->name('projects.product-limits.board');
        Route::put('/projects/{project}/product-limits/{product}', [AssociateProjectController::class, 'updateProductLimitsBatch'])->name('projects.product-limits.batch');
        Route::get('/projects/{project}/associates/{associate}', [AssociateProjectController::class, 'show'])->name('projects.associates.show');
        Route::get('/projects/{project}/associates/{associate}/limits', [AssociateProjectController::class, 'associateLimits'])->name('projects.associates.limits.index');
        Route::get('/projects/{project}/associates/{associate}/data/{section}', [AssociateProjectController::class, 'data'])->name('projects.associates.data');
        Route::put('/projects/{project}/associates/{associate}/participation', [AssociateProjectController::class, 'updateParticipation'])->name('projects.associates.participation');
        Route::put('/projects/{project}/associates/{associate}/limits/financial', [AssociateProjectController::class, 'updateFinancialLimit'])->name('projects.associates.limits.financial');
        Route::put('/projects/{project}/associates/{associate}/limits/product', [AssociateProjectController::class, 'updateProductLimit'])->name('projects.associates.limits.product');
        Route::put('/projects/{project}/associates/{associate}/limits/products', [AssociateProjectController::class, 'updateAssociateProductLimitsBatch'])->name('projects.associates.limits.products');
        Route::delete('/projects/{project}/associates/{associate}/limits/product/{product}', [AssociateProjectController::class, 'destroyProductLimit'])->name('projects.associates.limits.product.destroy');
        Route::get('/projects/{project}/associates/{associate}/receipt-check', [DeliveryRegistrationController::class, 'checkAssociateReceipt'])->name('projects.associate-receipt-check');
        Route::get('/projects/{project}/associates/{associate}/receipt', [DeliveryRegistrationController::class, 'generateAssociateReceiptPdf'])->name('projects.associate-receipt');
        Route::post('/projects/{project}/receipt-selected', [DeliveryRegistrationController::class, 'generateSelectedDeliveriesReceipt'])->name('projects.receipt-selected');
        Route::put('/projects/{project}/receipt-print-preferences', [DeliveryRegistrationController::class, 'updateAssociateReceiptPrintPreferences'])->name('projects.receipt-print-preferences.update');
        Route::post('/projects/{project}/receipts/{receipt}/regenerate', [DeliveryRegistrationController::class, 'regenerateReceipt'])->name('projects.receipt-regenerate');
        Route::put('/projects/{project}/receipts/{receipt}/distributions', [DeliveryRegistrationController::class, 'updateReceiptDistributions'])->name('projects.receipt-distributions.update');
        Route::get('/projects/{project}/receipts/{receipt}/reprint', [DeliveryRegistrationController::class, 'reprintReceipt'])->name('projects.receipt-reprint');
        Route::get('/projects/{project}/receipts', [DeliveryRegistrationController::class, 'projectReceiptsList'])->name('projects.receipts-list');
    });

    // Read-only delivery monitoring portal. The only write operation is an audited note.
    Route::prefix('delivery-viewer')
        ->name('delivery-viewer.')
        ->middleware(['any.role:visualizador_entregas'])
        ->group(function () {
            Route::get('/', [DeliveryViewerController::class, 'index'])->name('index');
            Route::get('/projects-data', [DeliveryViewerController::class, 'projectsData'])->name('projects.data-list');
            Route::get('/projects/{project}', [DeliveryViewerController::class, 'show'])->name('projects.show');
            Route::get('/projects/{project}/data', [DeliveryViewerController::class, 'projectData'])
                ->name('projects.data');
            Route::get('/projects/{project}/deliveries', [DeliveryViewerController::class, 'deliveriesData'])
                ->name('projects.deliveries');
            Route::get('/projects/{project}/products/{productToken}', [DeliveryViewerController::class, 'product'])
                ->name('products.show');
            Route::get('/projects/{project}/products/{productToken}/data', [DeliveryViewerController::class, 'productData'])
                ->name('products.data');
            Route::get('/projects/{project}/products/{productToken}/associates', [DeliveryViewerController::class, 'productAssociatesData'])
                ->name('products.associates');
            Route::get('/projects/{project}/associates/{associateToken}', [DeliveryViewerController::class, 'associate'])
                ->name('associates.show');
            Route::get('/projects/{project}/associates/{associateToken}/data', [DeliveryViewerController::class, 'associateData'])
                ->name('associates.data');
            Route::get('/projects/{project}/notes', [DeliveryViewerController::class, 'notesData'])
                ->name('notes.index');
            Route::post('/projects/{project}/notes', [DeliveryViewerController::class, 'storeNote'])
                ->middleware('throttle:30,1')
                ->name('notes.store');
            Route::delete('/projects/{project}/notes/{note}', [DeliveryViewerController::class, 'destroyNote'])
                ->middleware('throttle:30,1')
                ->name('notes.destroy');
        });

    // Buyer Organization Portal Routes
    Route::prefix('buyer')->name('buyer.')->middleware(['buyer.organization'])->group(function () {
        Route::get('/', [BuyerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/projects', [BuyerPortalController::class, 'projects'])->name('projects');
        Route::get('/projects/{project}', [BuyerPortalController::class, 'showProject'])->name('projects.show');
        Route::get('/projects/{project}/requests/create', [BuyerPortalController::class, 'createRequest'])->name('requests.create');
        Route::post('/projects/{project}/requests', [BuyerPortalController::class, 'storeRequest'])->name('requests.store');
        Route::get('/requests/{buyerRequest}', [BuyerPortalController::class, 'showRequest'])->name('requests.show');
        Route::get('/projects/{project}/reports/distribution', [BuyerPortalController::class, 'reports'])->name('reports.distribution');
        Route::get('/authorizations', [BuyerBillingAuthorizationController::class, 'index'])->name('authorizations.index');
        Route::get('/authorizations/{billingAuthorization}', [BuyerBillingAuthorizationController::class, 'show'])->whereNumber('billingAuthorization')->name('authorizations.show');
        Route::get('/authorizations/{billingAuthorization}/attachments/{document}', [BuyerBillingAuthorizationController::class, 'downloadAttachment'])->whereNumber(['billingAuthorization', 'document'])->name('authorizations.attachments.download');
        Route::post('/authorizations/{billingAuthorization}/authorize', [BuyerBillingAuthorizationController::class, 'authorizeBilling'])->middleware('throttle:20,1')->whereNumber('billingAuthorization')->name('authorizations.authorize');
        Route::post('/authorizations/{billingAuthorization}/request-correction', [BuyerBillingAuthorizationController::class, 'requestCorrection'])->middleware('throttle:20,1')->whereNumber('billingAuthorization')->name('authorizations.request-correction');
    });

    // Delivery Sheet (Fichas de Entrega) Routes — accessible to registrador, financeiro and admin
    Route::prefix('delivery/sheet')->name('delivery.sheet.')->middleware(['any.role:registrador_entregas,financeiro,admin'])->group(function () {
        Route::get('/', [DeliverySheetController::class, 'index'])->name('index');
        Route::get('/products/{customer}', [DeliverySheetController::class, 'productsForCustomer'])->name('products');
        Route::post('/generate', [DeliverySheetController::class, 'generate'])->name('generate');
    });

    // PDV (Point of Sale) Routes
    Route::prefix('pdv')->name('pdv.')->middleware(['any.role:operador_caixa,financeiro'])->group(function () {
        Route::get('/', [PdvController::class, 'index'])->name('index');
        Route::get('/history', [PdvController::class, 'history'])->name('history');

        // API endpoints (JSON)
        Route::get('/products', [PdvController::class, 'products'])->name('products');
        Route::get('/search', [PdvController::class, 'searchProducts'])->name('search');
        Route::post('/sale', [PdvController::class, 'completeSale'])->name('sale');
        Route::post('/sale/{sale}/cancel', [PdvController::class, 'cancelSale'])->name('sale.cancel');
        Route::get('/stats', [PdvController::class, 'stats'])->name('stats');
        Route::get('/customers', [PdvController::class, 'customers'])->name('customers');
        Route::post('/customers', [PdvController::class, 'storeCustomer'])->name('customers.store');
        Route::get('/fiado', [PdvController::class, 'fiadoPending'])->name('fiado');
        Route::post('/fiado/{sale}/pay', [PdvController::class, 'payFiado'])->name('fiado.pay');
        Route::get('/history-api', [PdvController::class, 'historyApi'])->name('history.api');
        Route::get('/sale/{sale}/receipt', [PdvController::class, 'receipt'])->name('sale.receipt');
        Route::get('/sale/{sale}/detail', [PdvController::class, 'saleDetail'])->name('sale.detail');
        Route::get('/customers/{customer}', [PdvController::class, 'getCustomer'])->name('customers.get');
        Route::put('/customers/{customer}', [PdvController::class, 'updateCustomer'])->name('customers.update');
    });

});
