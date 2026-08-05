<?php

namespace Tests\Unit;

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\SalesProject;
use App\Services\ProjectReceiptNumberingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectReceiptNumberingServiceTest extends TestCase
{
    public function test_project_format_identifies_producer_and_customer_receipts(): void
    {
        $project = new SalesProject([
            'receipt_numbering_scope' => ProjectReceiptNumberingService::PROJECT_YEAR,
            'receipt_project_reference' => 'P12',
            'receipt_number_format' => '{prefix}{number}/{year}-{project}',
        ]);
        $project->id = 12;

        $service = new ProjectReceiptNumberingService;

        $this->assertSame('0001/2024-P12', $service->format($project, 1, 2024));
        $this->assertSame('COM-0001/2024-P12', $service->format($project, 1, 2024, 'COM-'));
    }

    public function test_invalid_format_is_rejected_and_receipt_snapshot_is_stable(): void
    {
        $service = new ProjectReceiptNumberingService;

        $this->assertNull($service->validatedFormat('{number}<script>'));
        $this->assertNull($service->validatedFormat('{project}/{year}'));

        $associateReceipt = new AssociateReceipt(['receipt_label' => '0042/2026-P9']);
        $customerReceipt = new CustomerBillingReceipt(['receipt_label' => 'COM-0010/2026-P9']);

        $this->assertSame('0042/2026-P9', $associateReceipt->formatted_number);
        $this->assertSame('COM-0010/2026-P9', $customerReceipt->formatted_number);
    }

    public function test_project_sequence_does_not_consume_numbers_from_another_project(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
        });
        Schema::create('receipt_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id')->nullable();
            $table->string('scope_key');
            $table->string('receipt_type');
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('last_number');
            $table->timestamps();
            $table->unique(['tenant_id', 'scope_key', 'receipt_type', 'receipt_year']);
        });

        DB::table('associate_receipts')->insert([
            ['tenant_id' => 1, 'sales_project_id' => 12, 'receipt_year' => 2024, 'receipt_number' => 1],
            ['tenant_id' => 1, 'sales_project_id' => 13, 'receipt_year' => 2024, 'receipt_number' => 8],
        ]);

        $project = new SalesProject(['receipt_numbering_scope' => ProjectReceiptNumberingService::PROJECT_YEAR]);
        $project->id = 12;

        $service = new ProjectReceiptNumberingService;

        $this->assertSame(2, $service->nextNumber(AssociateReceipt::class, 1, 2024, $project));
        $this->assertSame(3, $service->nextNumber(AssociateReceipt::class, 1, 2024, $project));

        $project->receipt_numbering_scope = ProjectReceiptNumberingService::TENANT_YEAR;
        $this->assertSame(9, $service->nextNumber(AssociateReceipt::class, 1, 2024, $project));
    }
}
