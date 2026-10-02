<?php

namespace Tests\Feature;

use App\Models\AssociateReceipt;
use App\Services\FinancialDocumentIdentityService;
use App\Services\FinancialDocumentPaymentService;
use App\Services\FinancialDocumentPresenter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialDocumentIdentityVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('financial_document_identities');
        Schema::dropIfExists('associate_receipts');

        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->string('receipt_label')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->text('notes')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->string('status')->nullable();
            $table->decimal('total_gross', 14, 4)->default(0);
            $table->decimal('total_fees', 14, 4)->default(0);
            $table->decimal('total_net', 14, 4)->default(0);
            $table->json('fee_snapshot')->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('financial_document_identities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('public_id')->unique();
            $table->string('reference_code', 24)->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->char('document_hash', 64)->nullable();
            $table->json('document_snapshot')->nullable();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->timestamp('invalidated_at')->nullable();
            $table->unsignedBigInteger('invalidated_by')->nullable();
            $table->string('invalidation_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['documentable_type', 'documentable_id', 'revision']);
        });

        DB::table('associate_receipts')->insert([
            'id' => 10,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'receipt_year' => 2026,
            'receipt_number' => 8,
            'issued_at' => '2026-10-02',
            'delivery_ids' => json_encode([100, 101]),
            'status' => 'pending_payment',
            'total_gross' => 500,
            'total_fees' => 25,
            'total_net' => 475,
            'fee_snapshot' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_changed_document_gets_a_new_qr_and_the_printed_version_stays_obsolete(): void
    {
        $service = app(FinancialDocumentIdentityService::class);
        $receipt = AssociateReceipt::withoutGlobalScopes()->findOrFail(10);
        $printed = $service->ensure($receipt);

        $receipt->forceFill(['total_net' => 450])->saveQuietly();
        $receipt->refresh();

        self::assertFalse($service->isCurrent($printed->setRelation('documentable', $receipt)));

        $replacement = $service->ensure($receipt);
        $printed->refresh();

        self::assertNotSame($printed->public_id, $replacement->public_id);
        self::assertSame(1, $printed->revision);
        self::assertSame(2, $replacement->revision);
        self::assertNotNull($printed->invalidated_at);
        self::assertNull($replacement->invalidated_at);

        $printed->setRelation('documentable', $receipt);
        $printed->setRelation('checks', new Collection);
        $receipt->setRelation('project', null);
        $view = (new FinancialDocumentPresenter(app(FinancialDocumentPaymentService::class), $service))
            ->present($printed, null);

        self::assertFalse($view['is_current']);
        self::assertSame('obsolete', $view['technical_status']);
        self::assertSame([], $view['actions']);
    }

    public function test_reprinting_equal_material_data_preserves_the_same_qr(): void
    {
        $service = app(FinancialDocumentIdentityService::class);
        $receipt = AssociateReceipt::withoutGlobalScopes()->findOrFail(10);
        $printed = $service->ensure($receipt);
        $reprinted = $service->ensure($receipt->fresh());

        self::assertSame($printed->public_id, $reprinted->public_id);
        self::assertSame(1, $reprinted->revision);
        self::assertNull($printed->fresh()->invalidated_at);
        self::assertTrue($service->isCurrent($reprinted->setRelation('documentable', $receipt)));
    }

    public function test_distribution_change_is_material_and_creates_a_new_qr(): void
    {
        $service = app(FinancialDocumentIdentityService::class);
        $receipt = AssociateReceipt::withoutGlobalScopes()->findOrFail(10);
        $printed = $service->ensure($receipt);

        $receipt->forceFill(['delivery_ids' => [100, 101, 102]])->saveQuietly();
        $replacement = $service->ensure($receipt->fresh());

        self::assertNotSame($printed->public_id, $replacement->public_id);
        self::assertSame(2, $replacement->revision);
        self::assertNotNull($printed->fresh()->invalidated_at);
    }

    public function test_visual_age_and_renderer_changes_never_rotate_the_qr_identity(): void
    {
        config([
            'documents.automatic_refresh_on_view' => true,
            'documents.refresh_after_months' => 3,
            'documents.renderer_version' => '2026.10.02',
        ]);

        $service = app(FinancialDocumentIdentityService::class);
        $receipt = AssociateReceipt::withoutGlobalScopes()->findOrFail(10);
        $printed = $service->ensure($receipt);
        DB::table('financial_document_identities')->where('id', $printed->id)
            ->update(['created_at' => now()->subYear()]);
        config(['documents.renderer_version' => '2027.01.01']);

        $afterVisualPolicyChange = $service->ensure($receipt);

        self::assertSame($printed->public_id, $afterVisualPolicyChange->public_id);
        self::assertSame(1, $afterVisualPolicyChange->revision);
        self::assertNull($afterVisualPolicyChange->invalidated_at);
        self::assertDatabaseCount('financial_document_identities', 1);
    }

    public function test_legacy_visual_version_hash_is_repaired_without_rotating_the_qr(): void
    {
        $service = app(FinancialDocumentIdentityService::class);
        $receipt = AssociateReceipt::withoutGlobalScopes()->findOrFail(10);
        $printed = $service->ensure($receipt);
        $legacySnapshot = $printed->document_snapshot;
        $legacySnapshot['renderer_version'] = 'layout-antigo';
        ksort($legacySnapshot);
        $printed->forceFill([
            'document_snapshot' => $legacySnapshot,
            'document_hash' => hash('sha256', json_encode($legacySnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        ])->save();

        $repaired = $service->ensure($receipt);

        self::assertSame($printed->public_id, $repaired->public_id);
        self::assertSame(1, $repaired->revision);
        self::assertArrayNotHasKey('renderer_version', $repaired->document_snapshot);
        self::assertNull($repaired->invalidated_at);
    }
}
