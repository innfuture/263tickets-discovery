<?php

declare(strict_types=1);

namespace App\Services\Stakeholders;

use App\Mail\StakeholderMagicLinkMail;
use App\Models\Stakeholder;
use App\Models\StakeholderLoginToken;
use App\Models\StakeholderSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Magic-link auth for stakeholders. Identical model to the buyer
 * subsystem — duplicated rather than abstracted because:
 *
 *   - Different token TTLs (longer for stakeholders — they don't
 *     log in daily).
 *   - Different mailable copy + brand.
 *   - Different ownership backfill semantics (no guest orders to link).
 */
class StakeholderAuthService
{
    public const LINK_TTL_MINUTES = 30;

    public const SESSION_TTL_DAYS = 90;

    public function requestLink(string $email, ?string $ip = null): StakeholderLoginToken
    {
        $email = strtolower(trim($email));
        $plaintext = Str::random(48);

        $token = StakeholderLoginToken::create([
            'stakeholder_id' => Stakeholder::query()->where('email', $email)->value('id'),
            'email' => $email,
            'token_hash' => hash('sha256', $plaintext),
            'expires_at' => Carbon::now()->addMinutes(self::LINK_TTL_MINUTES),
        ]);

        try {
            Mail::to($email)->send(new StakeholderMagicLinkMail($email, $plaintext));
        } catch (\Throwable $e) {
            Log::warning('stakeholder.magic_link.mail_failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return $token;
    }

    public function verifyToken(string $plaintext, ?string $ip = null): array
    {
        $hash = hash('sha256', $plaintext);
        $token = StakeholderLoginToken::query()->where('token_hash', $hash)->first();
        if (! $token || $token->consumed_at !== null) {
            throw new RuntimeException('Invalid or already-used login link.');
        }
        if ($token->expires_at?->isPast()) {
            throw new RuntimeException('Login link has expired.');
        }

        $token->forceFill(['consumed_at' => Carbon::now()])->save();

        $stakeholder = Stakeholder::query()->where('email', $token->email)->first();
        if (! $stakeholder) {
            throw new RuntimeException('No stakeholder account for this email — sign up first.');
        }

        $stakeholder->forceFill([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $ip,
            'email_verified_at' => $stakeholder->email_verified_at ?? Carbon::now(),
        ])->save();

        $sessionPlaintext = Str::random(48);
        $session = StakeholderSession::create([
            'stakeholder_id' => $stakeholder->id,
            'token_hash' => hash('sha256', $sessionPlaintext),
            'ip_address' => $ip,
            'last_active_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(self::SESSION_TTL_DAYS),
        ]);

        return [
            'stakeholder' => $stakeholder,
            'session' => $session,
            'bearer' => $sessionPlaintext,
        ];
    }

    public function resolveSession(string $bearer): ?StakeholderSession
    {
        $session = StakeholderSession::query()
            ->where('token_hash', hash('sha256', $bearer))
            ->with('stakeholder')
            ->first();

        if (! $session || ! $session->isActive()) {
            return null;
        }
        $session->forceFill(['last_active_at' => Carbon::now()])->saveQuietly();

        return $session;
    }

    public function revokeSession(StakeholderSession $session): void
    {
        $session->forceFill(['revoked_at' => Carbon::now()])->save();
    }
}
