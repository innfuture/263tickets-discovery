<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sandbox Affirm · Monthly plan</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(180deg,#0f1a2e 0%, #1e2c4d 100%); display: flex; align-items: center; justify-content: center; color: #f7f7f7;
        }
        .card { background: #fff; color: #0f1a2e; border-radius: 16px; padding: 32px; max-width: 460px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,.30); }
        .logo { font-weight: 800; font-size: 28px; letter-spacing: -.04em; color: #0f1a2e; margin-bottom: 4px; }
        .sub { color: #5d6b85; font-size: 13px; margin-bottom: 20px; }
        .badge { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 700; background: #d6e0fe; color: #0f1a2e; border-radius: 9999px; text-transform: uppercase; letter-spacing: .05em; }
        .schedule { margin: 20px 0; padding: 16px; background: #f3f6ff; border-radius: 12px; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .row + .row { border-top: 1px solid #dee5fb; }
        .apr { font-size: 11px; color: #5d6b85; margin-top: 12px; }
        .actions { display: flex; gap: 8px; margin-top: 16px; }
        button { flex: 1; padding: 12px 16px; border: 0; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .approve { background: #4a4af4; color: #fff; }
        .deny { background: #eef0f7; color: #0f1a2e; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge">Sandbox</span>
    <p class="logo">affirm</p>
    <p class="sub">3 monthly payments · 0% APR for qualified buyers. (Mock checkout — no real loan or credit check.)</p>

    <div class="schedule">
        @foreach ($installments as $i => $inst)
            <div class="row">
                <span>Month {{ $i + 1 }} · {{ $inst['due'] }}</span>
                <strong>{{ $inst['amount'] }} {{ $transaction->currency }}</strong>
            </div>
        @endforeach
        <div class="apr">Estimated APR: 0% · Total: {{ number_format($transaction->amount_minor / 100, 2) }} {{ $transaction->currency }}</div>
    </div>

    <p style="font-size:12px;color:#5d6b85;">Merchant <strong>{{ $transaction->merchant->name ?? 'Sandbox' }}</strong> · loan ref <code>{{ $transaction->reference }}</code></p>

    <form method="post" action="{{ $submitUrl }}" class="actions">
        @csrf
        <input type="hidden" name="reference" value="{{ $transaction->reference }}">
        <button type="submit" name="action" value="approve" class="approve">Accept loan terms</button>
        <button type="submit" name="action" value="deny" class="deny">Decline</button>
    </form>
</div>
</body>
</html>
