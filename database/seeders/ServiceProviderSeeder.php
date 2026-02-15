<?php

namespace Database\Seeders;

use App\Models\ServiceProvider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ServiceProviderSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure role exists
        $role = Role::firstOrCreate(['name' => 'service_provider', 'guard_name' => 'web']);

        // Permissions for service providers (view orders, submit work)
        $permissions = [
            'view_service_orders',
            'create_service_work',
            'view_own_service_work',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign permissions to role
        $role->syncPermissions($permissions);

        // Create a demo service provider user
        $user = User::firstOrCreate(
            ['email' => 'prestador@sgc.com'],
            [
                'name' => 'João Prestador',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        $user->assignRole($role);

        // Create the service provider profile
        ServiceProvider::firstOrCreate(
            ['email' => 'prestador@sgc.com'],
            [
                'user_id' => $user->id,
                'name' => 'João Prestador',
                // campo na migration é 'cpf'
                'cpf' => '987.654.321-00',
                'phone' => '(67) 99999-9999',
                // campo na migration é 'type'
                'type' => 'outro',
                // current_balance é adicionado por migration posterior, manter caso exista
                'current_balance' => 0,
            ]
        );

        $this->command->info('Service provider role, permissions and demo user created.');
    }
}
