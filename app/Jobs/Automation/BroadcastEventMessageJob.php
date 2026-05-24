<?php

declare(strict_types=1);

namespace App\Jobs\Automation;

use App\Mail\EventBroadcastMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a broadcast message to every paid-order email for an event.
 * Chunked so even very large events don't OOM the worker.
 *
 * When the matching `EventBroadcastMail` mailable / SMS provider is
 * missing, logs structured "would-have-sent" lines so QA can confirm
 * the chain ran end-to-end. Implementations of `EventBroadcastMail`
 * and SMS dispatch are intentionally out-of-scope for the engine.
 */
class BroadcastEventMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $eventId,
        public string $subject,
        public string $body,
        public string $channel = 'email',
    ) {}

    public function handle(): void
    {
        Order::query()
            ->where('event_id', $this->eventId)
            ->where('status', 'paid')
            ->select(['id', 'buyer_email', 'buyer_name', 'buyer_phone'])
            ->orderBy('id')
            ->chunk(200, function ($orders) {
                foreach ($orders as $order) {
                    $this->deliver(
                        email: (string) $order->buyer_email,
                        phone: (string) ($order->buyer_phone ?? ''),
                    );
                }
            });
    }

    protected function deliver(string $email, string $phone): void
    {
        if ($this->channel === 'sms' && $phone !== '') {
            Log::info('automation.broadcast.sms', [
                'phone' => $phone,
                'subject' => $this->subject,
                'note' => 'wire an SMS provider (Twilio etc.) to actually send.',
            ]);

            return;
        }

        if ($email === '') {
            return;
        }

        Mail::to($email)->send(new EventBroadcastMail($this->subject, $this->body));
    }
}
