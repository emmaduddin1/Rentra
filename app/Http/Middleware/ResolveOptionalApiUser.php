<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Public listings stay public; a supplied token uses the same account rules as protected APIs. */
class ResolveOptionalApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            return $next($request);
        }

        if (! Auth::guard('sanctum')->user()) {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        }

        Auth::shouldUse('sanctum');

        return app(EnsureUserIsActive::class)->handle($request, $next);
    }
}
