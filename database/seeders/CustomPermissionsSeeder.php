<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeder para permissões customizadas do sistema.
 *
 * Estas permissões não são geradas pelo Filament Shield
 * e representam ações de negócio específicas.
 *
 * Execute: php artisan db:seed --class=CustomPermissionsSeeder
 */
class CustomPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $customPermissions = [
            // ── Serviços ──
            'service.attach_expense' => 'Vincular despesa a ordem de serviço',
            'service.apply_discount' => 'Aplicar desconto por arredondamento em parcelas',

            // ── Relatórios ──
            'report.generate'            => 'Gerar relatórios gerais da organização',
            'report.generate_individual' => 'Gerar relatório individual por associado',

            // ── Membros ──
            'member.create'     => 'Criar novo membro na organização',
            'member.deactivate' => 'Desativar vínculo de membro',
        ];

        foreach ($customPermissions as $name => $label) {
            Permission::firstOrCreate([
                'name'       => $name,
                'guard_name' => 'web',
            ]);
        }

        $this->command->info('✓ ' . count($customPermissions) . ' permissões customizadas criadas/verificadas.');

        // ── Atribuir ao admin ──
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $admin->givePermissionTo(array_keys($customPermissions));
            $this->command->info('✓ Permissões atribuídas ao role: admin');
        }

        // ── Atribuir ao super_admin ──
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            // Super admin recebe service e member, mas NÃO report (não opera como tenant)
            $superAdmin->givePermissionTo([
                'service.attach_expense',
                'service.apply_discount',
                'member.create',
                'member.deactivate',
            ]);
            $this->command->info('✓ Permissões técnicas atribuídas ao role: super_admin');
        }

        // ── Atribuir ao financeiro ──
        $financeiro = Role::where('name', 'financeiro')->first();
        if ($financeiro) {
            $financeiro->givePermissionTo([
                'service.attach_expense',
                'service.apply_discount',
                'report.generate',
                'report.generate_individual',
            ]);
            $this->command->info('✓ Permissões financeiras atribuídas ao role: financeiro');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('✓ Cache de permissões atualizado.');
    }
}
