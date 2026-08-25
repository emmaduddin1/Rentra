<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SRS FR-AUTH-006 - a suspended account cannot authenticate or perform
 * protected actions, including on a session created before the suspension.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        // API clients hold a token rather than a session, so the token is the
        // thing that has to stop working (SRS 9.3, NFR-MNT-004).
        if ($request->is('api/*') || $request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => __('This account has been suspended.'),
            ], 403);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withErrors(['identifier' => __('This account has been suspended.')]);
    }
}
