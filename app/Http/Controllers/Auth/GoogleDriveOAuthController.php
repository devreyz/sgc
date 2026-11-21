<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\TenantUser;
use App\Services\GoogleDriveClientFactory;
use App\Services\GoogleDriveSyncDispatcher;
use App\Services\TenantGoogleDriveService;
use Filament\Notifications\Notification;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GoogleDriveOAuthController extends Controller
{
    public function connect(Request $request, Tenant $tenant, GoogleDriveClientFactory $clients): RedirectResponse
    {
        $this->authorizeTenantAdmin($request, $tenant);
        $connection = $this->connection($tenant);
        if (! $clients->canConnect($connection)) {
            Notification::make()->danger()->title('Configure as credenciais do Google Drive')->send();

            return $this->settings();
        }

        $client = $clients->baseClient($connection);
        $state = Str::random(80);
        $verifier = $client->getOAuth2Service()->generateCodeVerifier();
        $request->session()->forget('google_drive_destination');
        $request->session()->put('google_drive_oauth', [
            'state_hash' => hash('sha256', $state),
            'code_verifier' => $verifier,
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()->id,
            'connection_hash' => $this->fingerprint($connection, $client),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        $client->setState($state);
        $client->setPrompt('consent select_account');

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request, GoogleDriveClientFactory $clients): RedirectResponse
    {
        abort_if(app()->environment('production') && ! $request->isSecure(), 400);
        $oauth = (array) $request->session()->pull('google_drive_oauth', []);
        if (! $request->user() || empty($oauth['state_hash']) || empty($oauth['code_verifier'])
            || (int) ($oauth['user_id'] ?? 0) !== (int) $request->user()->id
            || (int) ($oauth['expires_at'] ?? 0) < now()->timestamp
            || ! is_string($request->query('state'))
            || ! hash_equals($oauth['state_hash'], hash('sha256', $request->query('state')))) {
            return redirect()->route('security.index')->with('error', 'A autorização do Google Drive expirou. Inicie a conexão novamente.');
        }

        $tenant = Tenant::query()->findOrFail((int) $oauth['tenant_id']);
        $this->authorizeTenantAdmin($request, $tenant);
        if ($request->filled('error')) {
            Notification::make()->warning()->title('Autorização do Google Drive cancelada')->send();

            return $this->settings();
        }

        try {
            $connection = $this->connection($tenant);
            $client = $clients->baseClient($connection);
            if (! hash_equals($oauth['connection_hash'], $this->fingerprint($connection, $client))
                || ! is_string($request->query('code')) || ! $request->filled('code')) {
                throw new RuntimeException('Configuração OAuth alterada ou código inválido.');
            }

            $token = $client->fetchAccessTokenWithAuthCode($request->query('code'), $oauth['code_verifier']);
            $scopes = preg_split('/\s+/', trim((string) ($token['scope'] ?? ''))) ?: [];
            if (isset($token['error']) || empty($token['access_token']) || empty($token['refresh_token'])
                || ! in_array(Drive::DRIVE_FILE, $scopes, true)) {
                throw new RuntimeException('Autorização persistente indisponível.');
            }

            $request->session()->put('google_drive_destination', Crypt::encrypt([
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()->id,
                'connection_hash' => $oauth['connection_hash'],
                'expires_at' => now()->addSeconds(min(600, (int) ($token['expires_in'] ?? 0)))->timestamp,
                'token' => $token,
                'scopes' => $scopes,
            ]));

            return redirect()->route('settings.google-drive.destination', ['tenant' => $tenant->slug]);
        } catch (Throwable) {
            activity('cloud_storage')->causedBy($request->user())->performedOn($tenant)->log('Falha ao autorizar Google Drive');
            Notification::make()->danger()->title('Não foi possível autorizar o Google Drive. Tente novamente.')->send();

            return $this->settings();
        }
    }

    public function destination(Request $request, Tenant $tenant, GoogleDriveClientFactory $clients): Response|RedirectResponse
    {
        $this->authorizeTenantAdmin($request, $tenant);
        $grant = $this->pendingGrant($request, $tenant);
        $connection = $this->connection($tenant);
        $client = $clients->canConnect($connection) ? $clients->baseClient($connection) : null;
        if (! $grant || ! $client || ! hash_equals($grant['connection_hash'], $this->fingerprint($connection, $client))) {
            return $this->expired();
        }

        $existingRoot = filled($connection->root_folder_id);
        $picker = $existingRoot ? null : $clients->pickerConfiguration($client);

        return response()->view('auth.google-drive-destination', [
            'tenant' => $tenant,
            'existingRoot' => $existingRoot,
            'picker' => $picker ? $picker + ['accessToken' => $grant['token']['access_token']] : null,
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Frame-Options', 'DENY')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function saveDestination(
        Request $request,
        Tenant $tenant,
        GoogleDriveClientFactory $clients,
        TenantGoogleDriveService $driveStorage,
        GoogleDriveSyncDispatcher $syncDispatcher,
    ): RedirectResponse {
        $this->authorizeTenantAdmin($request, $tenant);
        $grant = $this->pendingGrant($request, $tenant);
        if (! $grant) {
            return $this->expired();
        }
        $data = $request->validate([
            'destination' => 'required|in:automatic,selected',
            'folder_id' => ['exclude_unless:destination,selected', 'required', 'string', 'max:200', 'regex:/\A[A-Za-z0-9_-]+\z/'],
        ]);
        $selectedFolder = $data['folder_id'] ?? null;

        try {
            $activated = DB::transaction(function () use ($tenant, $request, $grant, $selectedFolder, $clients, $driveStorage): bool {
                // Serializa as confirmações inclusive antes de existir uma linha de conexão.
                Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();
                $connection = $this->connection($tenant, lock: true);
                if (! $clients->canConnect($connection)) {
                    return false;
                }
                $client = $clients->baseClient($connection);
                if (! hash_equals($grant['connection_hash'], $this->fingerprint($connection, $client))) {
                    return false;
                }
                if ($selectedFolder && ($connection->root_folder_id || ! $clients->pickerConfiguration($client))) {
                    throw new RuntimeException('Seleção de pasta indisponível.');
                }

                $client->setAccessToken($grant['token']);
                $rootFolder = $driveStorage->prepareRootFolder(new Drive($client), $tenant, $connection->root_folder_id, $selectedFolder);
                $connection->forceFill([
                    'oauth_client_id' => $client->getClientId(),
                    'oauth_client_secret' => $client->getClientSecret(),
                    'refresh_token' => $grant['token']['refresh_token'],
                    'granted_scopes' => $grant['scopes'],
                    'root_folder_id' => $rootFolder,
                    'status' => 'active',
                    'connected_by_user_id' => $request->user()->id,
                    'connected_at' => now(),
                    'last_error' => null,
                ])->save();

                return true;
            });
        } catch (Throwable) {
            $message = $this->connection($tenant)->root_folder_id
                ? 'A conta autorizada precisa ter acesso à pasta atual. Volte e reconecte com a conta usada anteriormente.'
                : 'O Google Drive não permitiu preparar a pasta. Verifique se você pode adicionar arquivos nela ou use a pasta automática.';
            return back()->withErrors(['folder_id' => $message]);
        }

        if (! $activated) {
            return $this->expired();
        }

        $request->session()->forget('google_drive_destination');
        activity('cloud_storage')->causedBy($request->user())->performedOn($tenant)
            ->withProperties(['tenant_id' => $tenant->id, 'provider' => 'google_drive'])
            ->log('Google Drive conectado');

        try {
            $syncDispatcher->dispatchForTenant((int) $tenant->id);
        } catch (Throwable) {
            Notification::make()->warning()->title('Drive conectado. Use “Sincronizar agora” para iniciar o envio.')->send();

            return $this->settings();
        }

        Notification::make()->success()->title('Google Drive conectado')->send();

        return $this->settings();
    }

    private function connection(Tenant $tenant, bool $lock = false): TenantCloudStorageConnection
    {
        return TenantCloudStorageConnection::query()->where('tenant_id', $tenant->id)
            ->where('provider', 'google_drive')->when($lock, fn ($query) => $query->lockForUpdate())->first()
            ?? (new TenantCloudStorageConnection)->forceFill(['tenant_id' => $tenant->id, 'provider' => 'google_drive']);
    }

    private function fingerprint(TenantCloudStorageConnection $connection, Client $client): string
    {
        return hash('sha256', serialize([
            $connection->id, $connection->status, $connection->root_folder_id, $connection->refresh_token,
            $client->getClientId(), $client->getClientSecret(),
        ]));
    }

    private function pendingGrant(Request $request, Tenant $tenant): ?array
    {
        $encrypted = $request->session()->get('google_drive_destination');
        if (! $encrypted) {
            return null;
        }

        $grant = Crypt::decrypt($encrypted);

        return (int) $grant['tenant_id'] === (int) $tenant->id
            && (int) $grant['user_id'] === (int) $request->user()->id
            && (int) $grant['expires_at'] > now()->timestamp ? $grant : null;
    }

    private function expired(): RedirectResponse
    {
        Notification::make()->warning()->title('A autorização expirou ou a configuração mudou. Conecte novamente.')->send();

        return $this->settings();
    }

    private function settings(): RedirectResponse
    {
        return redirect()->route('filament.admin.pages.organization-settings-page', ['tab' => 'organization-settings-google-drive-tab']);
    }

    private function authorizeTenantAdmin(Request $request, Tenant $tenant): void
    {
        abort_if(app()->environment('production') && ! $request->isSecure(), 400);
        $user = $request->user();
        abort_if(! $user || $user->isSuperAdmin() || (int) session('tenant_id') !== (int) $tenant->id, 403);
        abort_unless(TenantUser::query()->where('tenant_id', $tenant->id)->where('user_id', $user->id)
            ->where('status', true)->where('is_admin', true)->exists(), 403);
    }
}
