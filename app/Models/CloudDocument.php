<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CloudDocument extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id', 'tenant_id'];

    protected $hidden = ['remote_file_id', 'remote_folder_id', 'last_error'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'synced_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
