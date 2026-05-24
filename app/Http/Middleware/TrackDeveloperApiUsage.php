<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperApiUsage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daily-rollup writer. Runs AFTER the route is handled so we can
 * record success / error / rate-limited counts. Uses `upsert` so
 * concurrent requests don't fight for the same (key, day) row.
 */
class TrackDeveloperApiUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $key = $request->attributes->get('developer_api_key');
        if (! $key) {
            return $response;
        }

        $status = $response->getStatusCode();
        $today = Carbon::today()->toDateString();

        DeveloperApiUsage::query()->upsert([
            [
                'developer_api_key_id' => $key->id,
                'date' => $today,
                'request_count' => 1,
                'success_count' => $status >= 200 && $status < 400 ? 1 : 0,
                'error_count' => $status >= 400 && $status !== 429 ? 1 : 0,
                'rate_limited_count' => $status === 429 ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['developer_api_key_id', 'date'], [
            'request_count' => \DB::raw('request_count + 1'),
            'success_count' => \DB::raw('success_count + '.(int) ($status >= 200 && $status < 400)),
            'error_count' => \DB::raw('error_count + '.(int) ($status >= 400 && $status !== 429)),
            'rate_limited_count' => \DB::raw('rate_limited_count + '.(int) ($status === 429)),
            'updated_at' => now(),
        ]);

        return $response;
    }
}
