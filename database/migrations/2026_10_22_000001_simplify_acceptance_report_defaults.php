<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates')) return;
        DB::table('document_templates')->where('template_category', 'system')
            ->where('system_template_key', 'billing_acceptance_term')->orderBy('id')
            ->chunkById(100, function ($templates): void {
                foreach ($templates as $template) {
                    $changes = [];
                    $sections = json_decode($template->visible_sections ?? 'null', true);
                    if (in_array($sections, [
                        ['context', 'declaration', 'table', 'totals', 'signature'],
                        ['context', 'declaration', 'table', 'signature'],
                    ], true)) $changes['visible_sections'] = json_encode(['context', 'table']);
                    if (json_decode($template->visible_columns ?? 'null', true) === ['quantity']) {
                        $changes['visible_columns'] = json_encode(['quantity', 'unit_price', 'gross']);
                    }
                    if ($changes) DB::table('document_templates')->where('id', $template->id)->update($changes);
                }
            });
    }

    public function down(): void
    {
        // Do not overwrite document preferences edited after this data migration.
    }
};
