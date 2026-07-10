<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuerza que las peticiones a la API se traten como JSON, aunque el cliente
 * no mande el header Accept. Así los errores (401, 422, etc.) se devuelven
 * como JSON y no como redirects/HTML pensados para navegador.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
