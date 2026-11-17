<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            @if($jenis == 'utang') Buku Besar Pembantu Utang
            @elseif($jenis == 'piutang') Buku Besar Pembantu Piutang
            @elseif($jenis == 'um-pembelian') Buku Pembantu Uang Muka Pembelian
            @elseif($jenis == 'um-penjualan') Buku Pembantu Uang Muka Penjualan
            @endif
        </h2>
    </x-slot>

    <style>
        :root {
            --brand: #e07a5f;
            --brand-soft: #fdf2f0;
        }

        .breadcrumb { font-size: 13px; color: #64748b; margin-bottom: 20px; }

        .tab-navigation {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: transparent;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none;
            position: relative;
            bottom: -2px;
            border-bottom: 2px solid transparent;
        }

        .tab-btn.active {
            color: var(--brand);
            border-bottom-color: var(--brand);
        }

        .filter-tagihan-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            transition: all 0.15s ease-in-out;
        }

        .filter-tagihan-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .filter-tagihan-btn.active-all {
            background: #1e293b;
            color: #ffffff;
            border-color: #1e293b;
        }

        .filter-tagihan-btn.active-pending {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }

        .filter-tagihan-btn.active-overdue {
            background: #b91c1c;
            color: #ffffff;
            border-color: #991b1b;
        }

        .filter-tagihan-btn.active-lunas {
            background: #16a34a;
            color: #ffffff;
            border-color: #16a34a;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        .metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }

        .metric-title { font-size: 13px; color: #64748b; margin-bottom: 8px; font-weight: 500; }
        .metric-value { font-size: 22px; font-weight: 700; color: var(--brand); }
        .metric-value.dark { color: #1e293b; }
        .metric-value.danger { color: #dc2626; }

        .entity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 20px;
        }

        .entity-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            position: relative;
            transition: all 0.2s ease;
        }

        .entity-card.has-tagihan {
            border-color: #fca5a5;
            background: linear-gradient(180deg, #fffbfb 0%, #ffffff 60px);
        }

        .entity-card.has-overdue {
            border-color: #f87171;
            border-width: 1.5px;
            box-shadow: 0 4px 12px -2px rgba(220, 38, 38, 0.08);
        }

        .entity-code {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            color: var(--brand);
            background: var(--brand-soft);
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 12px;
        }

        .badge-status {
            float: right;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-overdue { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-pending { background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; }
        .badge-lunas { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        .entity-name { font-size: 16px; font-weight: 700; margin-bottom: 4px; color: #1e293b; }
        .entity-sub { font-size: 12px; color: #64748b; margin-bottom: 14px; }

        .tempo-alert-box {
            background: #fff1f2;
            border: 1px dashed #fca5a5;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            color: #991b1b;
            margin-bottom: 14px;
        }

        .saldo-label { font-size: 12px; color: #64748b; }
        .saldo-value { font-size: 18px; font-weight: 700; color: var(--brand); }
        .saldo-value.danger { color: #dc2626; }

        .btn-detail {
            float: right;
            color: var(--brand);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding-top: 4px;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 48px;
            background: #ffffff;
            border: 1px dashed #e2e8f0;
            border-radius: 12px;
            color: #64748b;
        }
    </style>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="breadcrumb">
                Home / 
                @if(in_array($jenis, ['utang', 'um-pembelian'])) Pembelian @else Penjualan @endif / 
                Buku Pembantu
            </div>

            <!-- Tab Navigasi Menu 4 Pilihan & Search Form -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div class="tab-navigation mb-0" style="flex-wrap: wrap;">
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'utang', 'search' => request('search'), 'status_tagihan' => request('status_tagihan')]) }}" class="tab-btn {{ $jenis == 'utang' ? 'active' : '' }}">Utang Usaha</a>
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'piutang', 'search' => request('search'), 'status_tagihan' => request('status_tagihan')]) }}" class="tab-btn {{ $jenis == 'piutang' ? 'active' : '' }}">Piutang Usaha</a>
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'um-pembelian', 'search' => request('search')]) }}" class="tab-btn {{ $jenis == 'um-pembelian' ? 'active' : '' }}">Uang Muka Pembelian</a>
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'um-penjualan', 'search' => request('search')]) }}" class="tab-btn {{ $jenis == 'um-penjualan' ? 'active' : '' }}">Uang Muka Penjualan</a>
                </div>

                <!-- Search Input Form -->
                <form action="{{ route('buku-pembantu.index') }}" method="GET" class="d-flex align-items-center gap-2" style="min-width: 260px;">
                    <input type="hidden" name="jenis" value="{{ $jenis }}">
                    @if(request('status_tagihan'))
                        <input type="hidden" name="status_tagihan" value="{{ request('status_tagihan') }}">
                    @endif
                    <div style="position: relative; width: 100%;">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / ID {{ in_array($jenis, ['utang', 'um-pembelian']) ? 'supplier' : 'customer' }}..." value="{{ $search ?? request('search') }}" style="border-radius: 8px; border: 1px solid #cbd5e1; padding: 7px 12px 7px 32px; font-size: 13px; width: 100%;">
                        <i class="bi bi-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    </div>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--brand); color: #fff; border-radius: 8px; font-weight: 600; padding: 7px 14px; white-space: nowrap;">Cari</button>
                    @if(!empty($search))
                        <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis, 'status_tagihan' => request('status_tagihan')]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; padding: 7px 12px; white-space: nowrap;">Reset</a>
                    @endif
                </form>
            </div>

            {{-- BANNER PERINGATAN SUPPLIER BELUM LUNAS / LEWAT JATUH TEMPO (KHUSUS UTANG USAHA) --}}
            @if($jenis === 'utang' && ($summary['total_pending'] ?? 0) > 0)
                <div class="alert mb-4 shadow-sm" style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 16px 20px;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width: 42px; height: 42px; border-radius: 10px; background-color: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1" style="color: #991b1b; font-size: 15px;">
                                    Peringatan: Terdapat {{ $summary['total_pending'] }} Supplier Belum Lunas
                                </h6>
                                <p class="mb-0 text-muted" style="font-size: 13px; line-height: 1.4;">
                                    Total kewajiban utang berjalan pembelian bahan baku: <strong class="text-danger">Rp {{ number_format($summary['total_saldo'] ?? 0, 0, ',', '.') }}</strong>.
                                    @if(($summary['total_overdue'] ?? 0) > 0)
                                        <span class="badge bg-danger ms-1" style="font-size: 11px;">
                                            <i class="bi bi-clock-history me-1"></i> {{ $summary['total_overdue'] }} Supplier Lewat Jatuh Tempo
                                        </span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <a href="{{ route('pembelian.index') }}" class="btn btn-sm btn-outline-danger" style="border-radius: 8px; font-weight: 600; font-size: 12px;">
                                <i class="bi bi-cart-check me-1"></i> Ke Menu Pembelian
                            </a>
                            @if(($statusTagihan ?? 'semua') !== 'belum_lunas')
                                <a href="{{ route('buku-pembantu.index', ['jenis' => 'utang', 'status_tagihan' => 'belum_lunas', 'search' => request('search')]) }}" class="btn btn-sm btn-danger text-white" style="border-radius: 8px; font-weight: 600; font-size: 12px;">
                                    Filter Belum Lunas ({{ $summary['total_pending'] }})
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="metric-card">
                    <div class="metric-title">
                        @if($jenis == 'utang') Total Utang Berjalan
                        @elseif($jenis == 'piutang') Total Piutang Berjalan
                        @elseif($jenis == 'um-pembelian') Total Uang Muka Pembelian
                        @elseif($jenis == 'um-penjualan') Total Uang Muka Penjualan
                        @endif
                    </div>
                    <div class="metric-value {{ ($summary['total_saldo'] ?? 0) > 0 ? 'danger' : '' }}">
                        Rp {{ number_format($summary['total_saldo'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">
                        @if(in_array($jenis, ['utang', 'um-pembelian'])) Jumlah Akun / Supplier @else Jumlah Akun / Customer @endif
                    </div>
                    <div class="metric-value dark">{{ $summary['total_akun'] ?? 0 }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">Akun Belum Lunas / Ada Tagihan</div>
                    <div class="metric-value {{ ($summary['total_pending'] ?? 0) > 0 ? 'danger' : 'dark' }}">
                        {{ $summary['total_pending'] ?? 0 }}
                        @if(($summary['total_overdue'] ?? 0) > 0)
                            <small style="font-size: 13px; font-weight: normal; color: #dc2626;">({{ $summary['total_overdue'] }} overdue)</small>
                        @endif
                    </div>
                </div>
            </div>

            {{-- FILTER STATUS TAGIHAN (KHUSUS UTANG & PIUTANG) --}}
            @if(in_array($jenis, ['utang', 'piutang']))
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 pb-2 border-bottom">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="text-muted fw-semibold me-1" style="font-size: 13px;">
                            <i class="bi bi-funnel me-1"></i> Filter Supplier:
                        </span>
                        <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis, 'status_tagihan' => 'semua', 'search' => request('search')]) }}" 
                           class="filter-tagihan-btn {{ ($statusTagihan ?? 'semua') === 'semua' ? 'active-all' : '' }}">
                            Semua ({{ $summary['total_akun'] ?? 0 }})
                        </a>
                        <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis, 'status_tagihan' => 'belum_lunas', 'search' => request('search')]) }}" 
                           class="filter-tagihan-btn {{ ($statusTagihan ?? 'semua') === 'belum_lunas' ? 'active-pending' : '' }}">
                            <i class="bi bi-exclamation-circle text-danger"></i> Ada Tagihan ({{ $summary['total_pending'] ?? 0 }})
                        </a>
                        @if(($summary['total_overdue'] ?? 0) > 0)
                            <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis, 'status_tagihan' => 'jatuh_tempo', 'search' => request('search')]) }}" 
                               class="filter-tagihan-btn {{ ($statusTagihan ?? 'semua') === 'jatuh_tempo' ? 'active-overdue' : '' }}">
                                <i class="bi bi-clock-history"></i> Lewat Tempo ({{ $summary['total_overdue'] ?? 0 }})
                            </a>
                        @endif
                        <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis, 'status_tagihan' => 'lunas', 'search' => request('search')]) }}" 
                           class="filter-tagihan-btn {{ ($statusTagihan ?? 'semua') === 'lunas' ? 'active-lunas' : '' }}">
                            <i class="bi bi-check-circle text-success"></i> Sudah Lunas ({{ max(0, ($summary['total_akun'] ?? 0) - ($summary['total_pending'] ?? 0)) }})
                        </a>
                    </div>
                    @if(($statusTagihan ?? 'semua') !== 'semua')
                        <div>
                            <span class="badge bg-secondary" style="font-size: 12px;">
                                Menampilkan: {{ $entities->count() }} hasil
                            </span>
                        </div>
                    @endif
                </div>
            @endif

            <!-- Entity Cards -->
            <div class="entity-grid">
                @forelse($entities as $entity)
                    @php
                        $hasTagihan = ($entity->saldo > 0) || ($entity->status !== 'Lunas');
                        $isOverdue = !empty($entity->is_overdue);
                    @endphp
                    <div class="entity-card {{ $isOverdue ? 'has-overdue' : ($hasTagihan ? 'has-tagihan' : '') }}">
                        <span class="entity-code">{{ $entity->kode_akun }}</span>
                        
                        @if($isOverdue)
                            <span class="badge-status badge-overdue">
                                <i class="bi bi-clock-history"></i> Lewat Tempo
                            </span>
                        @elseif($hasTagihan)
                            <span class="badge-status badge-pending">
                                <i class="bi bi-exclamation-circle"></i> Ada Tagihan
                            </span>
                        @else
                            <span class="badge-status badge-lunas">
                                <i class="bi bi-check2"></i> Lunas
                            </span>
                        @endif

                        <div class="entity-name">{{ $entity->nama }}</div>
                        <div class="entity-sub">{{ $entity->keterangan_sub }}</div>

                        {{-- Alert info jika ada tempo atau tagihan aktif --}}
                        @if(!empty($entity->jatuh_tempo_terdekat) && $hasTagihan)
                            <div class="tempo-alert-box">
                                <div class="d-flex align-items-center justify-content-between">
                                    <span><strong>Jatuh Tempo:</strong></span>
                                    <span>{{ \Carbon\Carbon::parse($entity->jatuh_tempo_terdekat)->translatedFormat('d M Y') }}</span>
                                </div>
                                <div class="mt-1" style="font-size: 11px;">
                                    @if($isOverdue)
                                        <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Telah melewati batas waktu pembayaran!</span>
                                    @else
                                        <span class="text-secondary"><i class="bi bi-calendar-event"></i> Sisa {{ \Carbon\Carbon::parse($entity->jatuh_tempo_terdekat)->diffForHumans(null, true) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                        
                        <div style="margin-top:12px;">
                            <span class="saldo-label">Sisa Tagihan / Saldo</span><br>
                            <span class="saldo-value {{ $hasTagihan ? 'danger' : '' }}">
                                Rp {{ number_format($entity->saldo, 0, ',', '.') }}
                            </span>
                            <a href="{{ route('buku-pembantu.show', ['jenis' => $jenis, 'id' => $entity->entity_id]) }}" class="btn-detail">
                                Kartu Utang →
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div style="font-size: 32px; margin-bottom: 8px;">🗂️</div>
                        <h6 class="fw-bold text-dark mb-1">Tidak Ada Data Ditemukan</h6>
                        <p class="text-muted mb-3" style="font-size: 13px;">
                            @if(($statusTagihan ?? 'semua') !== 'semua')
                                Tidak ada entitas dengan filter tagihan <strong>"{{ $statusTagihan }}"</strong>.
                            @else
                                Belum ada data buku pembantu untuk kategori ini.
                            @endif
                        </p>
                        @if(($statusTagihan ?? 'semua') !== 'semua' || !empty($search))
                            <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px;">
                                Reset Semua Filter
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>