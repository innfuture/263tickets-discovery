<?php

declare(strict_types=1);

namespace App\Services\Buyers;

use App\Mail\BuyerMagicLinkMail;
use App\Models\Buyer;
use App\Models\BuyerLoginToken;
use App\Models\BuyerSession;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Magic-link authentication for Buyer accounts.
 *
 *   1. requestLink($email) — mint a short-lived token, mail it.
 *   2. verifyToken($token) — exchange the magic-link token for a
 *      BuyerSession + bearer; the buyer is now authenticated.
 *   3. resolveSession($bearer) — middleware lookup.
 *
 * Why magic-link over password:
 *   - Most buyers transact once a year; remembering a password is
 *     hostile UX.
 *   - No password storage = no password breach.
 *
 * Tokens are 48-char URL-safe random + hashed at rest. The plaintext
 * is only in transit (the email) and as a request argument.
 *
 * On first verify, we link any existing Orders that match the email
 * (typical case: guest checkout, later signs up).
 */
class BuyerAuthService
{
    public const LINK_TTL_MINUTES = 15;

    public const SESSION_TTL_DAYS = 30;

    public function requestLink(string $email, ?string $ip = null): BuyerLoginToken
    {
        $email = strtolower(trim($email));
        $plaintext = Str::random(48);

        $token = BuyerLoginToken::create([
            'buyer_id' => Buyer::query()->where('email', $email)->value('id'),
            'email' => $email,
            'token_hash' => hash('sha256', $plaintext),
            'ip_address' => $ip,
            'expires_at' => Carbon::now()->addMinutes(self::LINK_TTL_MINUTES),
        ]);

        // Stash the plaintext on the model in-memory so the caller
        // (controller) can hand it off to the mailable. Not persisted.
        $token->setAttribute('plaintext_for_email', $plaintext);

        $this->dispatchMagicLink($email, $plaintext);

        return $token;
    }

    /**
     * Exchange a magic-link token for a Buyer + BuyerSession + plaintext
     * bearer. Returns ['buyer', 'session', 'bearer'].
     */
    public function verifyToken(string $plaintext, ?string $ip = null, ?string $userAgent = null): array
    {
        $hash = hash('sha256', $plaintext);
        $token = BuyerLoginToken::query()->where('token_hash', $hash)->first();
        if (! $token || $token->consumed_at !== null) {
            throw new RuntimeException('Invalid or already-used login link.');
        }
        if ($token->expires_at->isPast()) {
            throw new RuntimeException('Login link has expired. Request a fresh one.');
        }

        return DB::transaction(function () use ($token, $ip, $userAgent) {
            $token->forceFill(['consumed_at' => Carbon::now()])->save();

            $buyer = Buyer::query()->firstOrCreate(
                ['email' => $token->email],
                ['email_verified_at' => Carbon::now()],
            );

            // First login → backfill `buyer_id` on any pre-existing
            // guest orders matching this email. Idempotent because
            // we only touch rows where buyer_id IS NULL.
            $linked = Order::query()
                ->whereNull('buyer_id')
                ->where('buyer_email', $token->email)
                ->update(['buyer_id' => $buyer->id]);

            if ($linked > 0) {
                Log::info('buyer.signup.linked_orders', [
                    'buyer_id' => $buyer->id,
                    'count' => $linked,
                ]);
            }

            $buyer->forceFill([
                'last_login_at' => Carbon::now(),
                'last_login_ip' => $ip,
                'email_verified_at' => $buyer->email_verified_at ?? Carbon::now(),
            ])->save();

            $sessionPlaintext = Str::random(48);
            $session = BuyerSession::create([
                'buyer_id' => $buyer->id,
                'token_hash' => hash('sha256', $sessionPlaintext),
                'user_agent_hash' => $userAgent ? hash('sha256', $userAgent) : null,
                'ip_address' => $ip,
                'last_active_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(self::SESSION_TTL_DAYS),
            ]);

            return [
                'buyer' => $buyer,
                'session' => $session,
                'bearer' => $sessionPlaintext,
            ];
        });
    }

    public function resolveSession(string $bearer): ?BuyerSession
    {
        $session = BuyerSession::query()
            ->where('token_hash', hash('sha256', $bearer))
            ->with('buyer')
            ->first();

        if (! $session || ! $session->isActive()) {
            return null;
        }

        $session->forceFill(['last_active_at' => Carbon::now()])->saveQuietly();

        return $session;
    }

    public function revokeSession(BuyerSession $session): void
    {
        $session->forceFill(['revoked_at' => Carbon::now()])->save();
    }

    public function revokeAllSessions(Buyer $buyer): void
    {
        BuyerSession::query()
            ->where('buyer_id', $buyer->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now()]);
    }

    protected function dispatchMagicLink(string $email, string $plaintext): void
    {
        try {
            Mail::to($email)->send(new BuyerMagicLinkMail($email, $plaintext));
        } catch (\Throwable $e) {
            Log::warning('buyer.magic_link.mail_failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
