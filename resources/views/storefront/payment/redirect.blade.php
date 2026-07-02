<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment {{ $success ? 'Successful' : 'Update' }} — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb; color: #111827; }
        .box { text-align: center; padding: 2rem; background: #fff; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); max-width: 28rem; }
        .spinner { width: 2rem; height: 2rem; border: 3px solid #fde68a; border-top-color: #F57C00; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #F57C00; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <p>{{ $message }}</p>
        <p class="text-sm" style="color:#6b7280;margin-top:1rem;">Redirecting to your order…</p>
        <p style="margin-top:1rem;"><a href="{{ $redirectUrl }}">Click here if you are not redirected</a></p>
    </div>
    <script>
        (function () {
            var url = @json($redirectUrl);
            try {
                if (window.top && window.top !== window.self) {
                    window.top.location.href = url;
                } else {
                    window.location.href = url;
                }
            } catch (e) {
                window.location.href = url;
            }
        })();
    </script>
</body>
</html>
