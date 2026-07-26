<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f97316">
    <title>Delete your account — EldoGas</title>
    <meta name="description" content="Request deletion of your EldoGas account and personal data.">
    <meta name="robots" content="index, follow">
    <link rel="icon" href="/favicon.ico">

    <style>
        :root {
            --brand: #f97316;
            --bg: #ffffff;
            --surface: #fafafa;
            --text: #18181b;
            --muted: #52525b;
            --border: #e4e4e7;
            --danger: #dc2626;
            --success: #16a34a;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #09090b;
                --surface: #131316;
                --text: #f4f4f5;
                --muted: #a1a1aa;
                --border: #27272a;
            }
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 17px;
            line-height: 1.6;
        }

        .wrap { max-width: 640px; margin: 0 auto; padding: 40px 20px 64px; }

        .brand { display: flex; align-items: center; gap: 10px; margin-bottom: 32px; }
        .brand .dot {
            width: 34px; height: 34px; border-radius: 10px;
            background: var(--brand);
            display: grid; place-items: center;
            color: #fff; font-weight: 800;
        }
        .brand strong { font-size: 18px; font-weight: 800; }

        h1 { font-size: 28px; line-height: 1.2; margin: 0 0 8px; }
        p.lead { color: var(--muted); margin: 0 0 28px; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 15px; }

        input[type="tel"], input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            font-size: 17px;
            color: var(--text);
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            outline: none;
        }
        input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(249,115,22,.18); }

        .hint { color: var(--muted); font-size: 14px; margin-top: 8px; }
        .err { color: var(--danger); font-size: 14px; margin-top: 8px; }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 14px 18px;
            font-size: 17px;
            font-weight: 700;
            color: #fff;
            background: var(--brand);
            border: none;
            border-radius: 12px;
            cursor: pointer;
        }
        button:hover { filter: brightness(.96); }
        button.danger { background: var(--danger); }

        .muted-link { color: var(--muted); font-size: 14px; }
        a { color: var(--brand); }

        ul { padding-left: 20px; margin: 8px 0 0; }
        li { margin-bottom: 6px; }

        .notice {
            border-left: 3px solid var(--brand);
            padding: 4px 0 4px 16px;
            color: var(--muted);
            font-size: 15px;
            margin-top: 20px;
        }
        .success-badge {
            display: inline-grid; place-items: center;
            width: 56px; height: 56px; border-radius: 50%;
            background: rgba(22,163,74,.12); color: var(--success);
            font-size: 30px; margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="brand">
            <span class="dot">E</span>
            <strong>EldoGas</strong>
        </div>

        @if (session('deleted'))
            {{-- ── Step 3: confirmation ────────────────────────────────── --}}
            <div class="card" style="text-align:center;">
                <div class="success-badge">✓</div>
                <h1>Your account has been deleted</h1>
                <p class="lead">
                    Your personal details, saved addresses, notification tokens and
                    reward history have been permanently removed, and you've been
                    signed out on all devices.
                </p>
                <p class="muted-link">
                    Past order records are kept in anonymised form for financial and
                    legal compliance. You can register again anytime with the same
                    phone number.
                </p>
            </div>

        @elseif (session('otp_sent'))
            {{-- ── Step 2: enter the code ──────────────────────────────── --}}
            <h1>Confirm deletion</h1>
            <p class="lead">
                If <strong>{{ old('phone') }}</strong> has an EldoGas account, we've
                sent it a 4-digit code by SMS. Enter it below to permanently delete
                the account and its data.
            </p>

            <div class="card">
                <form method="POST" action="{{ route('account-deletion.destroy') }}">
                    @csrf
                    <input type="hidden" name="phone" value="{{ old('phone') }}">
                    <label for="token">Verification code</label>
                    <input type="text" id="token" name="token" inputmode="numeric"
                           autocomplete="one-time-code" maxlength="4" placeholder="1234" required>
                    @error('token') <div class="err">{{ $message }}</div> @enderror
                    <div class="hint">This permanently deletes your account. It cannot be undone.</div>
                    <button type="submit" class="danger">Delete my account</button>
                </form>
            </div>

            <form method="POST" action="{{ route('account-deletion.otp') }}">
                @csrf
                <input type="hidden" name="phone" value="{{ old('phone') }}">
                <button type="submit" style="background:transparent;color:var(--brand);border:1px solid var(--border);">
                    Resend code
                </button>
            </form>

        @else
            {{-- ── Step 1: enter phone ─────────────────────────────────── --}}
            <h1>Delete your EldoGas account</h1>
            <p class="lead">
                Request permanent deletion of your account and personal data. We'll
                text a verification code to your registered number to confirm it's you.
            </p>

            <div class="card">
                <form method="POST" action="{{ route('account-deletion.otp') }}">
                    @csrf
                    <label for="phone">Registered phone number</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                           placeholder="0712 345 678" autocomplete="tel" required>
                    @error('phone') <div class="err">{{ $message }}</div> @enderror
                    <div class="hint">Kenyan mobile number, e.g. 0712 345 678.</div>
                    <button type="submit">Send verification code</button>
                </form>
            </div>

            <h2 style="font-size:18px;margin:8px 0 4px;">What gets deleted</h2>
            <ul>
                <li>Your name, phone number and profile</li>
                <li>Saved delivery addresses and map pins</li>
                <li>Push-notification tokens for your devices</li>
                <li>GasPoints balance, badges and streaks</li>
            </ul>

            <div class="notice">
                Past order records are retained in <strong>anonymised</strong> form
                (no name or phone) for financial and legal compliance, as described
                in our <a href="{{ route('privacy-policy') }}">Privacy Policy</a>.
                Deletion is permanent and takes effect immediately.
            </div>

            <p class="muted-link" style="margin-top:24px;">
                Prefer help? Email
                <a href="mailto:support@eldogas.co.ke">support@eldogas.co.ke</a>
                from your registered number and we'll process it within 30 days.
            </p>
        @endif
    </div>
</body>
</html>
