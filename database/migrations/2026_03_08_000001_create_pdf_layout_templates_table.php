<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pdf_layout_templates')) {
            Schema::create('pdf_layout_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->string('name');
                $table->string('layout_type')->default('header'); // 'header' | 'footer' | 'both'
                $table->longText('content'); // HTML content with variables
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('document_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('document_templates', 'template_category')) {
                $table->string('template_category')->default('custom')->after('type');
            }
            if (!Schema::hasColumn('document_templates', 'system_template_key')) {
                $table->string('system_template_key')->nullable()->after('template_category');
            }
            if (!Schema::hasColumn('document_templates', 'header_layout_id')) {
                $table->foreignId('header_layout_id')->nullable()->after('system_template_key')->constrained('pdf_layout_templates')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_templates', 'footer_layout_id')) {
                $table->foreignId('footer_layout_id')->nullable()->after('header_layout_id')->constrained('pdf_layout_templates')->nullOnDelete();
            }
            if (!Schema::hasColumn('document_templates', 'visible_sections')) {
                $table->json('visible_sections')->nullable()->after('footer_layout_id');
            }
            if (!Schema::hasColumn('document_templates', 'visible_columns')) {
                $table->json('visible_columns')->nullable()->after('visible_sections');
            }
            if (!Schema::hasColumn('document_templates', 'custom_fields')) {
                $table->json('custom_fields')->nullable()->after('visible_columns');
            }
            if (!Schema::hasColumn('document_templates', 'paper_size')) {
                $table->string('paper_size')->default('a4')->after('custom_fields');
            }
            if (!Schema::hasColumn('document_templates', 'paper_orientation')) {
                $table->string('paper_orientation')->default('portrait')->after('paper_size');
            }
            if (!Schema::hasColumn('document_templates', 'section_order')) {
                $table->json('section_order')->nullable()->after('paper_orientation');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropForeign(['header_layout_id']);
            $table->dropForeign(['footer_layout_id']);
            $table->dropColumn([
                'template_category', 'system_template_key',
                'header_layout_id', 'footer_layout_id',
                'visible_sections', 'visible_columns', 'custom_fields',
                'paper_size', 'paper_orientation', 'section_order',
            ]);
        });

        Schema::dropIfExists('pdf_layout_templates');
    }
};
