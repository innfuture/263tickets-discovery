<?php

declare(strict_types=1);

namespace App\Services\Storefront\Privacy;

use App\Models\CheckoutSession;
use App\Models\Order;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\DB;

/**
 * GDPR / "Right to be Forgotten" — anonymises (not deletes) records.
 *
 * Why anonymise vs hard-delete:
 *   - Tax / accounting law in most jurisdictions requires retaining
 *     order amounts + dates for a number of years.
 *   - Inventory accounting needs to keep "this seat was sold at this
 *     time" even if the buyer's name is scrubbed.
 *
 * We replace PII columns with deterministic hashes so the same email
 * being erased twice yields the same anonymised value (helpful for
 * audit / debugging that an erasure happened) but the original email
 * can't be reconstructed.
 */
class BuyerDataEraser
{
    /** @return array{orders_anonymised: int, sessions_anonymised: int, waitlist_anonymised: int} */
    public function erase(string $email): array
    {
        $email = strtolower(trim($email));
        $hash = 'erased-'.substr(hash('sha256', $email), 0, 16).'@erased.invalid';

        return DB::transaction(function () use ($email, $hash) {
            $orders = Order::query()->where('buyer_email', $email)->get();
            foreach ($orders as $order) {
                $order->forceFill([
                    'buyer_email' => $hash,
                    'buyer_name' => 'Anonymised',
                    'buyer_phone' => null,
                ])->save();

                $order->items()->update([
                    'attendee_email' => $hash,
                    'attendee_name' => 'Anonymised',
                    'attendee_phone' => null,
                ]);
            }

            $sessions = CheckoutSession::query()->where('buyer_email', $email)->update([
                'buyer_email' => $hash,
                'buyer_name' => 'Anonymised',
                'buyer_phone' => null,
                'attendee_data' => null,
            ]);

            $waitlist = WaitlistEntry::query()->where('email', $email)->update([
                'email' => $hash,
                'name' => 'Anonymised',
                'phone' => null,
            ]);

            return [
                'orders_anonymised' => $orders->count(),
                'sessions_anonymised' => (int) $sessions,
                'waitlist_anonymised' => (int) $waitlist,
            ];
        });
    }
}
