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
        Schema::table('associates', function (Blueprint $table) {
            $table->string('member_code', 50)->nullable()->after('registration_number')->unique();
            $table->string('validation_token', 100)->nullable()->after('member_code')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('associates', function (Blueprint $table) {
            $table->dropColumn(['member_code', 'validation_token']);
        });
    }
};
