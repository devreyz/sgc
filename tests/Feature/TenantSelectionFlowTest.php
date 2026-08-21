<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthenticationRedirector;
use App\Services\TenantResolver;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class TenantSelectionFlowTest extends TestCase
{
    public function test_selection_page_receives_complete_tenant_models(): void
    {
        $tenant = new Tenant([
            'name' => 'Organizacao de Teste',
            'slug' => 'organizacao-teste',
            'description' => 'Descricao segura',
        ]);
        $tenant->id = 10;

        $resolver = Mockery::mock(TenantResolver::class);
        $resolver->shouldReceive('getAvailableTenantModels')
            ->once()
            ->andReturn(collect([$tenant]));
        $this->app->instance(TenantResolver::class, $resolver);

        $user = new User(['name' => 'Usuario de Teste']);
        $user->id = 991;

        $this->actingAs($user)
            ->get(route('tenant.select'))
            ->assertOk()
            ->assertSee('Organizacao de Teste')
            ->assertSee('descricao segura');
    }

    public function test_new_login_always_clears_tenant_and_opens_selection(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->once()->andReturnFalse();

        $this->withSession([
            'tenant_id' => 10,
            'tenant_slug' => 'organizacao-anterior',
            'url.intended' => '/organizacao-anterior/delivery',
        ]);

        $path = app(AuthenticationRedirector::class)->pathAfterLogin($user);

        $this->assertSame(route('tenant.select'), $path);
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('tenant_slug'));
        $this->assertNull(session('url.intended'));
    }

    public function test_resolver_does_not_auto_select_the_only_membership(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->once()->andReturnFalse();
        Auth::shouldReceive('check')->once()->andReturnTrue();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $this->assertNull(app(TenantResolver::class)->autoSelectTenant());
        $this->assertNull(session('tenant_id'));
        $this->assertNull(session('tenant_slug'));
    }

    public function test_logout_removes_all_tenant_context(): void
    {
        $user = new User;
        $user->id = 991;

        $this->actingAs($user)
            ->withSession([
                'tenant_id' => 10,
                'tenant_slug' => 'organizacao-anterior',
                'url.intended' => '/organizacao-anterior/delivery',
            ])
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('tenant_id')
            ->assertSessionMissing('tenant_slug')
            ->assertSessionMissing('url.intended');

        $this->assertGuest();
    }
}
