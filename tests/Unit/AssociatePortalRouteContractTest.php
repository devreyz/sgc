<?php

namespace Tests\Unit;

use App\Http\Controllers\Associate\AssociatePortalDataController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AssociatePortalRouteContractTest extends TestCase
{
    public function test_ledger_route_resolves_section_from_request_instead_of_tenant_position(): void
    {
        $route = app('router')->getRoutes()->getByName('associate.data.ledger');

        $this->assertNotNull($route);
        $this->assertSame('{tenant}/associate/data/ledger/{section}', $route->uri());
        $this->assertStringContainsString('summary', $route->wheres['section'] ?? '');

        $method = new ReflectionMethod(AssociatePortalDataController::class, 'ledger');
        $parameters = $method->getParameters();

        $this->assertCount(1, $parameters);
        $this->assertSame(Request::class, $parameters[0]->getType()?->getName());
    }
}
