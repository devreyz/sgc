<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'view_delivery_conference_sheets',
        'create_delivery_conference_sheets',
        'issue_delivery_conference_sheets',
        'review_delivery_conference_sheets',
        'upload_delivery_conference_documents',
        'prepare_billing_from_delivery_conference',
        'cancel_delivery_conference_sheets',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->whereIn('name', ['super_admin', 'admin'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
        Role::query()->whereIn('name', ['financeiro', 'tesoureiro'])->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));
        Role::query()->where('name', 'registrador_entregas')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->whereIn('name', [
                'view_delivery_conference_sheets', 'create_delivery_conference_sheets',
                'issue_delivery_conference_sheets', 'review_delivery_conference_sheets',
                'upload_delivery_conference_documents',
            ])));
        Role::query()->where('name', 'presidente')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions->where('name', 'view_delivery_conference_sheets')));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
