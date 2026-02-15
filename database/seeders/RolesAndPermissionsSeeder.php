<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure a tenant context for creating tenant-scoped seed data
        // (TenantSeeder runs before this and creates tenant ID 1)
        app()->instance('tenant.id', 1);
        session(['tenant_id' => 1]);

        // Create roles (idempotent)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'tenant_id' => 1]);
        $admin = Role::firstOrCreate(['name' => 'admin', 'tenant_id' => 1]);
        $financial = Role::firstOrCreate(['name' => 'financeiro', 'tenant_id' => 1]);
        $associate = Role::firstOrCreate(['name' => 'associado', 'tenant_id' => 1]);
        
        // Service Provider roles (can have multiple)
        $serviceProvider = Role::firstOrCreate(['name' => 'service_provider', 'tenant_id' => 1]); // Generic provider
        $tratorista = Role::firstOrCreate(['name' => 'tratorista', 'tenant_id' => 1]);
        $motorista = Role::firstOrCreate(['name' => 'motorista', 'tenant_id' => 1]);
        $diarista = Role::firstOrCreate(['name' => 'diarista', 'tenant_id' => 1]);
        $tecnico = Role::firstOrCreate(['name' => 'tecnico', 'tenant_id' => 1]);
        
        // Delivery recorder role for mobile delivery registration
        $deliveryRecorder = Role::firstOrCreate(['name' => 'registrador_entregas', 'tenant_id' => 1]);
        
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

        // Assign role with tenant pivot set (super admin user already flagged via is_super_admin)
        if (!$user->roles()->where('roles.id', $superAdmin->id)->wherePivot('tenant_id', 1)->exists()) {
            $user->roles()->attach($superAdmin->id, ['tenant_id' => 1]);
        }

        // Create a demo associate user
        $associateUser = User::firstOrCreate(
            ['email' => 'associado@sgc.com'],
            [
                'name' => 'João Silva',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        if (!$associateUser->roles()->where('roles.id', $associate->id)->wherePivot('tenant_id', 1)->exists()) {
            $associateUser->roles()->attach($associate->id, ['tenant_id' => 1]);
        }

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
