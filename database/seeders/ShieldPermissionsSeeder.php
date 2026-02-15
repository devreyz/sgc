<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ShieldPermissionsSeeder extends Seeder
{
    /**
     * Assign all Shield permissions to admin role of each tenant
     */
    public function run(): void
    {
        // Set tenant context for seeding
        $tenantId = 1; // Default tenant
        app()->instance('tenant.id', $tenantId);
        session(['tenant_id' => $tenantId]);

        $this->command->info('Atribuindo permissões do Filament Shield aos roles...');

        // Get admin role for this tenant
        $adminRole = Role::withoutGlobalScopes()
            ->where('name', 'admin')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $adminRole) {
            $this->command->error("Role 'admin' não encontrado para tenant {$tenantId}");

            return;
        }

        // Get all permissions
        $permissions = Permission::all();

        $this->command->info("Encontradas {$permissions->count()} permissões");

        // Sync all permissions to admin role
        $adminRole->syncPermissions($permissions);

        $this->command->info("✓ {$permissions->count()} permissões atribuídas ao role 'admin' do tenant {$tenantId}");

        // Also assign to financeiro role (limited access)
        $financeRole = Role::withoutGlobalScopes()
            ->where('name', 'financeiro')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($financeRole) {
            // Financial role gets limited permissions
            $financePermissions = $permissions->filter(function ($permission) {
                return str_contains($permission->name, 'expense') ||
                       str_contains($permission->name, 'cash_movement') ||
                       str_contains($permission->name, 'bank_account') ||
                       str_contains($permission->name, 'loan') ||
                       str_contains($permission->name, 'direct_purchase') ||
                       str_contains($permission->name, 'purchase_order') ||
                       str_contains($permission->name, 'chart_account') ||
                       str_contains($permission->name, 'service_order_payment');
            });

            $financeRole->syncPermissions($financePermissions);
            $this->command->info("✓ {$financePermissions->count()} permissões financeiras atribuídas ao role 'financeiro'");
        }

        $this->command->info("\n=== Permissões atribuídas com sucesso! ===");
    }
}
