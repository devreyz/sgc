<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keep the database constraint synchronized with App\Enums\ProjectStatus.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `sales_projects` MODIFY `status` ENUM('draft','active','suspended','deliveries_closed','completed','cancelled','archived') NOT NULL DEFAULT 'draft'");
    }

    /**
     * Retain deliveries_closed during rollback so existing projects are not truncated.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `sales_projects` MODIFY `status` ENUM('draft','active','suspended','deliveries_closed','completed','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
