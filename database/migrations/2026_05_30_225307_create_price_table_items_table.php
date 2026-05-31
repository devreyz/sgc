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
        Schema::create('price_table_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('price_table_id')->index();
            $table->unsignedBigInteger('product_id')->index();

            $table->decimal('sale_price', 10, 4);
            $table->decimal('cost_price', 10, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('price_table_id')->references('id')->on('price_tables')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->unique(['price_table_id', 'product_id'], 'pti_table_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_table_items');
    }
};
