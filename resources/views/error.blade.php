<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #fef2f2; }
        .card { background: white; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,.08); max-width: 420px; width: 100%; }
        .icon { font-size: 64px; margin-bottom: 16px; }
        h1 { color: #dc2626; margin: 0 0 8px; }
        p { color: #6b7280; margin: 4px 0; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">❌</div>
    <h1>Payment Failed</h1>
    <p>{{ $transaction?->gateway_response['error_message'] ?? 'The payment was not completed.' }}</p>
    @if($transaction)
    <p style="margin-top:16px;font-size:13px;color:#9ca3af;">Ref: {{ $transaction->track_id }}</p>
    @endif
</div>
</body>
</html>
