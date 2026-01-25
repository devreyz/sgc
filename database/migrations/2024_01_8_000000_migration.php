<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('sales_projects', 'completion_notes')) {
                $table->text('completion_notes')->nullable()->after('completed_at');
            }
        });

        // Verificar se a tabela purchase_projects existe antes de alterar
        if (Schema::hasTable('purchase_projects')) {
            Schema::table('purchase_projects', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_projects', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('purchase_projects', 'completion_notes')) {
                    $table->text('completion_notes')->nullable()->after('completed_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'completion_notes']);
        });

        if (Schema::hasTable('purchase_projects')) {
            Schema::table('purchase_projects', function (Blueprint $table) {
                $table->dropColumn(['completed_at', 'completion_notes']);
            });
        }
    }
};
