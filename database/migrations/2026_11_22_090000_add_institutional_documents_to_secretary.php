<?php

use App\Services\SecretaryAccess;
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
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->json('institutional_settings')->nullable();
        });
        Schema::table('generated_documents', function (Blueprint $table): void {
            $table->unsignedSmallInteger('institutional_year')->nullable();
            $table->string('institutional_sector', 24)->nullable();
            $table->unsignedInteger('institutional_sequence')->nullable();
            $table->unique(['tenant_id', 'institutional_year', 'institutional_sector', 'institutional_sequence'], 'generated_institutional_sequence_unique');
        });
        Schema::create('generated_document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generated_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('event', 24);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at');
            $table->unique(['generated_document_id', 'revision'], 'generated_document_revision_unique');
        });

        $permissions = collect(SecretaryAccess::PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Role::query()->where('name', 'secretario')->where('guard_name', 'web')->first()
            ?->givePermissionTo($permissions->values()->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_document_revisions');
        Schema::table('generated_documents', function (Blueprint $table): void {
            $table->dropUnique('generated_institutional_sequence_unique');
            $table->dropColumn(['institutional_year', 'institutional_sector', 'institutional_sequence']);
        });
        Schema::table('document_templates', fn (Blueprint $table) => $table->dropColumn('institutional_settings'));
        Permission::query()->where('guard_name', 'web')->whereIn('name', array_diff_key(SecretaryAccess::PERMISSIONS, ['view' => true]))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
