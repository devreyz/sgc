<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('cash_movements')) {
            return;
        }

        Schema::table('cash_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_movements', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('cash_movements')) {
            return;
        }

        Schema::table('cash_movements', function (Blueprint $table) {
            if (Schema::hasColumn('cash_movements', 'tenant_id')) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $foreignKeys = $sm->listTableForeignKeys('cash_movements');

                foreach ($foreignKeys as $fk) {
                    if (in_array('tenant_id', $fk->getLocalColumns(), true)) {
                        $table->dropForeign($fk->getName());
                    }
                }

                $table->dropColumn('tenant_id');
            }
        });
    }
};
