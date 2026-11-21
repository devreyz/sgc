<?php

namespace App\Services;

use App\Models\TenantCloudStorageConnection;
use Google\Client;
use Google\Service\Drive;
use RuntimeException;

class GoogleDriveClientFactory
{
    public function hasManagedCredentials(): bool
    {
        return filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.client_secret'));
    }

    public function canConnect(?TenantCloudStorageConnection $connection): bool
    {
        return ($connection?->hasOAuthConfiguration() ?? false) || $this->hasManagedCredentials();
    }

    public function pickerConfiguration(Client $client): ?array
    {
        $appId = (string) config('services.google_drive.picker_app_id');
        $apiKey = (string) config('services.google_drive.picker_api_key');

        return $client->getClientId() === config('services.google_drive.client_id')
            && ctype_digit($appId) && filled($apiKey) ? compact('appId', 'apiKey') : null;
    }

    public function baseClient(TenantCloudStorageConnection $connection): Client
    {
        $manual = $connection->hasOAuthConfiguration()
            && (! $this->hasManagedCredentials() || $connection->oauth_client_id !== config('services.google_drive.client_id'));
        $clientId = trim((string) ($manual ? $connection->oauth_client_id : config('services.google_drive.client_id')));
        $clientSecret = trim((string) ($manual ? $connection->oauth_client_secret : config('services.google_drive.client_secret')));
        $redirectUri = $this->redirectUri();

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('A organizacao ainda nao configurou o Google Drive.');
        }

        $client = new Client();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setAccessType('offline');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([Drive::DRIVE_FILE]);

        return $client;
    }

    public function redirectUri(): string
    {
        $applicationUrl = rtrim((string) config('app.url'), '/');

        if ($applicationUrl === '') {
            throw new RuntimeException('A URL da aplicacao nao esta configurada.');
        }

        return $applicationUrl.'/auth/google-drive/callback';
    }

    public function forConnection(TenantCloudStorageConnection $connection): Drive
    {
        if ($connection->status !== 'active' || ! $connection->hasOAuthConfiguration() || trim((string) $connection->refresh_token) === '') {
            throw new RuntimeException('A conexao com o Google Drive nao esta ativa.');
        }

        $client = $this->baseClient($connection);
        $token = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);

        if (isset($token['error'])) {
            throw new RuntimeException('Nao foi possivel renovar o acesso ao Google Drive.');
        }

        return new Drive($client);
    }
}
