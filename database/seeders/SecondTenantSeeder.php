<?php

namespace Database\Seeders;

use App\Models\Associate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SecondTenantSeeder extends Seeder
{
    /**
     * Create a second tenant with test data for isolation testing
     */
    public function run(): void
    {
        // Create second tenant
        $tenant2 = Tenant::firstOrCreate(
            ['slug' => 'empresa-teste'],
            [
                'name' => 'Empresa Teste Ltda',
                'settings' => ['teste' => true],
            ]
        );

        $this->command->info("✓ Tenant criado: {$tenant2->name} (ID: {$tenant2->id})");

        // Set tenant context
        app()->instance('tenant.id', $tenant2->id);
        session(['tenant_id' => $tenant2->id]);

        // Create admin user for this tenant
        $admin2 = User::firstOrCreate(
            ['email' => 'admin@empresa-teste.com'],
            [
                'name' => 'Admin Empresa Teste',
                'password' => Hash::make('password'),
                'is_super_admin' => false,
                'status' => true,
            ]
        );

        $this->command->info("✓ Admin criado: {$admin2->email}");

        // Attach to tenant
        if (!$tenant2->hasUser($admin2)) {
            $tenant2->users()->attach($admin2->id, ['is_admin' => true]);
        }

        // Create roles for this tenant
        $adminRole = Role::withoutGlobalScopes()->firstOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_id' => $tenant2->id,
            ]
        );

        $financeRole = Role::withoutGlobalScopes()->firstOrCreate(
            [
                'name' => 'financeiro',
                'guard_name' => 'web',
                'tenant_id' => $tenant2->id,
            ]
        );

        $this->command->info("✓ Roles criadas para tenant 2");

        // Assign admin role with pivot
        if (!$admin2->roles()->where('roles.id', $adminRole->id)->exists()) {
            $admin2->roles()->attach($adminRole->id, ['tenant_id' => $tenant2->id]);
        }

        // Sync permissions to admin role
        $permissions = \App\Models\Permission::all();
        $adminRole->syncPermissions($permissions);

        $this->command->info("✓ {$permissions->count()} permissões atribuídas ao admin do tenant 2");

        // Create test associate for tenant 2
        $associate2 = Associate::firstOrCreate(
            [
                'cpf_cnpj' => '111.222.333-44',
                'tenant_id' => $tenant2->id,
            ],
            [
                'user_id' => $admin2->id,
                'city' => 'São Paulo',
                'state' => 'SP',
                'phone' => '(11) 99999-8888',
            ]
        );

        $this->command->info("✓ Associado teste criado para tenant 2");

        $this->command->info("\n=== Tenant 2 configurado com sucesso! ===");
        $this->command->info("Email: admin@empresa-teste.com");
        $this->command->info("Senha: password");
        $this->command->info("Tenant: {$tenant2->name} (ID: {$tenant2->id})");
    }
}
