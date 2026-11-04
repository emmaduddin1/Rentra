<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Services\Push\PushConfiguration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/** Native registrations share the existing web subscription store and delivery outbox. */
class PushDeviceController extends Controller
{
    public function config(PushConfiguration $configuration): JsonResponse
    {
        return response()->json(['data' => [
            'enabled' => $configuration->mobileAvailable(),
            'environment' => config('push.environment'),
            'firebase_project_id' => config('push.project_id'),
        ]]);
    }

    public function store(Request $request, PushConfiguration $configuration): JsonResponse
    {
        abort_unless($configuration->mobileAvailable(), 503, 'Mobile push temporarily unavailable.');
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:\-]+$/'],
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'firebase_project_id' => ['required', Rule::in([config('push.project_id')])],
        ]);
        $accessToken = $request->user()->currentAccessToken();
        abort_unless($accessToken instanceof PersonalAccessToken, 401, 'A personal API session is required.');
        $hash = hash('sha256', $data['token']);
        try {
            $subscription = DB::transaction(function () use ($request, $data, $hash, $accessToken) {
                $query = fn () => PushSubscription::where('environment', config('push.environment'));
                $installation = $query()->where('installation_id', $data['installation_id'])->lockForUpdate()->first();
                $token = $query()->where('token_hash', $hash)->lockForUpdate()->first();
                abort_if(($installation && $installation->platform === 'web') || ($token && $token->platform === 'web'),
                    409, 'Use a fresh native registration token.');
                abort_if($installation && $token && $installation->id !== $token->id,
                    409, 'This registration changed. Refresh the device token and retry.');
                abort_if($token && PushDelivery::where('push_subscription_id', $token->id)
                    ->where('token_hash', $hash)->where('status', 'revoked')
                    ->whereIn('error_code', ['UNREGISTERED', 'INVALID_ARGUMENT'])->exists(),
                    409, 'Use a fresh native registration token.');
                $subscription = $installation ?? $token ?? new PushSubscription;
                $subscription->fill([
                    'user_id' => $request->user()->id, 'personal_access_token_id' => $accessToken->getKey(),
                    'token' => $data['token'], 'token_hash' => $hash,
                    'installation_id' => $data['installation_id'], 'platform' => $data['platform'],
                    'environment' => config('push.environment'), 'notifications_enabled' => true,
                    'last_used_at' => now(), 'revoked_at' => null, 'failure_count' => 0,
                    'last_failure_at' => null, 'last_success_at' => null,
                ])->save();

                return $subscription;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This registration changed. Refresh the device token and retry.');
        }

        return response()->json(['data' => [
            'enabled' => true, 'subscription_key' => $subscription->bindingKey(),
            'environment' => config('push.environment'),
        ]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'uuid']]);
        PushSubscription::where('user_id', $request->user()->id)
            ->where('environment', config('push.environment'))->whereIn('platform', ['android', 'ios'])
            ->where('installation_id', $data['installation_id'])
            ->update(['revoked_at' => now(), 'notifications_enabled' => false]);

        return response()->json(['data' => ['revoked' => true]]);
    }
}
