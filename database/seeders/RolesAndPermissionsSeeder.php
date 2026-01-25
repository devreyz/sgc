<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $superAdmin = Role::create(['name' => 'super_admin']);
        $admin = Role::create(['name' => 'admin']);
        $financial = Role::create(['name' => 'financeiro']);
        $associate = Role::create(['name' => 'associado']);

        // Create a super admin user
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@sgc.com',
            'password' => Hash::make('password'),
            'status' => true,
        ]);

        $user->assignRole($superAdmin);

        // Create a demo associate user
        $associateUser = User::create([
            'name' => 'João Silva',
            'email' => 'associado@sgc.com',
            'password' => Hash::make('password'),
            'status' => true,
        ]);

        $associateUser->assignRole($associate);

        // Create the associate profile
        \App\Models\Associate::create([
            'user_id' => $associateUser->id,
            'cpf_cnpj' => '123.456.789-00',
            'dap_caf' => 'DAP123456',
            'dap_caf_expiry' => now()->addYear(),
            'city' => 'Campo Grande',
            'state' => 'MS',
        ]);
    }
}
