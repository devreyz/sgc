<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ServicePortalRateLimitTest extends TestCase
{
    public function test_exhausting_a_preview_quota_does_not_block_other_service_operations(): void
    {
        $request = Request::create('/');
        $user = new User;
        $user->id = 1024;
        $request->setUserResolver(fn () => $user);

        $preview = $this->throttleArguments('provider.orders.preview');
        for ($attempt = 0; $attempt < (int) $preview[0]; $attempt++) {
            $this->assertSame(204, $this->runThrottle($request, $preview)->getStatusCode());
        }

        try {
            $this->runThrottle($request, $preview);
            $this->fail('The preview route must still enforce its quota.');
        } catch (ThrottleRequestsException $exception) {
            $this->assertSame(429, $exception->getStatusCode());
            $this->assertArrayHasKey('Retry-After', $exception->getHeaders());
        }

        foreach ([
            'provider.orders.draft',
            'provider.orders.submit',
            'provider.offline.sync',
            'services.management.execute.preview',
            'services.management.execute.submit',
            'services.management.documents.generate',
        ] as $route) {
            $this->assertSame(204, $this->runThrottle($request, $this->throttleArguments($route))->getStatusCode(), $route);
        }
    }

    private function throttleArguments(string $name): array
    {
        $middleware = collect(Route::getRoutes()->getByName($name)->gatherMiddleware())
            ->first(fn ($middleware) => str_starts_with($middleware, 'throttle:'));

        return explode(',', substr($middleware, strlen('throttle:')));
    }

    private function runThrottle(Request $request, array $arguments): \Symfony\Component\HttpFoundation\Response
    {
        return app(ThrottleRequests::class)->handle($request, fn () => response()->noContent(), ...$arguments);
    }
}
