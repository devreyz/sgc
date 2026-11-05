<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_distribution_security_events', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('route', 100);
            $table->unsignedSmallInteger('status_code');
            $table->uuid('operation_key')->nullable();
            $table->timestamp('created_at');
            $table->index(['tenant_id', 'created_at'], 'pdse_history_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pre_distribution_security_events') && DB::table('pre_distribution_security_events')->exists()) {
            throw new RuntimeException('Existem eventos de segurança; preserve a auditoria ao desabilitar o módulo.');
        }
        Schema::dropIfExists('pre_distribution_security_events');
    }
};
