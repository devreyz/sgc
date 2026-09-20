<?php

use App\Models\FinancialReceipt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_receipts') || ! Schema::hasTable('financial_document_identities')) {
            return;
        }

        DB::table('financial_receipts')->select(['id', 'tenant_id'])->orderBy('id')
            ->chunkById(500, function ($receipts): void {
                foreach ($receipts as $receipt) {
                    $uuid = (string) Str::uuid();
                    DB::table('financial_document_identities')->insertOrIgnore([
                        'tenant_id' => $receipt->tenant_id,
                        'public_id' => $uuid,
                        'reference_code' => 'CP-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12)),
                        'documentable_type' => FinancialReceipt::class,
                        'documentable_id' => $receipt->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_document_identities')) {
            DB::table('financial_document_identities')
                ->where('documentable_type', FinancialReceipt::class)
                ->delete();
        }
    }
};
