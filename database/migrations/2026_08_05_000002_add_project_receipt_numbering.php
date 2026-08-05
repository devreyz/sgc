<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sales_project_id')->nullable()->constrained('sales_projects')->cascadeOnDelete();
            $table->string('scope_key', 40);
            $table->string('receipt_type', 30);
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'scope_key', 'receipt_type', 'receipt_year'],
                'receipt_number_sequences_scope_unique'
            );
        });

        Schema::table('sales_projects', function (Blueprint $table) {
            $table->string('receipt_numbering_scope', 20)->default('tenant_year')->after('reference_year');
            $table->string('receipt_project_reference', 30)->nullable()->after('receipt_numbering_scope');
            $table->string('receipt_number_format', 80)
                ->default('{prefix}{number}/{year}')
                ->after('receipt_project_reference');
        });

        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->string('receipt_label', 80)->nullable()->after('receipt_number');
            $table->unique(
                ['tenant_id', 'sales_project_id', 'receipt_year', 'receipt_number'],
                'associate_receipts_project_number_unique'
            );
        });

        Schema::table('customer_billing_receipts', function (Blueprint $table) {
            $table->string('receipt_label', 80)->nullable()->after('receipt_number');
            $table->unique(
                ['tenant_id', 'sales_project_id', 'receipt_year', 'receipt_number'],
                'customer_receipts_project_number_unique'
            );
        });

        if (Schema::hasTable('document_templates') && Schema::hasColumn('document_templates', 'visible_sections')) {
            DB::table('document_templates')
                ->where('system_template_key', 'customer_billing_receipt')
                ->whereNotNull('visible_sections')
                ->orderBy('id')
                ->get(['id', 'visible_sections'])
                ->each(function ($template): void {
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

        DB::table('associate_receipts')
            ->whereNull('receipt_label')
            ->orderBy('id')
            ->select(['id', 'receipt_year', 'receipt_number'])
            ->chunkById(200, function ($receipts): void {
                foreach ($receipts as $receipt) {
                    DB::table('associate_receipts')->where('id', $receipt->id)->update([
                        'receipt_label' => str_pad((string) $receipt->receipt_number, 4, '0', STR_PAD_LEFT).'/'.$receipt->receipt_year,
                    ]);
                }
            });

        DB::table('customer_billing_receipts')
            ->whereNull('receipt_label')
            ->orderBy('id')
            ->select(['id', 'receipt_year', 'receipt_number'])
            ->chunkById(200, function ($receipts): void {
                foreach ($receipts as $receipt) {
                    DB::table('customer_billing_receipts')->where('id', $receipt->id)->update([
                        'receipt_label' => 'COM-'.str_pad((string) $receipt->receipt_number, 4, '0', STR_PAD_LEFT).'/'.$receipt->receipt_year,
                    ]);
                }
            });

        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->dropUnique('unique_receipt_number');
        });

        Schema::table('customer_billing_receipts', function (Blueprint $table) {
            $table->dropUnique('uniq_cob_number');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('document_templates') && Schema::hasColumn('document_templates', 'visible_sections')) {
            DB::table('document_templates')
                ->where('system_template_key', 'customer_billing_receipt')
                ->whereNotNull('visible_sections')
                ->orderBy('id')
                ->get(['id', 'visible_sections'])
                ->each(function ($template): void {
                    $sections = json_decode((string) $template->visible_sections, true);
                    if (! is_array($sections)) {
                        return;
                    }

                    DB::table('document_templates')->where('id', $template->id)->update([
                        'visible_sections' => json_encode(array_values(array_filter(
                            $sections,
                            fn ($section): bool => $section !== 'document_info'
                        ))),
                    ]);
                });
        }

        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->unique(['tenant_id', 'receipt_year', 'receipt_number'], 'unique_receipt_number');
            $table->dropUnique('associate_receipts_project_number_unique');
            $table->dropColumn('receipt_label');
        });

        Schema::table('customer_billing_receipts', function (Blueprint $table) {
            $table->unique(['tenant_id', 'receipt_year', 'receipt_number'], 'uniq_cob_number');
            $table->dropUnique('customer_receipts_project_number_unique');
            $table->dropColumn('receipt_label');
        });

        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_numbering_scope',
                'receipt_project_reference',
                'receipt_number_format',
            ]);
        });

        Schema::dropIfExists('receipt_number_sequences');
    }
};
