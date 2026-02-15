<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Roles agora são globais e a atribuição por tenant é feita através da tabela pivot tenant_user.
     * Este modelo customizado permite estender funcionalidades se necessário no futuro.
     */
}
