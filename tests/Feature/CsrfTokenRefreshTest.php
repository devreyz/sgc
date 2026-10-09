<?php

namespace Tests\Feature;

use Tests\TestCase;

class CsrfTokenRefreshTest extends TestCase
{
    public function test_current_session_token_can_be_refreshed_without_cache(): void
    {
        $response = $this->withSession(['_token' => 'current-session-token'])
            ->getJson('/session/csrf-token');

        $response->assertOk()
            ->assertJsonPath('csrf_token', 'current-session-token');

        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_frontend_refreshes_restored_pages_and_retries_one_csrf_failure(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        self::assertStringContainsString("response.status !== 419", $script);
        self::assertStringContainsString("refreshCsrfToken({ force: true })", $script);
        self::assertStringContainsString("refreshCsrfToken({ force: event.persisted })", $script);
        self::assertStringContainsString("input[name=\"_token\"]", $script);
        self::assertStringContainsString("window.SgcCsrf", $script);
    }
}
