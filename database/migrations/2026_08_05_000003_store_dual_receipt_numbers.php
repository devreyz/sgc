<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['associate_receipts', 'customer_billing_receipts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('tenant_receipt_year')->nullable();
                $table->unsignedInteger('tenant_receipt_number')->nullable();
                $table->unsignedSmallInteger('project_receipt_year')->nullable();
                $table->unsignedInteger('project_receipt_number')->nullable();
            });

            $this->backfill($tableName);

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $prefix = $tableName === 'associate_receipts' ? 'associate' : 'customer';
                $table->unique(
                    ['tenant_id', 'tenant_receipt_year', 'tenant_receipt_number'],
                    $prefix.'_receipts_tenant_number_unique'
                );
                $table->unique(
                    ['tenant_id', 'sales_project_id', 'project_receipt_year', 'project_receipt_number'],
                    $prefix.'_receipts_project_sequence_unique'
                );
            });
        }

        $this->synchronizeSequenceTable();
    }

    private function backfill(string $tableName): void
    {
        $tenantUsed = [];
        $tenantNext = [];
        $projectNext = [];

        DB::table($tableName.' as receipts')
            ->leftJoin('sales_projects as projects', 'projects.id', '=', 'receipts.sales_project_id')
            ->orderBy('receipts.issued_at')
            ->orderBy('receipts.id')
            ->select([
                'receipts.id',
                'receipts.tenant_id',
                'receipts.sales_project_id',
                'receipts.receipt_year',
                'receipts.receipt_number',
                'receipts.issued_at',
                'projects.reference_year',
            ])
            ->chunk(250, function ($receipts) use ($tableName, &$tenantUsed, &$tenantNext, &$projectNext): void {
                foreach ($receipts as $receipt) {
                    $tenantYear = $this->yearFromDate($receipt->issued_at) ?: (int) $receipt->receipt_year;
                    $tenantKey = $receipt->tenant_id.':'.$tenantYear;
                    $candidate = max(1, (int) $receipt->receipt_number);

                    if (isset($tenantUsed[$tenantKey][$candidate])) {
                        $candidate = ($tenantNext[$tenantKey] ?? 0) + 1;
                        while (isset($tenantUsed[$tenantKey][$candidate])) {
                            $candidate++;
                        }
                    }

                    $tenantUsed[$tenantKey][$candidate] = true;
                    $tenantNext[$tenantKey] = max($tenantNext[$tenantKey] ?? 0, $candidate);

                    $projectYear = (int) ($receipt->reference_year ?: $receipt->receipt_year ?: $tenantYear);
                    $projectKey = $receipt->tenant_id.':'.($receipt->sales_project_id ?: 0).':'.$projectYear;
                    $projectNumber = ($projectNext[$projectKey] ?? 0) + 1;
                    $projectNext[$projectKey] = $projectNumber;

                    DB::table($tableName)->where('id', $receipt->id)->update([
                        'tenant_receipt_year' => $tenantYear,
                        'tenant_receipt_number' => $candidate,
                        'project_receipt_year' => $projectYear,
                        'project_receipt_number' => $projectNumber,
                    ]);
                }
            });
    }

    private function yearFromDate(mixed $value): ?int
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->year;
        } catch (Throwable) {
            return null;
        }
    }

    private function synchronizeSequenceTable(): void
    {
        if (! Schema::hasTable('receipt_number_sequences')) {
            return;
        }

        foreach ([
            'associate_receipts' => 'associate',
            'customer_billing_receipts' => 'customer',
        ] as $tableName => $receiptType) {
            DB::table($tableName)
                ->selectRaw('tenant_id, tenant_receipt_year as sequence_year, MAX(tenant_receipt_number) as maximum')
                ->whereNotNull('tenant_receipt_number')
                ->groupBy('tenant_id', 'tenant_receipt_year')
                ->get()
                ->each(fn ($row) => $this->upsertSequence(
                    (int) $row->tenant_id,
                    null,
                    'tenant',
                    $receiptType,
                    (int) $row->sequence_year,
                    (int) $row->maximum,
                ));

            DB::table($tableName)
                ->selectRaw('tenant_id, sales_project_id, project_receipt_year as sequence_year, MAX(project_receipt_number) as maximum')
                ->whereNotNull('sales_project_id')
                ->whereNotNull('project_receipt_number')
                ->groupBy('tenant_id', 'sales_project_id', 'project_receipt_year')
                ->get()
                ->each(fn ($row) => $this->upsertSequence(
                    (int) $row->tenant_id,
                    (int) $row->sales_project_id,
                    'project:'.$row->sales_project_id,
                    $receiptType,
                    (int) $row->sequence_year,
                    (int) $row->maximum,
                ));
        }
    }

    private function upsertSequence(
        int $tenantId,
        ?int $projectId,
        string $scopeKey,
        string $receiptType,
        int $year,
        int $maximum,
    ): void {
        DB::table('receipt_number_sequences')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'scope_key' => $scopeKey,
                'receipt_type' => $receiptType,
                'receipt_year' => $year,
            ],
            [
                'sales_project_id' => $projectId,
                'last_number' => $maximum,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        foreach (['associate_receipts', 'customer_billing_receipts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $prefix = $tableName === 'associate_receipts' ? 'associate' : 'customer';
                $table->dropUnique($prefix.'_receipts_tenant_number_unique');
                $table->dropUnique($prefix.'_receipts_project_sequence_unique');
                $table->dropColumn([
                    'tenant_receipt_year',
                    'tenant_receipt_number',
                    'project_receipt_year',
                    'project_receipt_number',
                ]);
            });
        }
    }
};
