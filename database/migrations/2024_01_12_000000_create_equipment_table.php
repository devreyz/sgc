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
        // Tabela de equipamentos/ativos
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 191)->unique()->nullable(); // Patrimônio
            $table->string('type'); // tractor, truck, harvester, implement, etc
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('year')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('plate')->nullable(); // Para veículos
            $table->decimal('current_hours', 10, 2)->default(0); // Horímetro atual
            $table->decimal('current_km', 10, 2)->default(0); // Odômetro atual
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_value', 12, 2)->nullable();
            $table->string('status')->default('active'); // active, maintenance, inactive
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabela de tipos de manutenção
        Schema::create('maintenance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('interval_type'); // hours, km, days
            $table->integer('interval_value'); // 500h, 10000km, 30 days
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->integer('warning_before')->default(50); // Avisar X antes
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Tabela de manutenções programadas
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('last_hours', 10, 2)->nullable(); // Última manutenção em X horas
            $table->decimal('last_km', 10, 2)->nullable();
            $table->date('last_date')->nullable();
            $table->decimal('next_hours', 10, 2)->nullable(); // Próxima em X horas
            $table->decimal('next_km', 10, 2)->nullable();
            $table->date('next_date')->nullable();
            $table->string('status')->default('pending'); // pending, overdue, completed
            $table->timestamps();

            $table->unique(['equipment_id', 'maintenance_type_id']);
        });

        // Tabela de registros de manutenção
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('maintenance_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('performed_date');
            $table->decimal('hours_at_maintenance', 10, 2)->nullable();
            $table->decimal('km_at_maintenance', 10, 2)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('performed_by')->nullable();
            $table->string('invoice_number')->nullable();
            $table->json('parts_replaced')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabela de atualizações de horímetro/odômetro
        Schema::create('equipment_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            $table->string('reading_type'); // hours, km
            $table->decimal('value', 10, 2);
            $table->date('reading_date');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_readings');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('maintenance_types');
        Schema::dropIfExists('equipment');
    }
};
