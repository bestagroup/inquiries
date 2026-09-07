<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'service_token',
        'headers_json',
        'input',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {
            // Request correlation metadata is injected into the logger by
            // RequestCorrelationId before application code executes.
        });
    }

    public function render($request, Throwable $e)
    {
        $response = parent::render($request, $e);

        if ($request instanceof Request) {
            $requestId = $request->attributes->get('request_id');
            if (is_string($requestId) && $requestId !== '') {
                $response->headers->set('X-Request-ID', $requestId);
            }

            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
            $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'");
            $response->headers->remove('X-Powered-By');
            header_remove('X-Powered-By');

            if ($request->user() !== null) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('Pragma', 'no-cache');
            }

            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }
}
