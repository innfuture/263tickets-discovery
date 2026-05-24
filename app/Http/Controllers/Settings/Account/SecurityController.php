<?php

namespace App\Http\Controllers\Settings\Account;

use App\Http\Controllers\Settings\SettingsController;
use App\Models\UserSession;
use App\Models\UserTwoFactorSecret;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account security: two-factor enrolment (TOTP-style), recovery codes,
 * and a trusted-IP allowlist. The TOTP secret is base32 random — the
 * controller emits an otpauth:// URI that any standard authenticator
 * app (1Password, Authy, Google Authenticator) can scan.
 */
class SecurityController extends SettingsController
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        /** @var UserTwoFactorSecret|null $twoFactor */
        $twoFactor = UserTwoFactorSecret::query()->find($user->id);

        $allowlist = (array) ($user->trusted_ip_allowlist ?? []);

        return Inertia::render('settings/account/security', [
            'twoFactor' => $twoFactor ? [
                'enrolled' => true,
                'confirmed' => $twoFactor->isConfirmed(),
                'otpauthUri' => $twoFactor->isConfirmed()
                    ? null
                    : $this->otpauthUri($user->email, $twoFactor->secret),
                'recoveryCodes' => $twoFactor->isConfirmed()
                    ? array_map(fn () => '••••-••••', $twoFactor->recovery_codes ?? [])
                    : ($twoFactor->recovery_codes ?? []),
            ] : ['enrolled' => false, 'confirmed' => false, 'otpauthUri' => null, 'recoveryCodes' => []],
            'allowlist' => $allowlist,
            'lastLogin' => [
                'at' => optional(UserSession::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('last_active_at')
                    ->first())?->last_active_at?->toIso8601String(),
                'ip' => optional(UserSession::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('last_active_at')
                    ->first())?->ip_address,
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Security', 'href' => '/settings/security'],
            ],
        ]);
    }

    /**
     * Begin TOTP enrolment: generate a fresh base32 secret + 8 single-
     * use recovery codes. The user is shown the otpauth:// QR until
     * they confirm.
     */
    public function enrolTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();

        UserTwoFactorSecret::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => $this->randomBase32(32),
                'recovery_codes' => collect(range(1, 8))
                    ->map(fn () => Str::lower(Str::random(10)))
                    ->all(),
                'confirmed_at' => null,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scan the QR with your authenticator, then confirm with a code.')]);

        return back();
    }

    public function confirmTwoFactor(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        $two = UserTwoFactorSecret::query()->where('user_id', $user->id)->firstOrFail();
        if (! $this->verifyTotp($two->secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => __('That code didn\'t match. Try again with a fresh one.')]);
        }

        $two->forceFill(['confirmed_at' => now()])->save();

        $audit->record('account.2fa.confirmed', user: $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor enabled. Save your recovery codes somewhere safe.')]);

        return back();
    }

    public function disableTwoFactor(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        UserTwoFactorSecret::query()->where('user_id', $user->id)->delete();

        $audit->record('account.2fa.disabled', user: $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor disabled.')]);

        return back();
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        $two = UserTwoFactorSecret::query()->where('user_id', $user->id)->firstOrFail();

        $two->forceFill([
            'recovery_codes' => collect(range(1, 8))
                ->map(fn () => Str::lower(Str::random(10)))
                ->all(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New recovery codes generated.')]);

        return back()->with('newRecoveryCodes', $two->recovery_codes);
    }

    public function updateAllowlist(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'ips' => ['nullable', 'array', 'max:32'],
            'ips.*' => ['ip'],
        ]);

        $user = $request->user();
        $user->forceFill(['trusted_ip_allowlist' => $request->input('ips', [])])->save();

        $audit->record('account.ip_allowlist.updated', user: $user, after: ['ips' => $request->input('ips', [])]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Trusted IP allowlist saved.')]);

        return back();
    }

    private function otpauthUri(string $email, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'Platform'));
        $account = rawurlencode($email);

        return "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    private function randomBase32(int $length): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }

        return $out;
    }

    /**
     * RFC 6238 verifier with a ±1-step window.
     */
    private function verifyTotp(string $secret, string $code, int $period = 30): bool
    {
        $key = $this->base32Decode($secret);
        $now = floor(time() / $period);

        foreach ([-1, 0, 1] as $offset) {
            $counter = pack('N*', 0).pack('N*', (int) ($now + $offset));
            $hash = hash_hmac('sha1', $counter, $key, true);
            $byte = ord($hash[19]) & 0xF;
            $value = ((ord($hash[$byte]) & 0x7F) << 24)
                | ((ord($hash[$byte + 1]) & 0xFF) << 16)
                | ((ord($hash[$byte + 2]) & 0xFF) << 8)
                | (ord($hash[$byte + 3]) & 0xFF);
            $candidate = str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim($secret, '='));
        $buffer = 0;
        $bits = 0;
        $out = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $out;
    }
}
