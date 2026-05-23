<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sandbox Afterpay · 4 instalments</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #b2fce4; display: flex; align-items: center; justify-content: center;
        }
        .card { background: #fff; border-radius: 16px; padding: 32px; max-width: 440px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,.10); }
        .logo { font-weight: 800; font-size: 26px; color: #03110a; letter-spacing: -.04em; margin-bottom: 4px; }
        .logo .stroke { color: #00d287; }
        .sub { color: #486455; font-size: 13px; margin-bottom: 24px; }
        .badge { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 700; background: #d8f7e8; color: #00583a; border-radius: 9999px; text-transform: uppercase; letter-spacing: .05em; }
        .schedule { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 20px 0; }
        .pill { padding: 10px 8px; border-radius: 10px; background: #f1faf5; text-align: center; font-size: 12px; }
        .pill strong { display: block; font-size: 14px; color: #03110a; margin-top: 4px; }
        .actions { display: flex; gap: 8px; margin-top: 16px; }
        button { flex: 1; padding: 12px 16px; border: 0; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .approve { background: #00d287; color: #03110a; }
        .deny { background: #f1faf5; color: #03110a; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge">Sandbox</span>
    <p class="logo">after<span class="stroke">pay</span>.</p>
    <p class="sub">Four interest-free payments. (Mock checkout — no real loan.)</p>
    <div class="schedule">
        @foreach ($installments as $i => $inst)
            <div class="pill">
                {{ $i === 0 ? 'Today' : 'Wk ' . ($i * 2) }}
                <strong>{{ $inst['amount'] }}</strong>
            </div>
        @endforeach
    </div>
    <p style="font-size:12px;color:#486455;">Merchant <strong>{{ $transaction->merchant->name ?? 'Sandbox' }}</strong> · order <code>{{ $transaction->reference }}</code></p>
    <form method="post" action="{{ $submitUrl }}" class="actions">
        @csrf
        <input type="hidden" name="reference" value="{{ $transaction->reference }}">
        <button type="submit" name="action" value="approve" class="approve">Pay {{ $installments[0]['amount'] ?? '0.00' }} {{ $transaction->currency }} now</button>
        <button type="submit" name="action" value="deny" class="deny">Cancel</button>
    </form>
</div>
</body>
</html>
