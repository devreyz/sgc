<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds initial tenant and super admin user for multi-tenant system.
     */
    public function run(): void
    {
        // Create default tenant with complete information
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'organizacao-principal'],
            [
                'name' => 'Cooperativa Agrícola Modelo',
                'legal_name' => 'Cooperativa Agrícola Modelo Ltda',
                'cnpj' => '12.345.678/0001-90',
                'email' => 'contato@cooperativamodelo.com.br',
                'phone' => '(67) 3333-4444',
                'mobile' => '(67) 99999-8888',
                'website' => 'www.cooperativamodelo.com.br',

                // Endereço
                'address' => 'Rua das Palmeiras',
                'address_number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'Campo Grande',
                'state' => 'MS',
                'zip_code' => '79002-000',
                'country' => 'Brasil',

                // Branding
                'primary_color' => '#10b981',
                'secondary_color' => '#6366f1',
                'accent_color' => '#f59e0b',

                // Institucional
                'description' => 'Cooperativa agrícola comprometida com o desenvolvimento sustentável e a valorização do produtor rural.',
                'mission' => 'Promover o desenvolvimento econômico e social dos cooperados através de práticas sustentáveis e inovadoras.',
                'vision' => 'Ser referência em cooperativismo agrícola, reconhecida pela qualidade e sustentabilidade de nossas ações.',
                'values' => 'Cooperação, Sustentabilidade, Transparência, Inovação, Valorização do Produtor',
                'foundation_date' => '2020-01-15',

                // Portal Público
                'has_public_portal' => true,
                'public_slug' => 'cooperativa-modelo',
                'public_description' => 'Somos uma cooperativa agrícola que trabalha com produtores rurais da região, oferecendo suporte técnico, comercialização de produtos e serviços especializados.',
                'public_features' => ['about', 'contact', 'products', 'news'],

                // Redes Sociais
                'social_media' => [
                    'facebook' => 'https://facebook.com/cooperativamodelo',
                    'instagram' => 'https://instagram.com/cooperativamodelo',
                    'whatsapp' => '5567999998888',
                ],

                // Dados Bancários
                'bank_name' => 'Banco do Brasil',
                'bank_code' => '001',
                'bank_agency' => '1234-5',
                'bank_account' => '12345-6',
                'pix_key' => '12.345.678/0001-90',

                // Responsável Legal
                'legal_representative_name' => 'João da Silva',
                'legal_representative_cpf' => '123.456.789-00',
                'legal_representative_role' => 'Presidente',

                // Configurações
                'active' => true,
                'settings' => [
                    'timezone' => 'America/Sao_Paulo',
                    'currency' => 'BRL',
                    'language' => 'pt-BR',
                ],
                'document_settings' => [
                    'header_height' => '80',
                    'footer_height' => '60',
                    'margin_top' => '20',
                    'margin_bottom' => '20',
                    'show_logo' => true,
                    'show_watermark' => false,
                ],
            ]
        );

        // Ensure super_admin role exists
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['guard_name' => 'web']
        );

        // Ensure admin role exists
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['guard_name' => 'web']
        );

        // Create super admin user if doesn't exist
        $superAdmin = User::firstOrCreate(
            ['email' => 'josereisleite2016@gmail.com'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('dfgskdghkajhdldfadjasdhfsdjfhskjhd'),
                'status' => true,
            ]
        );

        // Assign super_admin role
        if (! $superAdmin->hasRole('super_admin')) {
            $superAdmin->assignRole('super_admin');
        }

        // Create regular admin user for the tenant
        $admin = User::firstOrCreate(
            ['email' => 'reysilver901@gmail.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('fshgdagfkghoughoaudfgklashldj'),
                'status' => true,
            ]
        );

        // Assign admin role
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Link admin to tenant as admin
        if (! $tenant->users()->where('user_id', $admin->id)->exists()) {
            $tenant->addUser($admin, true);
        }

        $this->command->info('Tenant e usuários criados com sucesso!');
        $this->command->info('Super Admin: josereisleite2016@gmail.com / password');
        $this->command->info('Admin Tenant: reysilver901@gmail.com / password');
        $this->command->warn('⚠️  ALTERE AS SENHAS PADRÃO EM PRODUÇÃO!');
    }
}
