<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE `documents`
            MODIFY `category` ENUM(
                'contrato',
                'nota_fiscal',
                'comprovante',
                'dap_caf',
                'documento_pessoal',
                'licenca',
                'relatorio',
                'foto',
                'delivery_conference_signed',
                'outro'
            ) NOT NULL DEFAULT 'outro'
        SQL);
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents') || DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('documents')
            ->where('category', 'delivery_conference_signed')
            ->update(['category' => 'foto']);

        DB::statement(<<<'SQL'
            ALTER TABLE `documents`
            MODIFY `category` ENUM(
                'contrato',
                'nota_fiscal',
                'comprovante',
                'dap_caf',
                'documento_pessoal',
                'licenca',
                'relatorio',
                'foto',
                'outro'
            ) NOT NULL DEFAULT 'outro'
        SQL);
    }
};
