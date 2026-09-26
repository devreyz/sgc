<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingAccessScope extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'granted_by', 'scope_type', 'scope_id', 'scope_value', 'scope_key', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
