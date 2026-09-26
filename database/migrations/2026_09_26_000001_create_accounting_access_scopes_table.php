<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_access_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_type', 40);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_value', 120)->nullable();
            $table->string('scope_key', 180);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'scope_key'], 'accounting_scope_unique');
            $table->index(['tenant_id', 'user_id', 'active'], 'accounting_scope_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_access_scopes');
    }
};
