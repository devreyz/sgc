<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_google_drive_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->text('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();
        });

        Schema::table('tenant_cloud_storage_connections', function (Blueprint $table): void {
            $table->text('google_account_email')->nullable();
        });

        Schema::table('cloud_documents', function (Blueprint $table): void {
            $table->foreignId('drive_archive_id')->nullable()->constrained('archived_google_drive_connections', indexName: 'cloud_documents_drive_archive_fk')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cloud_documents', function (Blueprint $table): void {
            $table->dropForeign('cloud_documents_drive_archive_fk');
            $table->dropColumn('drive_archive_id');
        });
        Schema::table('tenant_cloud_storage_connections', fn (Blueprint $table) => $table->dropColumn('google_account_email'));
        Schema::dropIfExists('archived_google_drive_connections');
    }
};
