<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adiciona colunas de roles na tabela pivot tenant_user.
     * Isso permite que cada usuário tenha roles DIFERENTES em cada organização.
     */
    public function up(): void
    {
        // Adicionar coluna de roles (JSON) na tabela tenant_user
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('is_admin');
        });

        // Migrar roles existentes dos usuários para o pivot
        // Usuários que têm roles globais terão essas roles copiadas para cada organização
        $users = \App\Models\User::with(['roles', 'tenants'])->get();

        foreach ($users as $user) {
            $userRoleNames = $user->roles->pluck('name')->toArray();

            if (! empty($userRoleNames) && $user->tenants->isNotEmpty()) {
                foreach ($user->tenants as $tenant) {
                    // Atualizar o pivot com as roles
                    $user->tenants()->updateExistingPivot($tenant->id, [
                        'roles' => json_encode($userRoleNames),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_user', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
