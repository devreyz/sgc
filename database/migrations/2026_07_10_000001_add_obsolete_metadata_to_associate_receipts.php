<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('associate_receipts')) {
            return;
        }

        Schema::table('associate_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('associate_receipts', 'obsolete_at')) {
                $table->timestamp('obsolete_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('associate_receipts', 'obsolete_by')) {
                $table->foreignId('obsolete_by')->nullable()->after('obsolete_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('associate_receipts', 'obsolete_reason')) {
                $table->string('obsolete_reason', 255)->nullable()->after('obsolete_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('associate_receipts')) {
            return;
        }

        Schema::table('associate_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('associate_receipts', 'obsolete_by')) {
                $table->dropConstrainedForeignId('obsolete_by');
            }

            $drop = array_values(array_filter([
                Schema::hasColumn('associate_receipts', 'obsolete_at') ? 'obsolete_at' : null,
                Schema::hasColumn('associate_receipts', 'obsolete_reason') ? 'obsolete_reason' : null,
            ]));

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
