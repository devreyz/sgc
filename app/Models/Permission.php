<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Permissions agora são globais e a atribuição de roles (que contêm permissions) por tenant 
     * é feita através da tabela pivot tenant_user.
     * Este modelo customizado permite estender funcionalidades se necessário no futuro.
     */
}
