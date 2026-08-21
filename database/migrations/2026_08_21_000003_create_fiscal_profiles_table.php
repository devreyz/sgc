<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_profiles', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->nullable()->constrained('sales_projects')->restrictOnDelete();
            $table->string('scope_key', 80);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('active_marker')->nullable();
            $table->string('document_type', 20)->nullable();
            $table->string('amount_source', 40)->nullable();
            $table->boolean('require_issuer_tax_id')->default(true);
            $table->boolean('require_issuer_address')->default(true);
            $table->boolean('require_recipient_tax_id')->default(true);
            $table->boolean('require_xml')->default(true);
            $table->boolean('require_pdf')->default(true);
            $table->text('standard_notes')->nullable();
            $table->char('profile_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'scope_key', 'version'], 'fiscal_profile_version_unique');
            $table->unique(['tenant_id', 'scope_key', 'active_marker'], 'fiscal_profile_active_unique');
            $table->index(['tenant_id', 'sales_project_id', 'status'], 'fiscal_profile_resolution_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_profiles');
    }
};
