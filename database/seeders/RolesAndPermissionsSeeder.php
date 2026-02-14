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

        // Create roles (idempotent)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $financial = Role::firstOrCreate(['name' => 'financeiro']);
        $associate = Role::firstOrCreate(['name' => 'associado']);
        
        // Service Provider roles (can have multiple)
        $serviceProvider = Role::firstOrCreate(['name' => 'service_provider']); // Generic provider
        $tratorista = Role::firstOrCreate(['name' => 'tratorista']);
        $motorista = Role::firstOrCreate(['name' => 'motorista']);
        $diarista = Role::firstOrCreate(['name' => 'diarista']);
        $tecnico = Role::firstOrCreate(['name' => 'tecnico']);
        
        // Delivery recorder role for mobile delivery registration
        $deliveryRecorder = Role::firstOrCreate(['name' => 'registrador_entregas']);
        
        $this->command->info('Created service provider roles: tratorista, motorista, diarista, tecnico, registrador_entregas');

        // Create a super admin user
        $user = User::firstOrCreate(
            ['email' => 'admin@sgc.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        $user->assignRole($superAdmin);

        // Create a demo associate user
        $associateUser = User::firstOrCreate(
            ['email' => 'associado@sgc.com'],
            [
                'name' => 'João Silva',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        $associateUser->assignRole($associate);

        // Create the associate profile
        \App\Models\Associate::firstOrCreate(
            ['user_id' => $associateUser->id],
            [
                'cpf_cnpj' => '123.456.789-00',
                'dap_caf' => 'DAP123456',
                'dap_caf_expiry' => now()->addYear(),
                'city' => 'Campo Grande',
                'state' => 'MS',
            ]
        );
    }
}
