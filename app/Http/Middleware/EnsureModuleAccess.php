<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Garante que o usuário autenticado tenha acesso ao(s) módulo(s) informados.
     *
     * Uso nas rotas:
     *   ->middleware('module:wallet.view')
     *   ->middleware('module:wallet.view,wallet.manage')   (qualquer um basta)
     */
    public function handle(Request $request, Closure $next, string ...$moduleKeys): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Não autenticado.'], 401);
            }
            return redirect()->guest(route('login'));
        }

        if (empty($moduleKeys)) {
            return $next($request);
        }

        // Admin total bypassa qualquer checagem.
        if ($user->isAdmin()) {
            return $next($request);
        }

        $allowed = collect($moduleKeys)->contains(fn (string $key) => $user->hasModule($key));

        if (!$allowed) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Você não tem permissão para acessar este módulo.',
                    'modules' => $moduleKeys,
                ], 403);
            }

            abort(403, 'Você não tem permissão para acessar este módulo.');
        }

        return $next($request);
    }
}
