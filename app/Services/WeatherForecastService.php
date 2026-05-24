<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeatherForecastService
{
    private const TTL_SECONDS = 3600;

    private const TIMEOUT_SECONDS = 5;

    /**
     * @return array<int, array{date: string, code: int, max_c: float, min_c: float, precip_pct: int}>|null
     */
    public function fetch(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        string $timezone,
    ): ?array {
        $key = sprintf(
            'weather:%.4f:%.4f:%s:%s',
            $latitude,
            $longitude,
            $startDate,
            $endDate,
        );

        return Cache::remember(
            $key,
            self::TTL_SECONDS,
            fn () => $this->callApi($latitude, $longitude, $startDate, $endDate, $timezone),
        );
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function callApi(
        float $latitude,
        float $longitude,
        string $startDate,
        string $endDate,
        string $timezone,
    ): ?array {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                    'timezone' => $timezone,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! is_array($data) || ! isset($data['daily']['time']) || ! is_array($data['daily']['time'])) {
                return null;
            }

            $days = $data['daily']['time'];
            $forecasts = [];

            foreach ($days as $i => $date) {
                $forecasts[] = [
                    'date' => $date,
                    'code' => (int) ($data['daily']['weather_code'][$i] ?? 0),
                    'max_c' => (float) ($data['daily']['temperature_2m_max'][$i] ?? 0),
                    'min_c' => (float) ($data['daily']['temperature_2m_min'][$i] ?? 0),
                    'precip_pct' => (int) ($data['daily']['precipitation_probability_max'][$i] ?? 0),
                ];
            }

            return $forecasts;
        } catch (Throwable $e) {
            Log::warning('Weather forecast fetch failed: '.$e->getMessage());

            return null;
        }
    }
}
