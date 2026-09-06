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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    @vite([
    'resources/css/app.css',
    'resources/js/app.js'
    ])

    <style>
        :root {
            --gaharu-primary: #DE8958;
            --gaharu-primary-hover: #C87443;
            --gaharu-brown: #715745;
            --gaharu-dark: #1A1A1A;
            --gaharu-gray: #DCD3CB;
            --gaharu-bg: #F9F7F5;
        }

        /* ── GLOBAL ── */
        body {
            background: var(--gaharu-bg);
            color: var(--gaharu-dark);
            margin: 0;
            font-family: 'Figtree', sans-serif;
            overflow-x: hidden;
        }

        main {
            padding: clamp(16px, 2.5vw, 24px) clamp(16px, 3.5vw, 32px) !important;
        }

        /* ── CARD & TABLE ── */
        .card {
            border: 1px solid var(--gaharu-gray);
            border-radius: 14px;
            background: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .03);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th, .table-custom-header th {
            background: var(--gaharu-brown) !important;
            color: white !important;
            border: none;
        }

        /* ── SELECT2 CUSTOM ── */
        .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted {
            background-color: var(--gaharu-primary) !important;
            color: #fff !important;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection,
        .select2-container--bootstrap-5.select2-container--open .select2-selection {
            border-color: var(--gaharu-primary) !important;
            box-shadow: 0 0 0 0.25rem rgba(222, 137, 88, 0.25) !important;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 260px;
            height: 100vh;
            overflow-y: auto;
            position: sticky;
            top: 0;
            background: #606060;
            border-right: none;
            color: #ffffff;
            flex-shrink: 0;
            z-index: 1050;
            transition: transform .3s ease-in-out, box-shadow .3s ease-in-out;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .sidebar-logo {
            text-align: center;
            padding: 24px 10px;
            border-bottom: 1px solid #4a4a4a;
            position: relative;
            background: transparent;
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
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: color .2s, background .2s;
            text-decoration: none;
        }

        .menu-parent:hover {
            color: #d88656;
            background: rgba(255, 255, 255, 0.05);
        }

        .menu-parent.active-menu-root {
            color: #d88656;
            background: rgba(216, 134, 86, 0.12);
        }

        /* chevron ikut warna parent */
        .menu-parent .chevron-icon {
            color: inherit;
            font-size: 12px;
            transition: transform .2s;
        }

        .menu-parent:hover .chevron-icon {
            color: #d88656;
        }

        /* ── SUBMENU (rincian menu) ── */
        .submenu-content {
            display: none;
            flex-direction: column;
            list-style: none;
            padding-left: 0;
            margin: 0 0 8px 0;
            background: #545454;
            border-top: none;
            border-bottom: none;
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

        /* link submenu */
        .submenu-content a {
            display: block;
            padding: 9px 20px 9px 44px;
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
        }

        /* ── STEP FLOW items (B2B, CK) ── */
        .submenu-content a.submenu-step {
            display: flex;
            align-items: center;
            padding: 8px 20px 8px 40px;
        }

        .submenu-content a.submenu-step.active {
            padding-left: 37px;
        }

        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 17px;
            height: 17px;
            min-width: 17px;
            border-radius: 50%;
            background: rgba(222, 137, 88, .25);
            color: var(--gaharu-primary);
            font-size: 9px;
            font-weight: 700;
            margin-right: 8px;
            flex-shrink: 0;
            transition: background .2s;
        }

        .submenu-content a.submenu-step:hover .step-badge,
        .submenu-content a.submenu-step.active .step-badge {
            background: var(--gaharu-primary);
            color: #fff;
        }

        .logout-btn {
            width: calc(100% - 40px);
            margin: 0 20px;
            border: none;
            background: var(--gaharu-primary);
            color: white;
            border-radius: 8px;
            padding: 10px;
            font-weight: 600;
            transition: background .3s;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: var(--gaharu-primary-hover);
        }

        /* ── LAYOUT & CONTENT ── */
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .topbar {
            background: white;
            padding: 14px 24px;
            border-bottom: 1px solid var(--gaharu-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header-container {
            padding: clamp(16px, 3vw, 32px) clamp(16px, 3.5vw, 32px) 0 clamp(16px, 3.5vw, 32px);
        }

        .page-header-container h2 {
            color: var(--gaharu-dark);
            font-size: clamp(20px, 4vw, 26px);
            font-weight: 800;
            margin: 0;
        }

        /* ── BUTTON & BADGE BRAND COLOR ── */
        .btn-primary, .btn-custom-orange, .btn-gaharu-primary {
            background: var(--gaharu-primary) !important;
            border: none !important;
            color: white !important;
            border-radius: 8px;
        }

        .btn-primary:hover, .btn-custom-orange:hover, .btn-gaharu-primary:hover {
            background: var(--gaharu-primary-hover) !important;
            color: white !important;
        }

        .btn-outline-primary {
            border-color: var(--gaharu-primary) !important;
            color: var(--gaharu-primary) !important;
        }

        .btn-outline-primary:hover {
            background-color: var(--gaharu-primary) !important;
            color: white !important;
        }

        /* ── MOBILE RESPONSIVE DRAWER & TOUCH OPTIMIZATION ── */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            z-index: 1040;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                height: 100vh;
                transform: translateX(-100%);
                box-shadow: 0 0 25px rgba(0,0,0,0.35);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            body.sidebar-open .sidebar-backdrop {
                display: block;
            }

            .topbar {
                padding: 10px 16px;
            }
        }

        @media (max-width: 767.98px) {
            .table-responsive {
                -webkit-overflow-scrolling: touch;
            }
            .form-control, .form-select {
                font-size: 16px !important;
            }
            .btn-action-base {
                width: 36px !important;
                height: 36px !important;
                font-size: 0.9rem !important;
            }
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

    <div class="d-flex min-vh-100 position-relative">

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        @include('layouts.navigation')

        <div class="content-wrapper">

            <header class="topbar">
                <button class="btn btn-light d-lg-none border-0 p-2 me-auto shadow-sm rounded-3 d-flex align-items-center justify-content-center" id="sidebarToggleMobile" type="button" aria-label="Toggle Menu" style="width: 40px; height: 40px;">
                    <i class="bi bi-list fs-4 text-dark"></i>
                </button>

                <div class="d-flex align-items-center ms-auto">
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

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ── Offcanvas Mobile Navigation Drawer JS ──
        document.addEventListener('DOMContentLoaded', function () {
            var toggleBtn = document.getElementById('sidebarToggleMobile');
            var closeBtn = document.getElementById('sidebarCloseMobile');
            var backdrop = document.getElementById('sidebarBackdrop');

            function toggleSidebar() {
                document.body.classList.toggle('sidebar-open');
            }

            function closeSidebar() {
                document.body.classList.remove('sidebar-open');
            }

            if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
            if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
            if (backdrop) backdrop.addEventListener('click', closeSidebar);

            // Auto close mobile menu when clicking nav link on mobile screens
            document.querySelectorAll('.sidebar-menu a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        closeSidebar();
                    }
                });
            });

            // ── Auto-dismiss popup toast (sukses/error) ──
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

            // ── Auto-check Event Notifikasi (SweetAlert) untuk halaman aktif ──
            checkEventNotifications();
        });

        function checkEventNotifications() {
            // Tentukan konteks menu berdasarkan URL halaman saat ini
            let currentPath = window.location.pathname;
            let menu = 'semua';
            if (currentPath.includes('/pembelian')) {
                menu = 'pembelian';
            } else if (currentPath.includes('/pengeluaran-bahan-baku')) {
                menu = 'permintaan';
            } else if (currentPath.includes('/produksi') || currentPath.includes('/ck-produksi')) {
                menu = 'produksi';
            } else if (currentPath.includes('/penjualan_pos')) {
                menu = 'penjualan_pos';
            }

            fetch('/api/event-notifikasi-aktif?menu=' + menu)
                .then(res => res.json())
                .then(events => {
                    if (!events || events.length === 0) return;

                    // Filter event yang belum dipilih "Jangan tampilkan lagi" di localStorage
                    let pendingEvents = events.filter(ev => {
                        let dismissedKey = 'event_dismissed_' + ev.id;
                        return !localStorage.getItem(dismissedKey);
                    });

                    if (pendingEvents.length === 0) return;

                    // Tampilkan event secara berurutan
                    displaySequentialSweetAlert(pendingEvents, 0);
                })
                .catch(err => console.error('Error fetching event notifications:', err));
        }

        function displaySequentialSweetAlert(events, index) {
            if (index >= events.length) return;

            let ev = events[index];
            Swal.fire({
                title: ev.judul,
                html: `<p style="font-size: 15px; margin-bottom: 12px; color: #374151; line-height: 1.6;">${ev.pesan}</p>`,
                icon: ev.tipe_icon || 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check2 me-1"></i> Mengerti',
                cancelButtonText: '<i class="bi bi-eye-slash me-1"></i> Jangan tampilkan lagi',
                confirmButtonColor: '#7A4517',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusConfirm: true,
                allowOutsideClick: false,
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    // Simpan di localStorage agar tidak muncul lagi
                    localStorage.setItem('event_dismissed_' + ev.id, 'true');
                }
                
                // Tampilkan notifikasi berikutnya jika ada
                displaySequentialSweetAlert(events, index + 1);
            });
        }
    </script>

    <!-- jQuery & Select2 JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            if (typeof $.fn.select2 !== 'undefined') {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: $(this).data('placeholder') || '-- Pilih --'
                });
            }
        });
    </script>

    @stack('scripts')

</body>

</html>