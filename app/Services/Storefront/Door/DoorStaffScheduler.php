<?php

declare(strict_types=1);

namespace App\Services\Storefront\Door;

use App\Models\DoorStaffShift;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Schedules door-staff shifts on an event and gates the scanner-app
 * door-PIN sign-in by active-shift presence.
 *
 *   schedule()         create a shift
 *   currentShifts()    shifts active right now (used by EnsureDoorPin)
 *   markActive()       called when staff sign in within the window
 *   close()            mark completed + record final scan count
 *   gateSignIn()       returns true if at least one shift covers `now`
 *
 * Overlapping shifts on the same door are allowed by design (multiple
 * staff per gate). Conflict detection happens at the per-user level —
 * one human can't be at two doors simultaneously.
 */
class DoorStaffScheduler
{
    public function schedule(
        Event $event,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?User $user = null,
        ?string $staffName = null,
        ?string $doorLabel = null,
        ?int $expectedScans = null,
    ): DoorStaffShift {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new RuntimeException('Shift end must be after start.');
        }

        return DB::transaction(function () use ($event, $startsAt, $endsAt, $user, $staffName, $doorLabel, $expectedScans): DoorStaffShift {
            if ($user !== null) {
                $clash = DoorStaffShift::query()
                    ->where('user_id', $user->id)
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->whereIn('status', [DoorStaffShift::STATUS_SCHEDULED, DoorStaffShift::STATUS_ACTIVE])
                    ->exists();
                if ($clash) {
                    throw new RuntimeException("User {$user->id} already has a shift overlapping this window.");
                }
            }

            return DoorStaffShift::create([
                'event_id' => $event->id,
                'user_id' => $user?->id,
                'staff_name' => $staffName ?? $user?->name,
                'door_label' => $doorLabel,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => DoorStaffShift::STATUS_SCHEDULED,
                'expected_scan_count' => $expectedScans,
            ]);
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DoorStaffShift>
     */
    public function currentShifts(Event $event, ?CarbonImmutable $at = null)
    {
        $at ??= CarbonImmutable::now();

        return DoorStaffShift::query()
            ->where('event_id', $event->id)
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->whereIn('status', [DoorStaffShift::STATUS_SCHEDULED, DoorStaffShift::STATUS_ACTIVE])
            ->get();
    }

    /**
     * True iff at least one staff shift covers `now` for this event.
     * Door-PIN sign-in middleware calls this to gate access — staff
     * can only sign in during a scheduled window, drastically limiting
     * blast radius if a PIN leaks.
     */
    public function gateSignIn(Event $event, ?CarbonImmutable $at = null): bool
    {
        return $this->currentShifts($event, $at)->isNotEmpty();
    }

    public function markActive(DoorStaffShift $shift): DoorStaffShift
    {
        if ($shift->status === DoorStaffShift::STATUS_SCHEDULED) {
            $shift->forceFill(['status' => DoorStaffShift::STATUS_ACTIVE])->save();
        }

        return $shift->fresh();
    }

    public function close(DoorStaffShift $shift, ?int $actualScanCount = null, ?string $notes = null): DoorStaffShift
    {
        $payload = ['status' => DoorStaffShift::STATUS_COMPLETED];
        if ($actualScanCount !== null) {
            $payload['actual_scan_count'] = $actualScanCount;
        }
        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        $shift->forceFill($payload)->save();

        return $shift->fresh();
    }
}
