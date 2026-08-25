<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SRS 9.3 - POST /api/v1/login, /logout, /password/forgot.
 *
 * Token authentication for the Flutter clients (Sanctum personal access
 * tokens). NFR-SEC-006 - login is rate limited by identifier and IP, exactly
 * like the web form.
 */
class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::transliterate(Str::lower($validated['identifier']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.throttle', [
                    'seconds' => $seconds = RateLimiter::availableIn($key),
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        // FR-AUTH-001/002 - either registered identifier works.
        $field = filter_var($validated['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $user = User::where($field, $validated['identifier'])->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
        }

        // FR-AUTH-006 - a suspended account never receives a token.
        if (! $user->isActive()) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'identifier' => __('This account has been suspended.'),
            ]);
        }

        RateLimiter::clear($key);

        // The abilities are the role, so a token can never outrank its owner.
        $token = $user->createToken($validated['device_name'], [$user->role->value]);

        $this->auditLogger->record(action: 'api.token_issued', subject: $user, actor: $user);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('Logged out.')]);
    }

    /**
     * FR-AUTH-003 - the reset link itself is delivered by email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
