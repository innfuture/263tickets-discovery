<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Models\SandboxClockRow;
use App\Models\SandboxMerchant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Per-merchant virtual clock (§15 #3). Wall time is the truth in `real`
 * mode; in `virtual` mode we keep an `offset_seconds` accumulator so
 * `now()` = wall + offset.
 *
 * Tests `advance()` to fast-forward — webhook timers, auth-expiry
 * windows, and ACH settlement all read through this clock and become
 * synchronous after a sufficiently large advance.
 *
 * Concurrency: every mutation takes a row-level lock so parallel tests
 * cannot race (§10).
 */
class SandboxClock
{
    /**
     * @var array<int, SandboxClockRow> in-process cache keyed by merchant id
     */
    protected array $rows = [];

    public function now(SandboxMerchant $merchant): CarbonImmutable
    {
        $row = $this->rowFor($merchant);
        $wall = CarbonImmutable::now();

        if ($row->mode === 'real') {
            return $wall;
        }

        return $wall->addSeconds($row->offset_seconds);
    }

    public function advance(SandboxMerchant $merchant, int $seconds): CarbonImmutable
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Sandbox clock cannot move backwards.');
        }

        DB::transaction(function () use ($merchant, $seconds) {
            $row = SandboxClockRow::query()
                ->where('sandbox_merchant_id', $merchant->id)
                ->lockForUpdate()
                ->first() ?? $this->seedRow($merchant);

            $row->mode = 'virtual';
            $row->frozen_at = $row->frozen_at ?? now();
            $row->offset_seconds = (int) $row->offset_seconds + $seconds;
            $row->save();

            $this->rows[$merchant->id] = $row;
        });

        return $this->now($merchant);
    }

    /**
     * Drop the clock back to wall time. Useful between tests.
     */
    public function reset(SandboxMerchant $merchant): void
    {
        $row = $this->rowFor($merchant);
        $row->mode = 'real';
        $row->offset_seconds = 0;
        $row->frozen_at = null;
        $row->save();
    }

    protected function rowFor(SandboxMerchant $merchant): SandboxClockRow
    {
        if (isset($this->rows[$merchant->id])) {
            return $this->rows[$merchant->id];
        }

        $row = SandboxClockRow::firstOrCreate(
            ['sandbox_merchant_id' => $merchant->id],
            ['mode' => 'real', 'offset_seconds' => 0],
        );

        return $this->rows[$merchant->id] = $row;
    }

    protected function seedRow(SandboxMerchant $merchant): SandboxClockRow
    {
        return SandboxClockRow::create([
            'sandbox_merchant_id' => $merchant->id,
            'mode' => 'real',
            'offset_seconds' => 0,
        ]);
    }
}
