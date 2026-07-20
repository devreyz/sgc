<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL pode deixar tabelas criadas quando um indice posterior falha.
        if (Schema::hasTable('cloud_documents')) {
            Schema::drop('cloud_documents');
        }
        if (Schema::hasTable('tenant_cloud_storage_connections')) {
            Schema::drop('tenant_cloud_storage_connections');
        }

        Schema::create('tenant_cloud_storage_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 24)->default('google_drive');
            $table->text('refresh_token');
            $table->text('granted_scopes')->nullable();
            $table->string('root_folder_id', 191)->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('connected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at');
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('cloud_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 24)->default('google_drive');
            $table->string('document_type', 64);
            $table->string('documentable_type', 100)->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->index(['documentable_type', 'documentable_id'], 'cloud_documents_documentable_idx');
            $table->string('remote_file_id', 191)->nullable();
            $table->string('remote_folder_id', 191)->nullable();
            $table->string('remote_path', 500);
            $table->char('checksum', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 24)->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'document_type', 'status'], 'cloud_documents_tenant_type_status_idx');
            $table->unique(
                ['tenant_id', 'provider', 'document_type', 'documentable_type', 'documentable_id'],
                'cloud_documents_owner_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_documents');
        Schema::dropIfExists('tenant_cloud_storage_connections');
    }
};
