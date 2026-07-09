<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_project_organizations', function (Blueprint $table) {
            $table->boolean('enforce_request_limits')
                ->default(false)
                ->after('notes')
                ->comment('Bloqueia distribuicoes acima do solicitado nesta organizacao/projeto.');
        });

        Schema::create('organization_authorized_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'organization_id', 'email'], 'org_auth_email_unique');
            $table->index(['tenant_id', 'email', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_authorized_emails');

        Schema::table('sales_project_organizations', function (Blueprint $table) {
            $table->dropColumn('enforce_request_limits');
        });
    }
};
