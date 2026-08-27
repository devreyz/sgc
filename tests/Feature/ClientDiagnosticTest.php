<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClientDiagnosticTest extends TestCase
{
    public function test_app_diagnostic_is_written_to_the_app_channel(): void
    {
        config()->set('logging.channels.app', ['driver' => 'null']);

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
