<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Event;
use App\Models\EventTemplate;
use App\Models\TicketCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clones an Event row per the template's cadence. Copies the Event
 * + its TicketCategory tiers + currency prices; resets inventory
 * counters; drops every relation that doesn't belong to a fresh
 * instance (page views, scans, orders).
 *
 * Cadences supported:
 *   daily     once a day, same time as source.starts_at
 *   weekly    once on the same weekday
 *   monthly   once on the same day-of-month
 *   nth_weekday_of_month  e.g. "third Tuesday" — meta carries
 *                          {nth:3, weekday:2}
 */
class RecurringEventGenerator
{
    /** @return list<Event> the newly created instances */
    public function generateDue(?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        $created = [];

        EventTemplate::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now))
            ->orderBy('id')
            ->chunkById(50, function ($templates) use ($now, &$created) {
                foreach ($templates as $template) {
                    if ($this->shouldStop($template)) {
                        $template->forceFill(['is_active' => false])->save();

                        continue;
                    }
                    $next = $this->nextInstanceTime($template, $now);
                    $event = $this->cloneEvent($template, $next);
                    if ($event) {
                        $created[] = $event;
                        $template->increment('instances_created');
                        $template->forceFill([
                            'last_generated_at' => $now,
                            'next_run_at' => $this->advance($template, $next),
                        ])->save();
                    }
                }
            });

        return $created;
    }

    protected function shouldStop(EventTemplate $template): bool
    {
        if ($template->repeat_until && $template->repeat_until->isPast()) {
            return true;
        }
        if ($template->max_instances !== null && $template->instances_created >= $template->max_instances) {
            return true;
        }

        return false;
    }

    protected function nextInstanceTime(EventTemplate $template, Carbon $now): Carbon
    {
        $source = $template->sourceEvent;
        $base = $template->next_run_at ?? $source?->starts_at ?? $now;

        return $base->copy()->setSecond(0);
    }

    protected function advance(EventTemplate $template, Carbon $current): Carbon
    {
        return match ($template->cadence) {
            EventTemplate::CADENCE_DAILY => $current->copy()->addDay(),
            EventTemplate::CADENCE_WEEKLY => $current->copy()->addWeek(),
            EventTemplate::CADENCE_MONTHLY => $current->copy()->addMonthNoOverflow(),
            EventTemplate::CADENCE_NTH_WEEKDAY => $this->advanceNthWeekday($current, (array) $template->cadence_meta),
            default => $current->copy()->addWeek(),
        };
    }

    /** @param array<string, mixed> $meta */
    protected function advanceNthWeekday(Carbon $current, array $meta): Carbon
    {
        $nth = max(1, (int) ($meta['nth'] ?? 1));
        $weekday = (int) ($meta['weekday'] ?? $current->dayOfWeek);
        $target = $current->copy()->addMonthNoOverflow()->startOfMonth();

        // Walk forward to the first occurrence of $weekday in the new
        // month, then jump (nth - 1) weeks.
        while ($target->dayOfWeek !== $weekday) {
            $target->addDay();
        }

        return $target->addWeeks($nth - 1)
            ->setTime($current->hour, $current->minute);
    }

    protected function cloneEvent(EventTemplate $template, Carbon $startsAt): ?Event
    {
        $source = $template->sourceEvent()->with('ticketCategories.currencyPrices')->first();
        if (! $source) {
            return null;
        }

        return DB::transaction(function () use ($source, $startsAt) {
            $duration = $source->ends_at?->diffInMinutes($source->starts_at) ?? 120;

            $new = $source->replicate([
                'tickets_sold_count', 'views_count', 'published_at',
            ]);
            $new->event_id = (string) Str::uuid();
            $new->slug = Str::slug($source->name).'-'.$startsAt->format('Y-m-d');
            $new->status = $source->status;
            $new->starts_at = $startsAt;
            $new->ends_at = $startsAt->copy()->addMinutes($duration);
            $new->tickets_sold_count = 0;
            $new->views_count = 0;
            $new->save();

            foreach ($source->ticketCategories as $tier) {
                $newTier = $tier->replicate([
                    'scanned_count', 'generation_progress', 'generation_status',
                ]);
                $newTier->event_id = $new->id;
                $newTier->uuid = (string) Str::uuid();
                $newTier->save();

                foreach ($tier->currencyPrices as $price) {
                    $newPrice = $price->replicate();
                    $newPrice->ticket_category_id = $newTier->id;
                    $newPrice->save();
                }
            }

            return $new;
        });
    }
}
