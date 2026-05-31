<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expand ENUM to include all historic + new values
        DB::statement("ALTER TABLE customers MODIFY COLUMN `type` ENUM(
            'prefeitura','escola','creche','mercado','restaurante','hospital','outros','outro'
        ) NOT NULL DEFAULT 'outro'");
    }

    public function down(): void
    {
        // Revert to original values (may fail if newer values are in use)
        DB::statement("ALTER TABLE customers MODIFY COLUMN `type` ENUM(
            'prefeitura','escola','mercado','restaurante','hospital','outro'
        ) NOT NULL DEFAULT 'outro'");
    }
};
