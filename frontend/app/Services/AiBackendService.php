<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin, fail-soft client for the Flask AI backend.
 *
 * Every method returns a sane fallback rather than throwing, because the
 * Laravel UI must stay usable when the Python service is not running - which
 * is the normal state while someone is only browsing tutorials.
 */
class AiBackendService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('citra.ai.url', 'http://localhost:5000'), '/');
        $this->timeout = (int) config('citra.ai.timeout', 8);
    }

    public function url(string $path = ''): string
    {
        return $this->baseUrl.'/'.ltrim($path, '/');
    }

    public function wsUrl(): string
    {
        return rtrim(config('citra.ai.ws_url', $this->baseUrl), '/');
    }

    /**
     * Is the AI backend reachable?
     *
     * Cached for 15 seconds so a page with several partials does not issue a
     * probe per partial, and so a dead backend does not add its timeout to
     * every request.
     */
    public function isOnline(bool $fresh = false): bool
    {
        if ($fresh) {
            Cache::forget('citra.ai.online');
        }

        return Cache::remember('citra.ai.online', 15, function () {
            return $this->health() !== null;
        });
    }

    public function health(): ?array
    {
        try {
            $response = Http::timeout(min($this->timeout, 4))
                ->acceptJson()
                ->get($this->url('/api/health'));

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::debug('CITRA AI backend unreachable: '.$e->getMessage());
            return null;
        }
    }

    /** Status of the trained deep-learning models, or null when offline. */
    public function deepModelStatus(): ?array
    {
        $health = $this->health();
        return $health['deep_models'] ?? null;
    }

    public function get(string $path, array $query = [], ?string $token = null): ?array
    {
        return $this->request('get', $path, $query, $token);
    }

    public function post(string $path, array $payload = [], ?string $token = null): ?array
    {
        return $this->request('post', $path, $payload, $token);
    }

    private function request(string $method, string $path, array $data, ?string $token): ?array
    {
        try {
            $request = Http::timeout($this->timeout)->acceptJson();

            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->{$method}($this->url($path), $data);

            if (!$response->successful()) {
                Log::warning('CITRA AI backend '.$response->status().' on '.$path, [
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning("CITRA AI backend {$method} {$path} failed: ".$e->getMessage());
            return null;
        }
    }

    /**
     * Mint a JWT the Flask service will accept for this user.
     *
     * Both halves share one database and one JWT secret, so a user already
     * authenticated by Laravel's session should not have to log in again to
     * reach the AI service. The claim set mirrors what flask-jwt-extended
     * issues itself: string `sub`, `type: access`, plus the standard times.
     */
    public function issueToken(?Authenticatable $user = null): ?string
    {
        $user ??= Auth::user();
        if (!$user) {
            return null;
        }

        $secret = (string) config('citra.ai.jwt_secret');
        if ($secret === '') {
            return null;
        }

        $now = time();
        $ttl = max(60, (int) config('citra.ai.jwt_ttl', 7200));

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'sub'   => (string) $user->getAuthIdentifier(),
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + $ttl,
            'jti'   => (string) Str::uuid(),
            'type'  => 'access',
            'fresh' => false,
            'name'  => $user->name,
            'email' => $user->email,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signing = implode('.', $segments);
        $segments[] = $this->base64UrlEncode(hash_hmac('sha256', $signing, $secret, true));

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Config blob handed to the browser so practice.blade.php never has to
     * hard-code the backend URL (it used to hard-code http://localhost:5000).
     */
    public function clientConfig(): array
    {
        return [
            'wsUrl'      => $this->wsUrl(),
            'apiUrl'     => $this->baseUrl,
            'token'      => $this->issueToken(),
            'targetFps'  => (int) config('citra.practice.target_fps', 12),
            'minSeconds' => (int) config('citra.practice.min_session_seconds', 10),
            'online'     => $this->isOnline(),
        ];
    }
}
