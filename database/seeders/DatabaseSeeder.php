<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. Tenants e Roles/Permissions primeiro
            TenantSeeder::class,
            RolesAndPermissionsSeeder::class,
            RolesSeeder::class,
            
            // 2. Usuários e associados
            AdminUserSeeder::class,
            AssociatePermissionsSeeder::class,
            
            // 3. Dados de configuração
            ChartAccountSeeder::class,
            BankAccountSeeder::class,
            
            // 4. Serviços e fornecedores
            ServiceSeeder::class,
            ServiceProviderSeeder::class,
            
            // 5. Templates e documentos
            DocumentTemplatesSeeder::class,
            
            // 6. Atualizações (se houver)
            UpdateServicePricesSeeder::class,
        ]);
    }
}
