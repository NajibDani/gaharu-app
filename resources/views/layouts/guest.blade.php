<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Gaharu') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Figtree', sans-serif;
            min-height: 100vh;
            color: #1a1a1a;

            /* ── BACKGROUND PHOTO ──
               Ganti path di bawah ini sesuai lokasi file gambar kamu.
               Contoh jika gambar disimpan di public/images/login-bg.jpg */
            background-image:
                linear-gradient(180deg, rgba(20, 12, 6, 0.45) 0%, rgba(20, 12, 6, 0.55) 100%),
                url('{{ asset('images/login-bg.jpg') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;

            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        /* ── BRAND (logo di atas kartu) ── */
        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            text-decoration: none;
            margin-bottom: 28px;
        }

        .brand-logo img,
        .brand-logo svg {
            height: 56px;
            width: auto;
            filter: brightness(0) invert(1) drop-shadow(0 2px 6px rgba(0,0,0,0.25));
        }

        /* ── GLASS CARD ── */
        .auth-card-wrapper {
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .auth-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 20px;
            padding: 40px 36px;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        }

        .auth-title {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .auth-subtitle {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 32px;
        }

        /* ── FORM ELEMENTS ── */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 7px;
            letter-spacing: 0.1px;
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1.5px solid rgba(255, 255, 255, 0.35);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Figtree', sans-serif;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.10);
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            outline: none;
        }

        .form-input:focus {
            border-color: #dd7045;
            background: rgba(255, 255, 255, 0.18);
            box-shadow: 0 0 0 3px rgba(221, 112, 69, 0.25);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.55);
        }

        .form-error {
            font-size: 12px;
            color: #ffb4a8;
            margin-top: 5px;
        }

        /* ── CHECKBOX ── */
        .check-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .check-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
        }

        .check-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            accent-color: #dd7045;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 13px;
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.15s;
        }

        .forgot-link:hover { opacity: 0.75; }

        /* ── BUTTON ── */
        .btn-primary {
            width: 100%;
            height: 48px;
            background: #dd7045;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Figtree', sans-serif;
            cursor: pointer;
            transition: background 0.18s, transform 0.1s;
            letter-spacing: 0.2px;
        }

        .btn-primary:hover { background: #c45e33; }
        .btn-primary:active { transform: scale(0.99); }

        .btn-secondary {
            width: 100%;
            height: 48px;
            background: transparent;
            color: rgba(255, 255, 255, 0.85);
            border: 1.5px solid rgba(255, 255, 255, 0.35);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            font-family: 'Figtree', sans-serif;
            cursor: pointer;
            transition: border-color 0.18s, color 0.18s;
            margin-top: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-secondary:hover {
            border-color: #dd7045;
            color: #ffffff;
        }

        /* ── STATUS ALERT ── */
        .alert-status {
            background: rgba(134, 239, 172, 0.15);
            border: 1px solid rgba(134, 239, 172, 0.5);
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            color: #d1fae5;
            margin-bottom: 20px;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 480px) {
            .auth-card {
                padding: 32px 24px;
                border-radius: 16px;
            }
            .brand-logo img,
            .brand-logo svg {
                height: 44px;
            }
        }
    </style>
</head>
<body>

    <div class="auth-card-wrapper">
        {{-- ── LOGO DI ATAS KARTU ── --}}
        <a href="{{ route('dashboard') }}" class="brand-logo">
            <x-application-logo style="height:64px; width:auto; filter:brightness(0) invert(1);" />
        </a>

        {{-- ── GLASS LOGIN CARD ── --}}
        <div class="auth-card">
            {{ $slot }}
        </div>
    </div>

</body>
</html>