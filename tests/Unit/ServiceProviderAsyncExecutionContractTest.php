<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceProviderAsyncExecutionContractTest extends TestCase
{
    #[Test]
    public function provider_execution_uses_fetch_and_preserves_the_form_on_validation_errors(): void
    {
        $view = file_get_contents(resource_path('views/provider/services-show.blade.php'));

        $this->assertStringContainsString('data-async-service-form', $view);
        $this->assertStringContainsString('await fetch(', $view);
        $this->assertStringContainsString("'Accept': 'application/json'", $view);
        $this->assertStringContainsString('Tudo o que foi preenchido continua na tela.', $view);
        $this->assertStringContainsString('localStorage.setItem(', $view);
        $this->assertStringContainsString('serviceLocalKey', $view);
        $this->assertStringNotContainsString('form.submit()', $view);
    }

    #[Test]
    public function images_have_automatic_inline_preview_with_a_browser_fallback(): void
    {
        $input = file_get_contents(resource_path('views/provider/_service-evidence-input.blade.php'));
        $view = file_get_contents(resource_path('views/provider/services-show.blade.php'));

        $this->assertStringContainsString('svc-file-preview', $input);
        $this->assertStringContainsString('<img', $input);
        $this->assertStringContainsString('src="{{ $downloadUrl }}"', $input);
        $this->assertStringContainsString('new FileReader()', $view);
        $this->assertStringContainsString('image.alt =', $view);
        $this->assertStringContainsString('`Prévia de ${file.name}`', $view);
    }

    #[Test]
    public function controller_returns_json_for_async_mutations_and_keeps_redirect_compatibility(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Provider/ServiceProviderPortalController.php'));

        $this->assertStringContainsString('$request->expectsJson()', $controller);
        $this->assertStringContainsString('RedirectResponse|JsonResponse', $controller);
        $this->assertStringContainsString("'evidences' =>", $controller);
        $this->assertStringContainsString('return back()->with(', $controller);
    }
}
