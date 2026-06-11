<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f0fdf4; }
        .card { background: white; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,.08); max-width: 420px; width: 100%; }
        .icon { font-size: 64px; margin-bottom: 16px; }
        h1 { color: #16a34a; margin: 0 0 8px; }
        p { color: #6b7280; margin: 4px 0; }
        .meta { background: #f9fafb; border-radius: 8px; padding: 16px; margin-top: 20px; text-align: left; font-size: 14px; }
        .meta dt { font-weight: 600; color: #374151; }
        .meta dd { color: #6b7280; margin: 2px 0 10px 0; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">✅</div>
    <h1>Payment Successful</h1>
    <p>Your payment has been captured.</p>
    @if($transaction)
    <dl class="meta">
        <dt>Transaction ID</dt><dd>{{ $transaction->ulid }}</dd>
        <dt>Amount</dt><dd>{{ number_format($transaction->amount, 3) }} {{ $transaction->currency }}</dd>
        <dt>Gateway</dt><dd>{{ strtoupper($transaction->gateway) }}</dd>
        @if($transaction->reference_id)<dt>Reference</dt><dd>{{ $transaction->reference_id }}</dd>@endif
    </dl>
    @endif
</div>
</body>
</html>
