<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** FCM HTTP v1, with short-lived OAuth2 credentials signed using PHP OpenSSL. */
class FcmClient
{
    /** Short-lived bearer tokens stay in this worker's memory, never the database cache. */
    private static array $accessTokens = [];

    public function send(string $token, array $payload): array
    {
        $project = config('push.project_id');
        $base = app()->environment('testing') ? config('push.test_base_url', 'https://fcm.googleapis.com') : 'https://fcm.googleapis.com';
        $response = Http::withToken($this->accessToken())->acceptJson()->connectTimeout(5)->timeout(15)
            ->post($base.'/v1/projects/'.rawurlencode($project).'/messages:send', [
                'message' => ['token' => $token, 'data' => $payload,
                    'webpush' => ['headers' => ['TTL' => '3600', 'Urgency' => 'high']]],
            ]);
        if ($response->successful()) {
            return ['status' => 'sent', 'code' => null];
        }
        $details = $response->json('error.details') ?? [];
        $fcmCode = collect(is_array($details) ? $details : [])->firstWhere('@type', 'type.googleapis.com/google.firebase.fcm.v1.FcmError')['errorCode'] ?? null;
        // INVALID_ARGUMENT only revokes when FCM identifies it as a registration-token error.
        $invalid = $fcmCode === 'UNREGISTERED' || ($fcmCode === 'INVALID_ARGUMENT' && preg_match('/registration token/i', (string) $response->json('error.message')));

        return ['status' => $invalid ? 'revoked' : (($response->status() === 429 || $response->serverError()) ? 'retry' : 'failed'),
            'code' => $invalid ? $fcmCode : 'HTTP_'.$response->status()];
    }

    protected function accessToken(): string
    {
        $credentials = json_decode(file_get_contents(config('push.credentials')), true, 512, JSON_THROW_ON_ERROR);
        if (($credentials['project_id'] ?? null) !== config('push.project_id') || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Invalid Firebase service account configuration.');
        }
        $key = 'push.oauth.'.hash('sha256', $credentials['client_email'].$credentials['private_key']);

        if ((self::$accessTokens[$key]['expires_at'] ?? 0) > time()) {
            return self::$accessTokens[$key]['token'];
        }

        $token = (function () use ($credentials) {
            $encode = fn ($value) => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            $jwt = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
                'iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600,
            ]);
            if (! openssl_sign($jwt, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign Firebase assertion.');
            }
            $jwt .= '.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
            $endpoint = app()->environment('testing') ? config('push.test_oauth_url', 'https://oauth2.googleapis.com/token') : 'https://oauth2.googleapis.com/token';
            $response = Http::asForm()->connectTimeout(5)->timeout(15)->post($endpoint, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt,
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new RuntimeException('Firebase OAuth unavailable.');
            }

            return $response->json('access_token');
        })();
        // Keep only the current credential's access token when credentials rotate.
        self::$accessTokens = [$key => ['token' => $token, 'expires_at' => time() + 3000]];

        return $token;
    }
}
