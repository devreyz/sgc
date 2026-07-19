<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'access-links.create',
        'access-links.view',
        'access-links.revoke',
        'passkeys.manage-own',
        'passkeys.manage-users',
        'security-events.view',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect($this->permissions)->map(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        Role::query()->whereIn('name', ['super_admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        $manageOwn = $permissions->firstWhere('name', 'passkeys.manage-own');
        Role::query()->whereIn('name', [
            'financeiro', 'operador_caixa', 'assistente', 'associado', 'service_provider',
            'tratorista', 'motorista', 'diarista', 'tecnico', 'registrador_entregas',
        ])->get()->each(fn (Role $role) => $role->givePermissionTo($manageOwn));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', $this->permissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
