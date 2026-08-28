<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Gaharu App</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @vite([
    'resources/css/app.css',
    'resources/js/app.js'
    ])

    <style>
        /* ── GLOBAL ── */
        body {
            background: #fbf9f6;
            margin: 0;
            font-family: 'Figtree', sans-serif;
        }

        main {
            padding: 24px 32px !important;
        }

        /* ── CARD & TABLE ── */
        .card {
            border: 1px solid #eadfd4;
            border-radius: 16px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .03);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #5a3416;
            color: white;
            border: none;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: #606060;
            /* abu-abu */
            border-right: none;
            color: #ffffff;
            flex-shrink: 0;
        }

        .sidebar-logo {
            text-align: center;
            padding: 24px 10px;
            border-bottom: 1px solid #4a4a4a;
        }

        .sidebar-logo a {
            text-decoration: none;
        }

        /* ── MENU PARENT (main menu) ── */
        .sidebar-menu {
            padding: 16px 0;
        }

        .menu-group {
            position: relative;
            margin-bottom: 2px;
        }

        .menu-parent {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            /* rata kiri, tidak center */
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: color .2s;
            text-decoration: none;
        }

        .menu-parent:hover,
        .menu-parent.active-menu-root {
            color: #d88656;
            /* terracota saat hover / aktif */
        }

        /* chevron ikut warna parent */
        .menu-parent .chevron-icon {
            color: inherit;
        }

        /* ── SUBMENU (rincian menu) ── */
        .submenu-content {
            display: none;
            flex-direction: column;
            list-style: none;
            padding-left: 0;
            margin: 0 0 8px 0;
            background: #545454;
            /* sedikit lebih gelap dari sidebar */
        }

        .menu-group.open .submenu-content {
            display: flex;
        }

        /* label divider di dalam submenu */
        .submenu-divider {
            padding: 6px 20px 2px 44px;
            font-size: 10px;
            font-weight: 700;
            color: #a0a0a0;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
            pointer-events: none;
            user-select: none;
        }

        .submenu-divider:first-child {
            margin-top: 0;
        }

        /* link submenu — menjorok lebih ke kanan dari parent */
        .submenu-content a {
            display: block;
            padding: 9px 20px 9px 44px;
            /* indent 44px vs parent 20px */
            text-decoration: none;
            color: #e0e0e0;
            font-size: 13.5px;
            font-weight: 400;
            transition: color .2s, background .2s;
        }

        .submenu-content a:hover {
            color: #d88656;
            background: rgba(255, 255, 255, .06);
        }

        .submenu-content a.active {
            color: #d88656;
            font-weight: 600;
            background: rgba(216, 134, 86, .12);
            border-left: 3px solid #d88656;
            padding-left: 41px;
            /* kompensasi border 3px */
        }

        /* ── STEP FLOW items (B2B, CK) ── */
        .submenu-content a.submenu-step {
            display: flex;
            align-items: center;
            padding: 8px 20px 8px 40px;
        }

        .submenu-content a.submenu-step.active {
            padding-left: 37px; /* kompensasi border 3px */
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 17px;
            height: 17px;
            min-width: 17px;
            border-radius: 50%;
            background: rgba(216, 134, 86, .25);
            color: #d88656;
            font-size: 9px;
            font-weight: 700;
            margin-right: 8px;
            flex-shrink: 0;
            transition: background .2s;
        }

        .submenu-content a.submenu-step:hover .step-badge,
        .submenu-content a.submenu-step.active .step-badge {
            background: #d88656;
            color: #fff;
        }

        .logout-btn {
            width: calc(100% - 40px);
            margin: 0 20px;
            border: none;
            background: #d88656;
            color: white;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            transition: background .3s;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #c87443;
        }

        /* ── LAYOUT & CONTENT ── */
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: white;
            padding: 16px 32px;
            border-bottom: 1px solid #eadfd4;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .page-header-container {
            padding: 32px 32px 0 32px;
        }

        .page-header-container h2 {
            color: #111;
            font-size: 26px;
            font-weight: 800;
            margin: 0;
        }

        /* ── BUTTON & BADGE ── */
        .btn-primary {
            background: #d88656 !important;
            border: none !important;
            border-radius: 8px;
        }

        .btn-primary:hover {
            background: #c87443 !important;
        }

        .badge.bg-success {
            background: #d88656 !important;
        }

        .badge.bg-purple {
            background: #b49476 !important;
        }

        .badge.bg-success-100 {
            background: #a1ce86 !important;
        }

        .badge.bg-cyan-500 {
            background: #92c4e6 !important;
        }

        /* ── POPUP TOAST NOTIFICATION ── */
        .popup-toast-wrapper {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .popup-toast {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            max-width: 380px;
            padding: 16px 20px;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .15);
            border-left: 5px solid #2e9e5b;
            pointer-events: auto;
            opacity: 0;
            transform: translateX(120%) scale(.95);
            animation: toastIn .45s cubic-bezier(.34, 1.56, .64, 1) forwards;
        }

        .popup-toast.toast-error {
            border-left-color: #d9534f;
        }

        .popup-toast.toast-hide {
            animation: toastOut .35s ease forwards;
        }

        .popup-toast .toast-icon {
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f7ee;
            color: #2e9e5b;
            font-size: 20px;
            animation: toastIconPop .5s .15s cubic-bezier(.34, 1.56, .64, 1) both;
        }

        .popup-toast.toast-error .toast-icon {
            background: #fbeaea;
            color: #d9534f;
        }

        .popup-toast .toast-text {
            font-size: 14.5px;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1.4;
        }

        .popup-toast .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: #9a9a9a;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            padding: 0 0 0 8px;
            flex-shrink: 0;
        }

        .popup-toast .toast-close:hover {
            color: #1a1a1a;
        }

        @keyframes toastIn {
            0% {
                opacity: 0;
                transform: translateX(120%) scale(.95);
            }

            100% {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        @keyframes toastOut {
            0% {
                opacity: 1;
                transform: translateX(0) scale(1);
                max-height: 100px;
            }

            100% {
                opacity: 0;
                transform: translateX(120%) scale(.95);
                max-height: 0;
                margin-bottom: -12px;
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        @keyframes toastIconPop {
            0% {
                transform: scale(0);
            }

            60% {
                transform: scale(1.15);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
    @stack('styles')
</head>

<body>

    {{-- ── POPUP TOAST NOTIFICATION ── --}}
    <div class="popup-toast-wrapper" id="popupToastWrapper">
        @if(session('success'))
            <div class="popup-toast" data-autohide="4000">
                <div class="toast-icon"><i class="bi bi-check-lg"></i></div>
                <div class="toast-text">{{ session('success') }}</div>
                <button type="button" class="toast-close" aria-label="Tutup">&times;</button>
            </div>
        @endif

        @if(session('error'))
            <div class="popup-toast toast-error" data-autohide="4000">
                <div class="toast-icon"><i class="bi bi-x-lg"></i></div>
                <div class="toast-text">{{ session('error') }}</div>
                <button type="button" class="toast-close" aria-label="Tutup">&times;</button>
            </div>
        @endif
    </div>

    <div class="d-flex min-vh-100">

        @include('layouts.navigation')

        <div class="content-wrapper">

            <header class="topbar">
                <div class="d-flex align-items-center">
                    <div class="text-end me-3">
                        <div class="fw-bold text-dark text-capitalize" style="font-size: 14px;">
                            {{ Auth::user()->nama }}
                        </div>
                        <div class="text-muted text-uppercase font-monospace" style="font-size: 11px; letter-spacing: 0.5px;">
                            {{ Auth::user()->role->nama ?? Auth::user()->role->name ?? 'STAFF' }}
                        </div>
                    </div>

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="btn border-0 p-0 rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                <i class="bi bi-person-fill text-secondary fs-4"></i>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                Profile
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Logout
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </header>

            @isset($header)
            <div class="page-header-container">
                {{ $header }}
            </div>
            @endisset

            <main>
                {{ $slot }}
            </main>

        </div>
    </div>

    <script>
        // ── Auto-dismiss popup toast (sukses/error) ──
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.popup-toast').forEach(function (toast) {
                var delay = parseInt(toast.getAttribute('data-autohide')) || 4000;

                var hideToast = function () {
                    if (toast.classList.contains('toast-hide')) return;
                    toast.classList.add('toast-hide');
                    setTimeout(function () {
                        toast.remove();
                    }, 350);
                };

                var timer = setTimeout(hideToast, delay);

                toast.querySelector('.toast-close').addEventListener('click', function () {
                    clearTimeout(timer);
                    hideToast();
                });
            });
        });
    </script>

    @stack('scripts')

</body>

</html>