<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    // Permissions são globais, não por tenant
    // Mas podem ser atribuídas a roles que são por tenant
}
