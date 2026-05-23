<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sandbox 3DS Authentication</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,.08);
            padding: 32px;
            max-width: 440px;
            width: 100%;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #6b21a8;
            background: #f3e8ff;
            border-radius: 9999px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        h1 { font-size: 18px; margin: 12px 0 4px; }
        .sub { color: #6b7280; font-size: 13px; margin-bottom: 24px; }
        dl { margin: 0 0 24px; padding: 16px; background: #f9fafb; border-radius: 8px; font-size: 13px; }
        dt { color: #6b7280; font-weight: 500; margin-top: 6px; }
        dd { margin: 0 0 6px; font-family: 'SF Mono', Menlo, monospace; }
        .actions { display: flex; gap: 8px; }
        button {
            flex: 1;
            padding: 12px 16px;
            border: 0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .approve { background: #10b981; color: white; }
        .approve:hover { background: #059669; }
        .deny { background: #ef4444; color: white; }
        .deny:hover { background: #dc2626; }
        .hint {
            margin-top: 16px;
            padding: 10px;
            background: #fef3c7;
            border-radius: 6px;
            font-size: 12px;
            color: #78350f;
        }
    </style>
</head>
<body>
<div class="card">
    <span class="badge">Sandbox 3DS</span>
    <h1>Confirm card payment</h1>
    <p class="sub">This is a mock Access Control Server. No real authentication is performed.</p>

    <dl>
        <dt>Merchant</dt>
        <dd>{{ $transaction->merchant->name ?? 'Sandbox merchant' }}</dd>
        <dt>Amount</dt>
        <dd>{{ number_format($transaction->amount_minor / 100, 2) }} {{ $transaction->currency }}</dd>
        <dt>Card</dt>
        <dd>{{ strtoupper($transaction->instrument_brand ?? 'CARD') }} •••• {{ $transaction->instrument_last4 ?? '0000' }}</dd>
        <dt>Reference</dt>
        <dd>{{ $transaction->reference }}</dd>
    </dl>

    @if ($accept)
        <div class="hint">Scenario hint: this challenge is configured to <strong>pass</strong> on approve.</div>
    @else
        <div class="hint">Scenario hint: this challenge is configured to <strong>fail</strong>. Either button records failure.</div>
    @endif

    <form method="post" action="{{ $submitUrl }}" class="actions" style="margin-top: 16px;">
        @csrf
        <input type="hidden" name="reference" value="{{ $transaction->reference }}">
        <button type="submit" name="action" value="approve" class="approve">Approve</button>
        <button type="submit" name="action" value="deny" class="deny">Deny</button>
    </form>
</div>
</body>
</html>
