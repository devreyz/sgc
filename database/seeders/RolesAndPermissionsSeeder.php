<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Cria roles comuns do sistema e atribui permissions apropriadas.
     * Roles são globais, mas a atribuição a usuários é feita por tenant via pivot table.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar role super_admin (acesso global ao sistema)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        // Criar roles comuns do sistema (atribuídas por tenant via pivot)
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $financeiro = Role::firstOrCreate(['name' => 'financeiro', 'guard_name' => 'web']);
        $operadorCaixa = Role::firstOrCreate(['name' => 'operador_caixa', 'guard_name' => 'web']);
        $assistente = Role::firstOrCreate(['name' => 'assistente', 'guard_name' => 'web']);
        $associado = Role::firstOrCreate(['name' => 'associado', 'guard_name' => 'web']);

        // Service Provider roles (can have multiple)
        $serviceProvider = Role::firstOrCreate(['name' => 'service_provider', 'guard_name' => 'web']);
        $tratorista = Role::firstOrCreate(['name' => 'tratorista', 'guard_name' => 'web']);
        $motorista = Role::firstOrCreate(['name' => 'motorista', 'guard_name' => 'web']);
        $diarista = Role::firstOrCreate(['name' => 'diarista', 'guard_name' => 'web']);
        $tecnico = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);

        // Delivery recorder role for mobile delivery registration
        $deliveryRecorder = Role::firstOrCreate(['name' => 'registrador_entregas', 'guard_name' => 'web']);

        $this->command->info('✓ Roles criadas: super_admin, admin, financeiro, operador_caixa, assistente, associado, prestadores');

        // Obter todas as permissions do Shield
        $allPermissions = Permission::all();

        if ($allPermissions->isEmpty()) {
            $this->command->warn('⚠ Nenhuma permission encontrada. Execute: php artisan shield:generate --all');
            return;
        }

        // Atribuir todas as permissions ao super_admin
        $superAdmin->syncPermissions($allPermissions);
        $this->command->info('✓ Super Admin: todas as permissions atribuídas');

        // Atribuir permissions ao role 'admin' (administrador de organização)
        if ($admin) {
            // Admin tem acesso a quase tudo, exceto gerenciamento de roles/permissions via Shield
            $adminPermissions = $allPermissions->filter(function ($permission) {
                // Excluir permissions de shield (roles e permissions) - apenas super_admin pode gerenciar
                return ! str_contains($permission->name, 'shield::');
            });
            $admin->syncPermissions($adminPermissions);
            $this->command->info('✓ Admin: ' . $adminPermissions->count() . ' permissions atribuídas');
        }

        // Atribuir permissions ao role 'financeiro'
        if ($financeiro) {
            $financeiroPermissions = $allPermissions->filter(function ($permission) {
                // Acesso a módulos financeiros
                $financeModules = [
                    'cash_movement',
                    'ledger',
                    'expense',
                    'bank_account',
                    'payment',
                    'provider',
                    'provider_ledger',
                    'direct_purchase',
                    'collective_purchase',
                    'purchase_order',
                    'project',
                    'loan',
                    'document',
                ];

                foreach ($financeModules as $module) {
                    if (str_contains($permission->name, $module)) {
                        return true;
                    }
                }

                return false;
            });
            $financeiro->syncPermissions($financeiroPermissions);
            $this->command->info('✓ Financeiro: ' . $financeiroPermissions->count() . ' permissions atribuídas');
        }

        // Atribuir permissions ao role 'operador_caixa'
        if ($operadorCaixa) {
            $operadorPermissions = $allPermissions->filter(function ($permission) {
                // Acesso apenas a movimentações de caixa e consultas básicas
                $modules = ['cash_movement', 'bank_account'];

                foreach ($modules as $module) {
                    if (str_contains($permission->name, $module)) {
                        // Apenas view, create, update - sem delete
                        return ! str_contains($permission->name, 'delete');
                    }
                }

                return false;
            });
            $operadorCaixa->syncPermissions($operadorPermissions);
            $this->command->info('✓ Operador Caixa: ' . $operadorPermissions->count() . ' permissions atribuídas');
        }

        // Atribuir permissions ao role 'assistente'
        if ($assistente) {
            // Apenas view permissions
            $assistentePermissions = $allPermissions->filter(function ($permission) {
                return str_contains($permission->name, 'view');
            });
            $assistente->syncPermissions($assistentePermissions);
            $this->command->info('✓ Assistente: ' . $assistentePermissions->count() . ' permissions (somente visualização)');
        }

        // Roles 'associado' e 'prestador' não têm permissions do painel admin
        // Eles têm acesso apenas aos seus portais específicos
        $this->command->info('✓ Associado/Prestadores: sem permissions admin (acesso via portais)');

        // Criar usuário super admin de demonstração
        $superAdminUser = User::firstOrCreate(
            ['email' => 'admin@sgc.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        $superAdminUser->assignRole($superAdmin);
        $this->command->info('✓ Usuário Super Admin criado: admin@sgc.com / password');

        // Criar usuário associado de demonstração
        $associateUser = User::firstOrCreate(
            ['email' => 'associado@sgc.com'],
            [
                'name' => 'João Silva',
                'password' => Hash::make('password'),
                'status' => true,
            ]
        );

        $associateUser->assignRole($associado);

        // Criar perfil de associado
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

        $this->command->info('✓ Usuário Associado criado: associado@sgc.com / password');

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('✓ Seeder executado com sucesso!');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('Roles disponíveis: ' . Role::pluck('name')->implode(', '));
        $this->command->info('Total de permissions: ' . Permission::count());
        $this->command->info('');
        $this->command->info('IMPORTANTE: Admin não pode criar/editar roles - apenas atribuir!');
        $this->command->info('Roles são atribuídas por tenant via campo "roles" na tabela tenant_user');
    }
}

