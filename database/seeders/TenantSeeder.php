<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar Tenant Padrão
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Organização Padrão',
                'settings' => [],
            ]
        );

        $this->command->info("✓ Tenant criado: {$tenant->name}");

        // Criar Super Admin (sem tenant, acessa todos)
        $superAdmin = User::firstOrCreate(
            ['email' => 'josereisleite2016@gmail.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'status' => true,
            ]
        );

        $this->command->info("✓ Super Admin criado: {$superAdmin->email}");

        // Criar Admin do Tenant
        $tenantAdmin = User::firstOrCreate(
            ['email' => 'reysilver901@gmail.com'],
            [
                'name' => 'Administrador do Tenant',
                'password' => Hash::make('password'),
                'is_super_admin' => false,
                'status' => true,
            ]
        );

        // Vincular admin ao tenant
        if (! $tenant->hasUser($tenantAdmin)) {
            $tenant->users()->attach($tenantAdmin->id, ['is_admin' => true]);
        }

        $this->command->info("✓ Admin do Tenant criado: {$tenantAdmin->email}");

        // Criar Role admin para este tenant
        $adminRole = Role::firstOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]
        );

        $this->command->info("✓ Role 'admin' criada para tenant");

        // Criar Role financeiro para este tenant
        $financeRole = Role::firstOrCreate(
            [
                'name' => 'financeiro',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]
        );

        $this->command->info("✓ Role 'financeiro' criada para tenant");

        // Criar Role associado para este tenant
        $associateRole = Role::firstOrCreate(
            [
                'name' => 'associado',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]
        );

        $this->command->info("✓ Role 'associado' criada para tenant");

        // Criar Role service_provider para este tenant
        $providerRole = Role::firstOrCreate(
            [
                'name' => 'service_provider',
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]
        );

        $this->command->info("✓ Role 'service_provider' criada para tenant");

        // Atribuir role admin ao admin do tenant (incluir tenant_id no pivot)
        if (! $tenantAdmin->roles()->where('roles.id', $adminRole->id)->wherePivot('tenant_id', $tenant->id)->exists()) {
            $tenantAdmin->roles()->attach($adminRole->id, ['tenant_id' => $tenant->id]);
        }

        $this->command->info("✓ Role 'admin' atribuída ao admin do tenant");

        $this->command->info("\n=== Seeder Completo ===");
        $this->command->info('Super Admin: josereisleite2016@gmail.com | password');
        $this->command->info('Tenant Admin: reysilver901@gmail.com | password');
        $this->command->info("Tenant: {$tenant->name} (ID: {$tenant->id})");
    }
}
