<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'view_any_financial::receipt', 'view_financial::receipt', 'create_financial::receipt',
        'update_financial::receipt', 'financial_receipt.issue', 'financial_receipt.cancel',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['super_admin', 'admin', 'financeiro', 'tesoureiro'] as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo($this->permissions);
        }

        Role::where('name', 'operador_caixa')->first()?->givePermissionTo([
            'view_any_financial::receipt', 'view_financial::receipt', 'create_financial::receipt',
            'update_financial::receipt', 'financial_receipt.issue',
        ]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
