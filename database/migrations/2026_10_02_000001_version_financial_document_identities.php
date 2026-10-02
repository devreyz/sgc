<?php

use App\Models\FinancialDocumentIdentity;
use App\Services\FinancialDocumentIdentityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_document_identities', function (Blueprint $table): void {
            $table->dropUnique('financial_document_owner_unique');
            $table->unsignedInteger('revision')->default(1)->after('reference_code');
            $table->char('document_hash', 64)->nullable()->after('revision');
            $table->json('document_snapshot')->nullable()->after('document_hash');
            $table->timestamp('invalidated_at')->nullable()->after('documentable_id');
            $table->foreignId('invalidated_by')->nullable()->after('invalidated_at')->constrained('users')->nullOnDelete();
            $table->string('invalidation_reason', 255)->nullable()->after('invalidated_by');
            $table->unique(
                ['documentable_type', 'documentable_id', 'revision'],
                'financial_document_owner_revision_unique'
            );
            $table->index(
                ['documentable_type', 'documentable_id', 'invalidated_at'],
                'financial_document_current_index'
            );
        });

        // Registra o estado atual das identidades já existentes antes que novas
        // alterações possam ocorrer. Assim, o primeiro documento reemitido após
        // a implantação também recebe um QR diferente do que já foi impresso.
        FinancialDocumentIdentity::withoutGlobalScopes()
            ->orderBy('id')
            ->chunkById(200, function ($identities): void {
                foreach ($identities as $identity) {
                    $document = $identity->documentable()->withoutGlobalScopes()->first();
                    if ($document) {
                        app(FinancialDocumentIdentityService::class)->ensure($document);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('financial_document_identities', function (Blueprint $table): void {
            $table->dropIndex('financial_document_current_index');
            $table->dropUnique('financial_document_owner_revision_unique');
            $table->dropConstrainedForeignId('invalidated_by');
            $table->dropColumn([
                'revision',
                'document_hash',
                'document_snapshot',
                'invalidated_at',
                'invalidation_reason',
            ]);
            $table->unique(
                ['documentable_type', 'documentable_id'],
                'financial_document_owner_unique'
            );
        });
    }
};
