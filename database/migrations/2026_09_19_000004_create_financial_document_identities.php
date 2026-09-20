<?php

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ServiceObligation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_document_identities', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('reference_code', 24)->unique();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['documentable_type', 'documentable_id'], 'financial_document_owner_unique');
            $table->index(['tenant_id', 'documentable_type'], 'financial_document_tenant_type_index');
        });

        Schema::create('financial_check_instruments', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('financial_document_identity_id');
            $table->uuid('operation_key');
            $table->uuid('delivery_operation_key')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('check_number', 80);
            $table->string('bank_name', 120);
            $table->string('account_reference', 120)->nullable();
            $table->date('issue_date');
            $table->date('expected_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('issued');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->foreign('financial_document_identity_id', 'financial_check_identity_fk')
                ->references('id')->on('financial_document_identities')->restrictOnDelete();
            $table->unique(['tenant_id', 'operation_key'], 'financial_check_issue_operation_unique');
            $table->unique(['tenant_id', 'delivery_operation_key'], 'financial_check_delivery_operation_unique');
            $table->index(['financial_document_identity_id', 'status'], 'financial_check_identity_status_index');
        });

        foreach ([
            'associate_receipts' => AssociateReceipt::class,
            'customer_billing_receipts' => CustomerBillingReceipt::class,
            'service_obligations' => ServiceObligation::class,
        ] as $table => $type) {
            DB::table($table)->select(['id', 'tenant_id'])->orderBy('id')->chunkById(500, function ($records) use ($type): void {
                $now = now();
                $rows = $records->map(function ($record) use ($type, $now): array {
                    $uuid = (string) Str::uuid();

                    return [
                        'tenant_id' => $record->tenant_id,
                        'public_id' => $uuid,
                        'reference_code' => 'CP-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12)),
                        'documentable_type' => $type,
                        'documentable_id' => $record->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('financial_document_identities')->insert($rows);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_check_instruments');
        Schema::dropIfExists('financial_document_identities');
    }
};
