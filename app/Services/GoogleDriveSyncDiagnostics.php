<?php

namespace App\Services;

use App\Models\CloudDocument;
use App\Models\TenantCloudStorageConnection;

class GoogleDriveSyncDiagnostics
{
    public function __construct(private readonly QueueTaskInspector $queues)
    {
    }

    /** @return array<string, mixed> */
    public function forTenant(int $tenantId): array
    {
        $connection = TenantCloudStorageConnection::query()
            ->where('tenant_id', $tenantId)
            ->first();
        $pendingJobs = collect($this->queues->pendingForTenant($tenantId, 500));
        $failedJobs = collect($this->queues->failedForTenant($tenantId, 500));
        $documents = CloudDocument::query()->where('tenant_id', $tenantId);

        return [
            'connected' => $connection?->status === 'active',
            'connection_status' => $connection?->status ?? 'not_configured',
            'last_sync_at' => $connection?->last_sync_at,
            'last_error' => $connection?->last_error,
            'root_folder_ready' => filled($connection?->root_folder_id),
            'queued_documents' => $pendingJobs->where('queue', 'documents')->count(),
            'queued_notifications' => $pendingJobs->where('queue', 'notifications')->count(),
            'failed_document_jobs' => $failedJobs->where('queue', 'documents')->count(),
            'failed_notification_jobs' => $failedJobs->where('queue', 'notifications')->count(),
            'synced_documents' => (clone $documents)->where('status', 'synced')->count(),
            'failed_documents' => (clone $documents)->where('status', 'failed')->count(),
            'rejected_documents' => (clone $documents)->where('status', 'rejected')->count(),
            'pending_documents' => (clone $documents)->where('status', 'pending')->count(),
        ];
    }
}
