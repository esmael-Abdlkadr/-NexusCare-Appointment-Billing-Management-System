<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLoggerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()) {
            AuditLogger::write(
                $request->user()->id,
                (string) ($request->route()?->getName() ?? 'UNNAMED_ACTION'),
                null,
                null,
                AuditLogger::sanitizedPayload($request),
                $request->ip(),
            );
        }

        return $response;
    }
}
