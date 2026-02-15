<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Expande a tabela tenants com informações completas da organização
     * para suportar personalização de documentos, portal público, branding, etc.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $blueprint) {
            // Identificação legal
            $blueprint->string('legal_name', 255)->nullable()->after('name');
            $blueprint->string('cnpj', 18)->nullable()->unique()->after('slug');
            $blueprint->string('state_registration', 50)->nullable()->after('cnpj');
            $blueprint->string('municipal_registration', 50)->nullable()->after('state_registration');
            
            // Contato
            $blueprint->string('email', 191)->nullable()->after('municipal_registration');
            $blueprint->string('phone', 20)->nullable()->after('email');
            $blueprint->string('mobile', 20)->nullable()->after('phone');
            $blueprint->string('website', 255)->nullable()->after('mobile');
            
            // Endereço completo
            $blueprint->string('address', 255)->nullable()->after('website');
            $blueprint->string('address_number', 20)->nullable()->after('address');
            $blueprint->string('address_complement', 100)->nullable()->after('address_number');
            $blueprint->string('neighborhood', 100)->nullable()->after('address_complement');
            $blueprint->string('city', 100)->nullable()->after('neighborhood');
            $blueprint->string('state', 2)->nullable()->after('city');
            $blueprint->string('zip_code', 10)->nullable()->after('state');
            $blueprint->string('country', 50)->default('Brasil')->after('zip_code');
            
            // Geolocalização
            $blueprint->decimal('latitude', 10, 7)->nullable()->after('country');
            $blueprint->decimal('longitude', 10, 7)->nullable()->after('latitude');
            
            // Branding e personalização
            $blueprint->string('logo', 255)->nullable()->after('longitude')->comment('Caminho para o arquivo de logo');
            $blueprint->string('logo_dark', 255)->nullable()->after('logo')->comment('Logo para tema escuro');
            $blueprint->string('favicon', 255)->nullable()->after('logo_dark');
            $blueprint->string('primary_color', 7)->default('#10b981')->after('favicon')->comment('Cor primária em hexadecimal');
            $blueprint->string('secondary_color', 7)->default('#6366f1')->after('primary_color')->comment('Cor secundária em hexadecimal');
            $blueprint->string('accent_color', 7)->default('#f59e0b')->after('secondary_color')->comment('Cor de destaque');
            
            // Informações institucionais
            $blueprint->text('description')->nullable()->after('accent_color')->comment('Descrição da organização');
            $blueprint->text('mission')->nullable()->after('description')->comment('Missão');
            $blueprint->text('vision')->nullable()->after('mission')->comment('Visão');
            $blueprint->text('values')->nullable()->after('vision')->comment('Valores');
            $blueprint->date('foundation_date')->nullable()->after('values')->comment('Data de fundação');
            
            // Redes sociais (JSON)
            $blueprint->json('social_media')->nullable()->after('foundation_date')->comment('Links para redes sociais');
            
            // Portal público
            $blueprint->boolean('has_public_portal')->default(false)->after('active')->comment('Portal público ativo');
            $blueprint->string('public_slug', 100)->nullable()->unique()->after('has_public_portal')->comment('Slug para acesso público');
            $blueprint->text('public_description')->nullable()->after('public_slug')->comment('Descrição pública da organização');
            $blueprint->json('public_features')->nullable()->after('public_description')->comment('Features ativas no portal público');
            
            // Configurações de documentos
            $blueprint->json('document_settings')->nullable()->after('settings')->comment('Configurações para geração de documentos');
            
            // Dados bancários da organização
            $blueprint->string('bank_name', 100)->nullable()->after('document_settings');
            $blueprint->string('bank_code', 10)->nullable()->after('bank_name');
            $blueprint->string('bank_agency', 20)->nullable()->after('bank_code');
            $blueprint->string('bank_account', 20)->nullable()->after('bank_agency');
            $blueprint->string('pix_key', 191)->nullable()->after('bank_account');
            
            // Responsável legal
            $blueprint->string('legal_representative_name', 255)->nullable()->after('pix_key');
            $blueprint->string('legal_representative_cpf', 14)->nullable()->after('legal_representative_name');
            $blueprint->string('legal_representative_role', 100)->nullable()->after('legal_representative_cpf')->comment('Cargo do responsável');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $blueprint) {
            $blueprint->dropColumn([
                'legal_name',
                'cnpj',
                'state_registration',
                'municipal_registration',
                'email',
                'phone',
                'mobile',
                'website',
                'address',
                'address_number',
                'address_complement',
                'neighborhood',
                'city',
                'state',
                'zip_code',
                'country',
                'latitude',
                'longitude',
                'logo',
                'logo_dark',
                'favicon',
                'primary_color',
                'secondary_color',
                'accent_color',
                'description',
                'mission',
                'vision',
                'values',
                'foundation_date',
                'social_media',
                'has_public_portal',
                'public_slug',
                'public_description',
                'public_features',
                'document_settings',
                'bank_name',
                'bank_code',
                'bank_agency',
                'bank_account',
                'pix_key',
                'legal_representative_name',
                'legal_representative_cpf',
                'legal_representative_role',
            ]);
        });
    }
};
