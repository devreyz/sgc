<?php

namespace App\Services;

use App\Models\TenantCloudStorageConnection;
use Google\Client;
use Google\Service\Drive;
use RuntimeException;

class GoogleDriveClientFactory
{
    public function baseClient(TenantCloudStorageConnection $connection): Client
    {
        $clientId = trim((string) $connection->oauth_client_id);
        $clientSecret = trim((string) $connection->oauth_client_secret);
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
        if ($connection->status !== 'active' || trim((string) $connection->refresh_token) === '') {
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
