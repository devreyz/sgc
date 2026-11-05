<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_distribution_reports', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('public_id')->unique();
            $table->uuid('series_id');
            $table->unsignedInteger('revision');
            $table->string('code', 64);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('operation_id');
            $table->json('snapshot');
            $table->string('path', 240);
            $table->char('sha256', 64);
            $table->timestamp('created_at');
            $table->unique(['tenant_id', 'series_id', 'revision'], 'pdr_series_revision_uq');
            $table->unique(['tenant_id', 'operation_id'], 'pdr_operation_uq');
            $table->foreign(['tenant_id', 'operation_id'], 'pdr_operation_fk')->references(['tenant_id', 'id'])->on('pre_distribution_operations')->restrictOnDelete();
            $table->index(['tenant_id', 'id'], 'pdr_history_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('pre_distribution_reports') && DB::table('pre_distribution_reports')->exists()) {
            throw new RuntimeException('Relações preliminares possuem snapshots. Desabilite o módulo sem apagar dados.');
        }
        Schema::dropIfExists('pre_distribution_reports');
    }
};
