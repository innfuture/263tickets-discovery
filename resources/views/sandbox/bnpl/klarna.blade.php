<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sandbox Klarna · Pay in 3</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #ffa8cd; display: flex; align-items: center; justify-content: center;
        }
        .card { background: #fff; border-radius: 16px; padding: 32px; max-width: 440px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,.12); }
        .logo { font-weight: 800; font-size: 24px; color: #0b051d; letter-spacing: -.02em; margin-bottom: 4px; }
        .sub { color: #6b7280; font-size: 13px; margin-bottom: 24px; }
        .badge { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 700; background: #fdf3a0; color: #5b4400; border-radius: 9999px; text-transform: uppercase; letter-spacing: .05em; }
        .schedule { margin: 20px 0; padding: 16px; background: #faf9f5; border-radius: 12px; }
        .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .row + .row { border-top: 1px solid #ece9df; }
        .actions { display: flex; gap: 8px; margin-top: 16px; }
        button { flex: 1; padding: 12px 16px; border: 0; border-radius: 999px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .approve { background: #0b051d; color: white; }
        .deny { background: #f2efe6; color: #0b051d; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge">Sandbox</span>
    <p class="logo">Klarna.</p>
    <p class="sub">Pay in 3 — interest-free monthly instalments. (Mock checkout — no real loan.)</p>
    <div class="schedule">
        @foreach ($installments as $i => $inst)
            <div class="row">
                <span>{{ $i === 0 ? 'Today' : $inst['due'] }}</span>
                <strong>{{ $inst['amount'] }} {{ $transaction->currency }}</strong>
            </div>
        @endforeach
    </div>
    <p style="font-size:12px;color:#6b7280;">Merchant <strong>{{ $transaction->merchant->name ?? 'Sandbox' }}</strong> · order <code>{{ $transaction->reference }}</code></p>
    <form method="post" action="{{ $submitUrl }}" class="actions">
        @csrf
        <input type="hidden" name="reference" value="{{ $transaction->reference }}">
        <button type="submit" name="action" value="approve" class="approve">Confirm and pay {{ $installments[0]['amount'] ?? '0.00' }} {{ $transaction->currency }}</button>
        <button type="submit" name="action" value="deny" class="deny">Cancel</button>
    </form>
</div>
</body>
</html>
