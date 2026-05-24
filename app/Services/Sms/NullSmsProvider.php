<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Local/CI/test default — logs the intended SMS as structured JSON
 * so QA can see the message without spending real credits. Returns a
 * synthetic message id so call sites don't have to special-case the
 * null path.
 */
class NullSmsProvider implements SmsProvider
{
    public function send(string $toMsisdn, string $body, ?string $from = null): ?string
    {
        $id = 'null-sms-'.Str::random(16);

        Log::info('sms.null.would_send', [
            'to' => $toMsisdn,
            'from' => $from,
            'body' => $body,
            'message_id' => $id,
        ]);

        return $id;
    }

    public function identifier(): string
    {
        return 'null';
    }
}
