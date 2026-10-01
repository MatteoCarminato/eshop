<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica requisições da API pública consumida pelo frontend Orbita via
 * header `X-Orbita-Token`, comparado contra `services.orbita.token`.
 */
class EnsureOrbitaToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.orbita.token');
        $provided = $request->header('X-Orbita-Token');

        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        return $next($request);
    }
}
