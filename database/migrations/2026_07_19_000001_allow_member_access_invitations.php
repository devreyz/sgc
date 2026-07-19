<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('access_invitations', 'tenant_user_id')) {
            Schema::table('access_invitations', function (Blueprint $table) {
                $table->foreignId('tenant_user_id')
                    ->nullable()
                    ->after('associate_id')
                    ->constrained('tenant_user')
                    ->restrictOnDelete();
                $table->index(
                    ['tenant_id', 'tenant_user_id', 'status'],
                    'access_invitation_member_status_idx'
                );
            });
        }

        Schema::table('access_invitations', function (Blueprint $table) {
            $table->dropForeign(['associate_id']);
        });

        Schema::table('access_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('associate_id')->nullable()->change();
            $table->foreign('associate_id')
                ->references('id')
                ->on('associates')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_invitations', function (Blueprint $table) {
            $table->dropForeign(['tenant_user_id']);
            $table->dropIndex('access_invitation_member_status_idx');
            $table->dropColumn('tenant_user_id');
        });

        Schema::table('access_invitations', function (Blueprint $table) {
            $table->dropForeign(['associate_id']);
            $table->unsignedBigInteger('associate_id')->nullable(false)->change();
            $table->foreign('associate_id')
                ->references('id')
                ->on('associates')
                ->cascadeOnDelete();
        });
    }
};
