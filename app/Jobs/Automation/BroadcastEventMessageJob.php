<?php

declare(strict_types=1);

namespace App\Jobs\Automation;

use App\Mail\EventBroadcastMail;
use App\Models\Order;
use App\Services\Sms\Contracts\SmsProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a broadcast message to every paid-order recipient for an
 * event. Chunked so even very large events don't OOM the worker.
 *
 * Channel is either `email` (Mail::send) or `sms` (resolved
 * SmsProvider). The provider binding is config-driven — NullSms in
 * dev, Twilio etc. in production.
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

    public function handle(SmsProvider $sms): void
    {
        Order::query()
            ->where('event_id', $this->eventId)
            ->where('status', 'paid')
            ->select(['id', 'buyer_email', 'buyer_name', 'buyer_phone'])
            ->orderBy('id')
            ->chunk(200, function ($orders) use ($sms) {
                foreach ($orders as $order) {
                    $this->deliver(
                        sms: $sms,
                        email: (string) $order->buyer_email,
                        phone: (string) ($order->buyer_phone ?? ''),
                    );
                }
            });
    }

    protected function deliver(SmsProvider $sms, string $email, string $phone): void
    {
        if ($this->channel === 'sms') {
            if ($phone !== '') {
                $sms->send($phone, $this->subject."\n\n".$this->body);
            }

            return;
        }

        if ($email === '') {
            return;
        }

        Mail::to($email)->send(new EventBroadcastMail($this->subject, $this->body));
    }
}
