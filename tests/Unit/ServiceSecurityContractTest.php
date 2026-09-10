<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceSecurityContractTest extends TestCase
{
    #[Test]
    public function tenant_slug_requires_an_active_membership(): void
    {
        $code = file_get_contents(app_path('Http/Middleware/TenantFromSlugMiddleware.php'));
        $this->assertStringContainsString("->where('status', true)", $code);
        $this->assertStringContainsString('abort_unless', $code);
    }

    #[Test]
    public function operational_global_roles_are_not_accepted(): void
    {
        $code = file_get_contents(app_path('Http/Middleware/CheckAnyRole.php'));
        $this->assertStringNotContainsString('hasAnyRole($roles)', $code);
        $this->assertStringContainsString('hasRoleInTenant($roles, $tenantId)', $code);
    }

    #[Test]
    public function provider_portal_never_auto_creates_or_trusts_provider_id(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/Provider/ServiceProviderPortalController.php'));
        $this->assertStringNotContainsString('ServiceProvider::create', $code);
        $this->assertStringNotContainsString("input('provider_id'", $code);
        $this->assertMatchesRegularExpression("/where\('user_id',\s*\\\$request->user\(\)->id\)/", $code);
        $this->assertMatchesRegularExpression("/where\('service_provider_id',\s*\\\$provider->id\)/", $code);
    }

    #[Test]
    public function payments_are_locked_idempotent_and_tenant_scoped(): void
    {
        $code = file_get_contents(app_path('Services/Services/ServicePaymentService.php'));
        $this->assertMatchesRegularExpression("/where\('operation_key',\s*\\\$operationKey\)/", $code);
        $this->assertStringContainsString('lockForUpdate()', $code);
        $this->assertMatchesRegularExpression("/where\('tenant_id',\s*\\\$obligation->tenant_id\)/", $code);
        $this->assertStringContainsString('CashMovement', $code);
    }

    #[Test]
    public function new_service_evidence_uses_private_storage_and_hash(): void
    {
        $code = file_get_contents(app_path('Services/Services/ServiceEvidenceService.php'));
        $this->assertStringContainsString("Storage::disk('local')", $code);
        $this->assertStringContainsString("hash('sha256'", $code);
        $this->assertStringNotContainsString("Storage::disk('public')", $code);
    }
}
