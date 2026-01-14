<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /**
     * Routes to exclude from logging.
     *
     * @var array<string>
     */
    protected array $excludedRoutes = [
        'up',
        'health',
        'horizon/*',
        'telescope/*',
    ];

    /**
     * Headers to exclude from logging.
     *
     * @var array<string>
     */
    protected array $excludedHeaders = [
        'authorization',
        'cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /**
     * Request fields to mask in logs.
     *
     * @var array<string>
     */
    protected array $maskedFields = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'secret',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $requestId = (string) Str::uuid();
        $startTime = microtime(true);

        // Log the incoming request
        $this->logRequest($request, $requestId);

        $response = $next($request);

        // Log the response
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $this->logResponse($request, $response, $requestId, $duration);

        return $response;
    }

    /**
     * Determine if logging should be skipped for this request.
     */
    protected function shouldSkip(Request $request): bool
    {
        if (!config('logging.log_requests', false)) {
            return true;
        }

        foreach ($this->excludedRoutes as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log the incoming request.
     */
    protected function logRequest(Request $request, string $requestId): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ];

        if (config('logging.log_request_headers', false)) {
            $context['headers'] = $this->getFilteredHeaders($request);
        }

        if (config('logging.log_request_body', false) && !$request->isMethod('GET')) {
            $context['body'] = $this->maskSensitiveData($request->all());
        }

        Log::channel('requests')->info('Incoming request', $context);
    }

    /**
     * Log the response.
     */
    protected function logResponse(Request $request, Response $response, string $requestId, float $duration): void
    {
        $context = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $request->user()?->id,
        ];

        $level = $this->getLogLevel($response->getStatusCode());

        Log::channel('requests')->{$level}('Response sent', $context);
    }

    /**
     * Get the log level based on status code.
     */
    protected function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }

    /**
     * Get headers with sensitive ones filtered out.
     *
     * @return array<string, mixed>
     */
    protected function getFilteredHeaders(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $key => $value) {
            if (!in_array(strtolower($key), $this->excludedHeaders)) {
                $headers[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }

        return $headers;
    }

    /**
     * Mask sensitive data in the request body.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            } elseif (in_array(strtolower($key), $this->maskedFields)) {
                $data[$key] = '***MASKED***';
            }
        }

        return $data;
    }
}
