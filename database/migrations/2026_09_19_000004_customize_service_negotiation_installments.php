<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_payment_plan_installments', 'kind')) {
            Schema::table('service_payment_plan_installments', function (Blueprint $table): void {
                $table->string('kind', 20)->default('installment')->after('number');
            });
        }
        if (! Schema::hasColumn('service_payment_plan_installments', 'service_payment_event_id')) {
            Schema::table('service_payment_plan_installments', function (Blueprint $table): void {
                $table->unsignedBigInteger('service_payment_event_id')->nullable()->after('status');
            });
        }
        if (! Schema::hasColumn('service_payment_plan_installments', 'paid_at')) {
            Schema::table('service_payment_plan_installments', function (Blueprint $table): void {
                $table->timestamp('paid_at')->nullable()->after('service_payment_event_id');
            });
        }
        Schema::table('service_payment_plan_installments', function (Blueprint $table): void {
            $table->foreign('service_payment_event_id', 'svc_plan_installment_event_fk')
                ->references('id')->on('service_payment_events')->restrictOnDelete();
            $table->unique('service_payment_event_id', 'svc_plan_installment_payment_uq');
        });
    }

    public function down(): void
    {
        Schema::table('service_payment_plan_installments', function (Blueprint $table): void {
            $table->dropUnique('svc_plan_installment_payment_uq');
            $table->dropForeign('svc_plan_installment_event_fk');
            $table->dropColumn('service_payment_event_id');
            $table->dropColumn(['kind', 'paid_at']);
        });
    }
};
