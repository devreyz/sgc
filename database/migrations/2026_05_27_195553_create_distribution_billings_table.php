<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_billings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('sales_project_id')->index();
            $table->unsignedBigInteger('associate_id')->nullable()->index();
            $table->string('reference')->nullable();
            $table->date('billing_date');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('total_gross', 14, 4)->default(0);
            $table->decimal('total_admin_fee', 14, 4)->default(0);
            $table->decimal('total_net', 14, 4)->default(0);
            $table->integer('total_distributions')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('sales_project_id')->references('id')->on('sales_projects');
            $table->foreign('associate_id')->references('id')->on('associates');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_billings');
    }
};
