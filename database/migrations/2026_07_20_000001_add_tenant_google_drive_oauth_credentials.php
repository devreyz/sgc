<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_cloud_storage_connections', function (Blueprint $table): void {
            $table->text('oauth_client_id')->nullable()->after('provider');
            $table->text('oauth_client_secret')->nullable()->after('oauth_client_id');
            $table->text('refresh_token')->nullable()->change();
            $table->timestamp('connected_at')->nullable()->change();
        });

        DB::table('tenant_cloud_storage_connections')
            ->where('status', 'active')
            ->update(['status' => 'configuration_required']);
    }

    public function down(): void
    {
        Schema::table('tenant_cloud_storage_connections', function (Blueprint $table): void {
            $table->dropColumn(['oauth_client_id', 'oauth_client_secret']);
        });
    }
};
