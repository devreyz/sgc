<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $models = [
            \App\Models\CashMovement::class,
            \App\Models\DocumentTemplate::class,
            \App\Models\GeneratedDocument::class,
            \App\Models\ProjectPayment::class,
            \App\Models\ProviderPaymentRequest::class,
            \App\Models\ServiceProviderService::class,
            \App\Models\ServiceProviderWork::class,
            \App\Models\ServiceProviderLedger::class,
            \App\Models\ServiceOrder::class,
            \App\Models\ServiceProvider::class,
            \App\Models\Service::class,
            \App\Models\Revenue::class,
            \App\Models\SalesProject::class,
            \App\Models\PurchaseOrder::class,
            \App\Models\ProjectDemand::class,
            \App\Models\PurchaseItem::class,
            \App\Models\PurchaseOrderItem::class,
            \App\Models\Expense::class,
            \App\Models\Loan::class,
            \App\Models\LoanPayment::class,
            \App\Models\EquipmentReading::class,
            \App\Models\Equipment::class,
            \App\Models\DirectPurchase::class,
            \App\Models\DirectPurchaseItem::class,
            \App\Models\CollectivePurchase::class,
            \App\Models\GeneratedDocument::class,
            \App\Models\Document::class,
            \App\Models\StockMovement::class,
            \App\Models\BankAccount::class,
            \App\Models\ChartAccount::class,
            \App\Models\Associate::class,
            \App\Models\AssociateLedger::class,
            \App\Models\Asset::class,
            \App\Models\Customer::class,
            \App\Models\Supplier::class,
            \App\Models\Product::class,
            \App\Models\ProductCategory::class,
            \App\Models\MaintenanceRecord::class,
            \App\Models\MaintenanceSchedule::class,
            \App\Models\MaintenanceType::class,
        ];

        foreach ($models as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                    $tableBlueprint->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
                    try {
                        $tableBlueprint->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
                    } catch (\Throwable $e) {
                        // Ignore if foreign key creation fails in some DB platforms
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Attempt to drop tenant_id from the same set of tables if present.
        $models = [
            \App\Models\CashMovement::class,
            \App\Models\DocumentTemplate::class,
            \App\Models\GeneratedDocument::class,
            \App\Models\ProjectPayment::class,
            \App\Models\ProviderPaymentRequest::class,
            \App\Models\ServiceProviderService::class,
            \App\Models\ServiceProviderWork::class,
            \App\Models\ServiceProviderLedger::class,
            \App\Models\ServiceOrder::class,
            \App\Models\ServiceProvider::class,
            \App\Models\Service::class,
            \App\Models\Revenue::class,
            \App\Models\SalesProject::class,
            \App\Models\PurchaseOrder::class,
            \App\Models\ProjectDemand::class,
            \App\Models\PurchaseItem::class,
            \App\Models\PurchaseOrderItem::class,
            \App\Models\Expense::class,
            \App\Models\Loan::class,
            \App\Models\LoanPayment::class,
            \App\Models\EquipmentReading::class,
            \App\Models\Equipment::class,
            \App\Models\DirectPurchase::class,
            \App\Models\DirectPurchaseItem::class,
            \App\Models\CollectivePurchase::class,
            \App\Models\Document::class,
            \App\Models\StockMovement::class,
            \App\Models\BankAccount::class,
            \App\Models\ChartAccount::class,
            \App\Models\Associate::class,
            \App\Models\AssociateLedger::class,
            \App\Models\Asset::class,
            \App\Models\Customer::class,
            \App\Models\Supplier::class,
            \App\Models\Product::class,
            \App\Models\ProductCategory::class,
            \App\Models\MaintenanceRecord::class,
            \App\Models\MaintenanceSchedule::class,
            \App\Models\MaintenanceType::class,
        ];

        foreach ($models as $modelClass) {
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass();
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                continue;
            }

            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($table) {
                    try {
                        $tableBlueprint->dropForeign([ 'tenant_id' ]);
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    try {
                        $tableBlueprint->dropColumn('tenant_id');
                    } catch (\Throwable $e) {
                        // ignore
                    }
                });
            }
        }
    }
};
