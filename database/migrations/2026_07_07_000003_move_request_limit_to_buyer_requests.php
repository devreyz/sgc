<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('buyer_requests') && ! Schema::hasColumn('buyer_requests', 'enforce_request_limits')) {
            Schema::table('buyer_requests', function (Blueprint $table) {
                $table->boolean('enforce_request_limits')
                    ->default(false)
                    ->after('status')
                    ->comment('Bloqueia distribuicoes acima das quantidades solicitadas nesta requisicao.');
            });
        }

        if (Schema::hasTable('organizations') && Schema::hasColumn('organizations', 'enforce_request_limits')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('enforce_request_limits');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
