<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\PushSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * SRS FR-AUTH-002 / FR-AUTH-004 - a valid login creates a session and the
     * user lands on the portal for their role.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route($request->user()->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        PushSubscription::where('user_id', $request->user()->id)
            ->where('environment', config('push.environment'))
            ->where('installation_id', $request->session()->get('push_installation_id', ''))
            ->update(['revoked_at' => now(), 'notifications_enabled' => false]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
