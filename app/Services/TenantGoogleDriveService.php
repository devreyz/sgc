<?php

namespace App\Services;

use App\Models\CloudDocument;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

class TenantGoogleDriveService
{
    public function __construct(private readonly GoogleDriveClientFactory $clients)
    {
    }

    public function ensureRootFolder(TenantCloudStorageConnection $connection): string
    {
        if ($connection->root_folder_id) {
            return $connection->root_folder_id;
        }

        $drive = $this->clients->forConnection($connection);
        $existing = $drive->files->listFiles([
            'q' => "mimeType = 'application/vnd.google-apps.folder' and trashed = false and appProperties has { key='sgc_tenant' and value='".(int) $connection->tenant_id."' } and appProperties has { key='sgc_type' and value='root' }",
            'spaces' => 'drive',
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);
        if ($existing->getFiles() !== []) {
            $connection->forceFill(['root_folder_id' => $existing->getFiles()[0]->id])->save();

            return (string) $existing->getFiles()[0]->id;
        }

        $folder = $drive->files->create(new DriveFile([
            'name' => 'SGC - '.$this->safeSegment($connection->tenant->name),
            'mimeType' => 'application/vnd.google-apps.folder',
            'appProperties' => [
                'sgc_tenant' => (string) $connection->tenant_id,
                'sgc_type' => 'root',
            ],
        ]), ['fields' => 'id']);

        $connection->forceFill(['root_folder_id' => $folder->id])->save();

        return (string) $folder->id;
    }

    public function putDocument(
        Tenant $tenant,
        Model $owner,
        string $documentType,
        array $folders,
        string $filename,
        string $contents,
        string $mimeType = 'application/pdf',
    ): CloudDocument {
        $connection = TenantCloudStorageConnection::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->first();

        if (! $connection) {
            throw new RuntimeException('Esta organizacao nao possui Google Drive conectado.');
        }

        $identity = [
            'tenant_id' => $tenant->id,
            'provider' => 'google_drive',
            'document_type' => $documentType,
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
        ];
        $document = CloudDocument::query()->where($identity)->first() ?? new CloudDocument();
        if (! $document->exists) {
            $document->forceFill($identity);
        }

        $checksum = hash('sha256', $contents);
        if ($document->exists && $document->checksum === $checksum && $document->status === 'synced') {
            return $document;
        }

        try {
            $drive = $this->clients->forConnection($connection);
            $parentId = $this->ensureRootFolder($connection);

            foreach ($folders as $folder) {
                $parentId = $this->findOrCreateFolder($drive, $parentId, $this->safeSegment($folder));
            }

            $safeFilename = $this->safeFilename($filename);
            $options = [
                'data' => $contents,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,modifiedTime',
            ];

            if ($document->remote_file_id) {
                if ($document->remote_folder_id
                    && (string) $document->remote_folder_id !== (string) $parentId) {
                    $options['addParents'] = $parentId;
                    $options['removeParents'] = $document->remote_folder_id;
                }

                $file = $drive->files->update(
                    $document->remote_file_id,
                    new DriveFile(['name' => $safeFilename]),
                    $options,
                );
            } else {
                $file = $drive->files->create(
                    new DriveFile(['name' => $safeFilename, 'parents' => [$parentId]]),
                    $options,
                );
            }

            $document->forceFill([
                'remote_file_id' => $file->id,
                'remote_folder_id' => $parentId,
                'remote_path' => implode('/', [...$folders, $safeFilename]),
                'checksum' => $checksum,
                'version' => $document->exists ? ((int) $document->version + 1) : 1,
                'status' => 'synced',
                'synced_at' => now(),
                'last_error' => null,
            ])->save();

            $connection->forceFill(['last_sync_at' => now(), 'last_error' => null])->save();

            return $document;
        } catch (Throwable $exception) {
            $safeError = $this->safeConnectionError($exception);
            $document->forceFill([
                'remote_path' => implode('/', [...$folders, $filename]),
                'status' => 'failed',
                'last_error' => $safeError,
            ])->save();
            $connection->forceFill([
                'last_error' => $safeError,
            ])->save();

            throw new RuntimeException($safeError, 0, $exception);
        }
    }

    public function disconnect(TenantCloudStorageConnection $connection): void
    {
        try {
            if ($connection->hasOAuthConfiguration() && $connection->refresh_token) {
                $client = $this->clients->baseClient($connection);
                $client->revokeToken($connection->refresh_token);
            }
        } catch (Throwable) {
            // A revogacao local continua obrigatoria se o Google estiver indisponivel.
        }

        $connection->forceFill([
            'refresh_token' => null,
            'granted_scopes' => [],
            'status' => 'revoked',
            'last_error' => null,
        ])->save();
    }

    private function findOrCreateFolder(Drive $drive, string $parentId, string $name): string
    {
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);
        $files = $drive->files->listFiles([
            'q' => "name = '{$escaped}' and mimeType = 'application/vnd.google-apps.folder' and '{$parentId}' in parents and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        if ($files->getFiles() !== []) {
            return (string) $files->getFiles()[0]->id;
        }

        $folder = $drive->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]), ['fields' => 'id']);

        return (string) $folder->id;
    }

    private function safeSegment(string $value): string
    {
        $value = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $value) ?? '');

        return mb_substr($value !== '' ? $value : 'Sem nome', 0, 120);
    }

    private function safeFilename(string $value): string
    {
        return mb_substr($this->safeSegment($value), 0, 180);
    }

    private function safeConnectionError(Throwable $exception): string
    {
        $messages = [];
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            $messages[] = mb_strtolower($current->getMessage());
        }
        $message = implode(' ', $messages);

        return match (true) {
            str_contains($message, 'invalid_grant'),
            str_contains($message, 'unauthorized'),
            str_contains($message, '401') => 'A autorização do Google Drive expirou ou foi revogada. Reconecte a conta.',
            str_contains($message, 'insufficientpermissions'),
            str_contains($message, 'insufficient permission'),
            str_contains($message, 'forbidden'),
            str_contains($message, '403') => 'A conta conectada não possui permissão suficiente no Google Drive. Reconecte e aceite o acesso solicitado.',
            str_contains($message, 'ratelimit'),
            str_contains($message, 'quota'),
            str_contains($message, '429') => 'O limite temporário do Google Drive foi atingido. A tarefa tentará novamente.',
            str_contains($message, 'timed out'),
            str_contains($message, 'timeout'),
            str_contains($message, 'connection') => 'O Google Drive não respondeu. A tarefa tentará novamente.',
            default => 'Não foi possível enviar o documento. Consulte o log de cloud_storage para o diagnóstico técnico.',
        };
    }
}
