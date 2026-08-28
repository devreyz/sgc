<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ClientDiagnosticTest extends TestCase
{
    public function test_app_diagnostic_is_written_to_the_app_channel(): void
    {
        config()->set('logging.channels.app', ['driver' => 'null']);
        $event = new SecurityEvent;
        $event->forceFill(['ip_hash' => hash('sha256', 'test')]);
        $audit = Mockery::mock(SecurityAuditService::class)->makePartial();
        $audit->shouldReceive('record')
            ->once()
            ->with(
                'client_authentication_error',
                'reported',
                Mockery::on(fn (array $attributes): bool =>
                    ($attributes['context']['error_type'] ?? null) === 'GOOGLE_SIGN_IN_FAILED'
                    && ($attributes['context']['platform'] ?? null) === 'app'),
                Mockery::type(Request::class),
            )
            ->andReturn($event);
        $this->app->instance(SecurityAuditService::class, $audit);

        $response = $this->postJson(route('diagnostics.client'), [
            'platform' => 'app',
            'category' => 'authentication',
            'code' => 'GOOGLE_SIGN_IN_FAILED',
            'stage' => 'native_google',
            'message' => 'Falha controlada para diagnostico.',
            'path' => '/login',
            'app_version' => '1.0',
            'android_version' => '15',
            'device' => 'Test Device',
        ]);

        $response->assertNoContent();
    }

    public function test_diagnostic_rejects_unknown_categories_and_oversized_payloads(): void
    {
        $this->postJson(route('diagnostics.client'), [
            'platform' => 'app',
            'category' => 'token_dump',
            'message' => str_repeat('x', 501),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'message']);
    }

    public function test_login_response_cannot_restore_a_stale_processing_state_from_cache(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }
}
