<?php

namespace Tests\Feature;

use Tests\TestCase;

class ServiceRoleBoundaryTest extends TestCase
{
    public function test_accounting_role_cannot_mutate_service_configuration(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_19_000002_repair_service_accounting_role_boundaries.php'));

        $this->assertStringContainsString("replaceServicePermissions(['contador'], \$servicePermissions, [])", $migration);
        $this->assertStringContainsString("'manage_service_catalog'", $migration);
        $this->assertStringContainsString("'approve_service_execution'", $migration);
    }

    public function test_financial_role_can_settle_services_but_not_operate_them(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_19_000002_repair_service_accounting_role_boundaries.php'));

        $this->assertStringContainsString('private const FINANCIAL_SERVICE_PERMISSIONS', $migration);
        $this->assertStringContainsString("'manage_service_receivables'", $migration);
        $this->assertStringContainsString("'manage_service_agreements'", $migration);
        $this->assertStringContainsString("replaceServicePermissions(['financeiro']", $migration);
        $this->assertStringContainsString("replaceServicePermissions(['tesoureiro'], \$servicePermissions, self::SERVICE_PERMISSIONS)", $migration);
    }

    public function test_treasurer_has_full_service_scope_and_accounting_while_accountant_stays_accounting_only(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_19_000003_refine_treasury_accounting_and_service_access.php'));

        $this->assertStringContainsString("where('name', 'tesoureiro')", $migration);
        $this->assertStringContainsString('givePermissionTo($servicePermissions)', $migration);
        $this->assertStringContainsString("['super_admin', 'admin', 'tesoureiro', 'contador']", $migration);
        $this->assertStringContainsString("where('name', 'contador')", $migration);
        $this->assertStringContainsString('revokePermissionTo($servicePermissions)', $migration);
    }
}
