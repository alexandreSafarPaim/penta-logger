<?php

namespace PentaLogger\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isAuthEnabled()) {
            return $next($request);
        }

        if (!$this->isAuthenticated($request)) {
            if ($request->expectsJson() || $request->is('*/_penta-logger/stream')) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return redirect()->route('penta-logger.login');
        }

        return $next($request);
    }

    protected function isAuthEnabled(): bool
    {
        $user = config('penta-logger.auth.user');
        $password = config('penta-logger.auth.password');

        return !empty($user) && !empty($password);
    }

    protected function isAuthenticated(Request $request): bool
    {
        return $request->session()->get('penta-logger.authenticated') === true;
    }
}
