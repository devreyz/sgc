<?php

namespace Tests\Unit;

use Tests\TestCase;

class AndroidAppEntryContractTest extends TestCase
{
    public function test_android_first_launch_uses_onboarding_and_then_opens_login(): void
    {
        $activity = file_get_contents(base_path('android/app/src/main/java/br/rzin/sgc/MainActivity.java'));
        $onboarding = file_get_contents(base_path('android/app/src/main/assets/onboarding.html'));

        self::assertStringContainsString('onboarding_complete', $activity);
        self::assertStringContainsString('file:///android_asset/onboarding.html', $activity);
        self::assertStringContainsString('https://sgc.rzin.com.br/login', $activity);
        self::assertStringContainsString('sgc://onboarding-complete', $onboarding);
    }

    public function test_app_user_agent_never_receives_the_public_welcome_view(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 SGCAndroid/1.0'
        )->get(route('home'));

        $response->assertOk()
            ->assertSee('Entrar no SGC')
            ->assertDontSee('Solicitar demonstração');
    }

    public function test_regular_browser_still_receives_the_public_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Solicitar demonstração')
            ->assertDontSee('SGC · Aplicativo');
    }
}
