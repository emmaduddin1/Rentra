<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUser;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'roles' => $this->selectableRoles(),
        ]);
    }

    public function store(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle($request->validated());

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route($user->homeRoute())
            ->with('status', __('Welcome, :name.', ['name' => $user->name]));
    }

    /**
     * SRS 19 - self-registration is limited to the configured roles.
     *
     * @return array<int, UserRole>
     */
    protected function selectableRoles(): array
    {
        return array_map(
            fn (string $role) => UserRole::from($role),
            config('rental.registration.allowed_roles'),
        );
    }
}
