<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\Push\NotificationPayload;
use App\Services\Push\PushConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, PushConfiguration $configuration): JsonResponse
    {
        abort_unless($configuration->available(), 503, 'Web push temporarily unavailable.');
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/^[A-Za-z0-9_:\-]+$/'],
            'installation_id' => ['required', 'uuid'],
            'browser' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'notifications_enabled' => ['required', 'boolean'],
        ]);
        $hash = hash('sha256', $data['token']);
        $environment = config('push.environment');
        $subscription = DB::transaction(function () use ($request, $data, $hash, $environment) {
            // One stable row per installation, one token per environment. Token rotation never
            // leaves the old token active. Concurrent conflicts are safely retried by the client.
            $installation = PushSubscription::where('environment', $environment)->where('installation_id', $data['installation_id'])->lockForUpdate()->first();
            $token = PushSubscription::where('environment', $environment)->where('token_hash', $hash)->lockForUpdate()->first();
            if ($installation && $token && $installation->id !== $token->id) {
                $installation->delete();
                $installation = null;
            }
            $subscription = $token ?? $installation ?? new PushSubscription;
            $changedRegistration = $subscription->exists && ($subscription->user_id !== $request->user()->id || $subscription->token_hash !== $hash);
            $subscription->fill([...$data, 'token_hash' => $hash, 'user_id' => $request->user()->id,
                'environment' => $environment, 'platform' => 'web', 'last_used_at' => now(), 'revoked_at' => null,
                'failure_count' => 0]);
            if ($changedRegistration) {
                $subscription->last_success_at = null;
                $subscription->last_failure_at = null;
            }
            $subscription->save();

            return $subscription;
        }, 3);
        $request->session()->put('push_installation_id', $data['installation_id']);

        return response()->json(['enabled' => $subscription->notifications_enabled, 'subscription_key' => $subscription->bindingKey()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['installation_id' => ['required', 'uuid']]);
        PushSubscription::where('user_id', $request->user()->id)->where('environment', config('push.environment'))
            ->where('installation_id', $data['installation_id'])->update(['revoked_at' => now(), 'notifications_enabled' => false]);

        return response()->json(['revoked' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $data = $request->validate(array_fill_keys(NotificationPayload::CATEGORIES, ['sometimes', 'boolean']));
        DB::table('web_notification_preferences')->updateOrInsert(['user_id' => $request->user()->id], [...$data, 'updated_at' => now()]);

        return response()->json(['saved' => true]);
    }
}
