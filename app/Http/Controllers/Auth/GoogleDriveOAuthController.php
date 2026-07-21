<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\SyncAssociateReceiptToDrive;
use App\Jobs\SyncTenantStoredFileToDrive;
use App\Models\AssociateReceipt;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\TenantUser;
use App\Services\GoogleDriveClientFactory;
use App\Services\TenantGoogleDriveService;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;
use RuntimeException;
use Throwable;

class GoogleDriveOAuthController extends Controller
{
    public function connect(Request $request, Tenant $tenant, GoogleDriveClientFactory $clients): RedirectResponse
    {
        abort_if(app()->environment('production') && ! $request->isSecure(), 400);
        $this->authorizeTenantAdmin($request, $tenant);

        $connection = TenantCloudStorageConnection::query()
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $connection?->hasOAuthConfiguration()) {
            Notification::make()
                ->danger()
                ->title('Configure as credenciais do Google Drive')
                ->send();

            return redirect()->route('filament.admin.pages.organization-settings-page', [
                'tab' => 'google-drive-tab',
            ]);
        }

        $state = Str::random(80);
        $request->session()->put('google_drive_oauth', [
            'state_hash' => hash('sha256', $state),
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'connection_id' => $connection->id,
            'client_id_hash' => hash('sha256', (string) $connection->oauth_client_id),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $client = $clients->baseClient($connection);
        $client->setState($state);
        $client->setPrompt('consent');

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(
        Request $request,
        GoogleDriveClientFactory $clients,
        TenantGoogleDriveService $driveStorage,
    ): RedirectResponse {
        abort_if(app()->environment('production') && ! $request->isSecure(), 400);
        $oauth = (array) $request->session()->pull('google_drive_oauth', []);

        try {
            if (! $request->user()
                || empty($oauth['state_hash'])
                || empty($oauth['tenant_id'])
                || empty($oauth['user_id'])
                || empty($oauth['connection_id'])
                || empty($oauth['client_id_hash'])
                || (int) $oauth['user_id'] !== (int) $request->user()->id
                || (int) ($oauth['expires_at'] ?? 0) < now()->timestamp
                || ! hash_equals((string) $oauth['state_hash'], hash('sha256', (string) $request->query('state')))
                || $request->filled('error')) {
                throw new RuntimeException('Resposta OAuth invalida.');
            }

            $tenant = Tenant::query()->findOrFail((int) $oauth['tenant_id']);
            $this->authorizeTenantAdmin($request, $tenant, false);

            $connection = TenantCloudStorageConnection::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey((int) $oauth['connection_id'])
                ->firstOrFail();

            if (! $connection->hasOAuthConfiguration()
                || ! hash_equals((string) $oauth['client_id_hash'], hash('sha256', (string) $connection->oauth_client_id))) {
                throw new RuntimeException('A configuracao OAuth foi alterada.');
            }

            $client = $clients->baseClient($connection);
            $token = $client->fetchAccessTokenWithAuthCode((string) $request->query('code'));
            $refreshToken = (string) ($token['refresh_token'] ?? '');
            $grantedScopes = preg_split('/\s+/', trim((string) ($token['scope'] ?? ''))) ?: [];

            if ($refreshToken === '' || isset($token['error']) || ! in_array(Drive::DRIVE_FILE, $grantedScopes, true)) {
                throw new RuntimeException('O Google nao forneceu autorizacao persistente.');
            }

            $connection = DB::transaction(function () use ($tenant, $request, $refreshToken, $grantedScopes) {
                $connection = TenantCloudStorageConnection::query()
                    ->where('tenant_id', $tenant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $connection->forceFill([
                    'tenant_id' => $tenant->id,
                    'provider' => 'google_drive',
                    'refresh_token' => $refreshToken,
                    'granted_scopes' => $grantedScopes,
                    'root_folder_id' => null,
                    'status' => 'active',
                    'connected_by_user_id' => $request->user()->id,
                    'connected_at' => now(),
                    'last_error' => null,
                ])->save();

                return $connection;
            });

            $driveStorage->ensureRootFolder($connection->load('tenant'));
            AssociateReceipt::query()
                ->where('tenant_id', $tenant->id)
                ->select('id')
                ->chunkById(100, function ($receipts): void {
                    foreach ($receipts as $receipt) {
                        SyncAssociateReceiptToDrive::dispatch($receipt->id);
                    }
                });
            SyncTenantStoredFileToDrive::dispatchExistingForTenant($tenant->id);
            activity('cloud_storage')->causedBy($request->user())->performedOn($tenant)
                ->withProperties(['tenant_id' => $tenant->id, 'provider' => 'google_drive'])
                ->log('Google Drive conectado');

            session(['tenant_id' => $tenant->id, 'tenant_slug' => $tenant->slug]);

            return redirect()->route('filament.admin.pages.organization-settings-page')
                ->with('success', 'Google Drive conectado com seguranca.');
        } catch (Throwable) {
            activity('cloud_storage')->causedBy($request->user())
                ->withProperties(['tenant_id' => $oauth['tenant_id'] ?? null, 'provider' => 'google_drive'])
                ->log('Falha ao conectar Google Drive');

            return redirect()->route('security.index')
                ->with('error', 'Nao foi possivel conectar o Google Drive. Tente novamente.');
        }
    }

    private function authorizeTenantAdmin(Request $request, Tenant $tenant, bool $checkSessionTenant = true): void
    {
        $user = $request->user();
        abort_if(! $user || $user->isSuperAdmin(), 403);
        abort_if($checkSessionTenant && (int) session('tenant_id') !== (int) $tenant->id, 403);

        $authorized = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', true)
            ->where('is_admin', true)
            ->exists();

        abort_unless($authorized, 403);
    }
}
