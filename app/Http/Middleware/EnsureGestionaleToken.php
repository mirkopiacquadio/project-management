<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protegge le route API consumate dal gestionale esterno (omnianextsrl).
 * Confronta l'header "Authorization: Bearer <token>" con services.gestionale.token.
 * Fail-closed: se il token non è configurato sul server, nega sempre.
 */
class EnsureGestionaleToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.gestionale.token');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
