<?php

namespace App\Services;

use App\Models\TenantCloudStorageConnection;
use Google\Client;
use Google\Service\Drive;
use RuntimeException;

class GoogleDriveClientFactory
{
    public function baseClient(): Client
    {
        $clientId = (string) config('services.google_drive.client_id');
        $clientSecret = (string) config('services.google_drive.client_secret');
        $redirectUri = (string) config('services.google_drive.redirect');

        if ($clientId === '' || $clientSecret === '' || $redirectUri === '') {
            throw new RuntimeException('A integracao com o Google Drive nao esta configurada.');
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

    public function forConnection(TenantCloudStorageConnection $connection): Drive
    {
        if ($connection->status !== 'active' || $connection->refresh_token === '') {
            throw new RuntimeException('A conexao com o Google Drive nao esta ativa.');
        }

        $client = $this->baseClient();
        $token = $client->fetchAccessTokenWithRefreshToken($connection->refresh_token);

        if (isset($token['error'])) {
            throw new RuntimeException('Nao foi possivel renovar o acesso ao Google Drive.');
        }

        return new Drive($client);
    }
}
