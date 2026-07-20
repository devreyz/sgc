<?php

namespace App\Observers;

use App\Jobs\SyncTenantStoredFileToDrive;
use Illuminate\Database\Eloquent\Model;

class TenantStoredFileObserver
{
    public function saved(Model $model): void
    {
        $field = SyncTenantStoredFileToDrive::pathFieldFor($model);
        if (! $field || ! is_string($model->getAttribute($field)) || $model->getAttribute($field) === '') {
            return;
        }

        if ($model->wasRecentlyCreated || $model->wasChanged($field)) {
            SyncTenantStoredFileToDrive::dispatch($model::class, (int) $model->getKey())->afterCommit();
        }
    }
}
