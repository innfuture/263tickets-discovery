<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;

/**
 * Upserts a row in `user_sessions` for the current PHP session ID and
 * authenticated user, so the /settings/sessions page has data to
 * render. Cheap — one indexed UPSERT per authenticated request.
 *
 * Device labels are coarse parses of the UA string; precise device
 * naming would warrant a dedicated UA parsing library.
 */
class TrackUserSession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        $user = $request->user();
        if ($user && $request->hasSession()) {
            UserSession::query()->updateOrInsert(
                ['id' => $request->session()->getId()],
                [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 512) ?: null,
                    'device_label' => $this->deviceLabel((string) $request->userAgent()),
                    'started_at' => UserSession::query()
                        ->where('id', $request->session()->getId())
                        ->value('started_at') ?? now(),
                    'last_active_at' => now(),
                ],
            );
        }

        return $response;
    }

    private function deviceLabel(string $ua): string
    {
        $platform = match (true) {
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Linux') => 'Linux',
            str_contains($ua, 'iPhone') => 'iOS (iPhone)',
            str_contains($ua, 'iPad') => 'iOS (iPad)',
            str_contains($ua, 'Android') => 'Android',
            default => 'Unknown OS',
        };
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        return "{$browser} on {$platform}";
    }
}
