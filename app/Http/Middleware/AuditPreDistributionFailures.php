<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Durable failure metadata outside rolled-back business operations; no bodies, notes, tokens or names. */
final class AuditPreDistributionFailures
{
    public function handle(Request $request, Closure $next)
    {
        $applicable = config('pre_distributions.enabled') && ($request->routeIs('pre-distributions.*')
            || collect($request->input('distributions', []))->contains(fn ($row) => is_array($row) && ! empty($row['pre_distribution_item_id'])));
        if (! $applicable) {
            return $next($request);
        }
        try {
            $response = $next($request);
            if ($response->getStatusCode() >= 400) {
                $this->record($request, $response->getStatusCode());
            }

            return $response;
        } catch (\Throwable $error) {
            $this->record($request, $error instanceof ValidationException ? $error->status : ($error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500));
            throw $error;
        }
    }

    private function record(Request $request, int $status): void
    {
        $tenant = $request->route('tenant');
        $actor = $request->user();
        if (! $tenant instanceof Tenant || ! $actor) {
            return;
        }
        $key = $request->header('Idempotency-Key') ?: $request->input('operation_key');
        try {
            DB::table('pre_distribution_security_events')->insert(['tenant_id' => $tenant->id, 'actor_id' => $actor->id,
                'route' => (string) $request->route()->getName(), 'status_code' => $status, 'operation_key' => is_string($key) && Str::isUuid($key) ? $key : null, 'created_at' => now()]);
        } catch (\Throwable) {
            Log::warning('Falha ao persistir auditoria de pré-distribuições.', ['tenant_id' => $tenant->id, 'status_code' => $status]);
        }
    }
}
