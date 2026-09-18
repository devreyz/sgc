<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_simulations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('service_version_id')->constrained('service_versions')->cascadeOnDelete();
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 191);
            $table->string('status', 20)->default('success');
            $table->boolean('auto_filled')->default(true);
            $table->json('values');
            $table->json('result')->nullable();
            $table->json('diagnostics')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['tenant_id', 'service_version_id', 'created_at'], 'service_simulations_lookup');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'simulate_services', 'guard_name' => 'web']);
        Role::query()->whereIn('name', ['super_admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('service_simulations');
        Permission::query()->where('name', 'simulate_services')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
