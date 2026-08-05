<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'visible_sections')) {
            return;
        }

        DB::table('document_templates')
            ->where('template_category', 'system')
            ->where('system_template_key', 'customer_organization_receipt')
            ->whereNotNull('visible_sections')
            ->orderBy('id')
            ->get(['id', 'visible_sections'])
            ->each(function (object $template): void {
                $sections = json_decode((string) $template->visible_sections, true);
                if (! is_array($sections) || in_array('document_info', $sections, true)) {
                    return;
                }

                array_unshift($sections, 'document_info');
                DB::table('document_templates')->where('id', $template->id)->update([
                    'visible_sections' => json_encode(array_values(array_unique($sections))),
                ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_templates') || ! Schema::hasColumn('document_templates', 'visible_sections')) {
            return;
        }

        DB::table('document_templates')
            ->where('template_category', 'system')
            ->where('system_template_key', 'customer_organization_receipt')
            ->whereNotNull('visible_sections')
            ->orderBy('id')
            ->get(['id', 'visible_sections'])
            ->each(function (object $template): void {
                $sections = json_decode((string) $template->visible_sections, true);
                if (! is_array($sections)) {
                    return;
                }

                DB::table('document_templates')->where('id', $template->id)->update([
                    'visible_sections' => json_encode(array_values(array_filter(
                        $sections,
                        fn ($section): bool => $section !== 'document_info',
                    ))),
                ]);
            });
    }
};
