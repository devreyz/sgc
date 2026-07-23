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
        Schema::create('delivery_project_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(
                ['tenant_id', 'sales_project_id', 'created_at'],
                'delivery_project_notes_lookup'
            );
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $view = Permission::firstOrCreate([
            'name' => 'view_delivery_monitor',
            'guard_name' => 'web',
        ]);
        $annotate = Permission::firstOrCreate([
            'name' => 'create_delivery_notes',
            'guard_name' => 'web',
        ]);
        $viewer = Role::firstOrCreate([
            'name' => 'visualizador_entregas',
            'guard_name' => 'web',
        ]);

        $viewer->syncPermissions([$view, $annotate]);
        Role::query()
            ->whereIn('name', ['super_admin', 'admin'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([$view, $annotate]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_notes');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::query()->where('name', 'visualizador_entregas')->delete();
        Permission::query()
            ->whereIn('name', ['view_delivery_monitor', 'create_delivery_notes'])
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
