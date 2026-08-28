<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationFailureLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditAuthenticationFailures
{
    public function __construct(private readonly AuthenticationFailureLogger $logger) {}

    public function handle(Request $request, Closure $next, string $flow): Response
    {
        try {
            /** @var Response $response */
            $response = $next($request);

            if ($response->getStatusCode() >= 400
                && $request->attributes->get('authentication_failure_recorded') !== true) {
                $this->logger->record(
                    $request,
                    $flow,
                    'response',
                    $this->reasonForStatus($response->getStatusCode()),
                    $response->getStatusCode(),
                );
            }

            return $response;
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : ($exception instanceof ValidationException ? 422 : 500);
            $stage = $exception instanceof ValidationException ? 'request_validation' : 'middleware';
            $reason = match (true) {
                $status === 429 => 'rate_limited',
                $exception instanceof InvalidSignatureException => 'invalid_url_signature',
                $exception instanceof ValidationException => 'invalid_request',
                default => 'unexpected_error',
            };

            $this->logger->record($request, $flow, $stage, $reason, $status, $exception);

            throw $exception;
        }
    }

    private function reasonForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            419 => 'csrf_or_session_expired',
            422 => 'verification_failed',
            429 => 'rate_limited',
            default => $status >= IlluminateResponse::HTTP_INTERNAL_SERVER_ERROR
                ? 'server_error'
                : 'request_rejected',
        };
    }
}
