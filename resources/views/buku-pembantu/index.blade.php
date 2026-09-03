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

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
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

        .entity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .entity-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
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
            padding: 3px 8px;
            border-radius: 12px;
        }

        .badge-jatuh-tempo { background: #fee2e2; color: #dc2626; }
        .badge-lunas { background: #dcfce7; color: #16a34a; }

        .entity-name { font-size: 16px; font-weight: 700; margin-bottom: 4px; color: #1e293b; }
        .entity-sub { font-size: 12px; color: #64748b; margin-bottom: 16px; }

        .saldo-label { font-size: 12px; color: #64748b; }
        .saldo-value { font-size: 18px; font-weight: 700; color: var(--brand); }

        .btn-detail {
            float: right;
            color: var(--brand);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
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
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'utang', 'search' => request('search')]) }}" class="tab-btn {{ $jenis == 'utang' ? 'active' : '' }}">Utang Usaha</a>
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'piutang', 'search' => request('search')]) }}" class="tab-btn {{ $jenis == 'piutang' ? 'active' : '' }}">Piutang Usaha</a>
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'um-pembelian', 'search' => request('search')]) }}" class="tab-btn {{ $jenis == 'um-pembelian' ? 'active' : '' }}">Uang Muka Pembelian</a>
                    <a href="{{ route('buku-pembantu.index', ['jenis' => 'um-penjualan', 'search' => request('search')]) }}" class="tab-btn {{ $jenis == 'um-penjualan' ? 'active' : '' }}">Uang Muka Penjualan</a>
                </div>

                <!-- Search Input Form -->
                <form action="{{ route('buku-pembantu.index') }}" method="GET" class="d-flex align-items-center gap-2" style="min-width: 260px;">
                    <input type="hidden" name="jenis" value="{{ $jenis }}">
                    <div style="position: relative; width: 100%;">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama / ID {{ in_array($jenis, ['utang', 'um-pembelian']) ? 'supplier' : 'customer' }}..." value="{{ $search ?? request('search') }}" style="border-radius: 8px; border: 1px solid #cbd5e1; padding: 7px 12px 7px 32px; font-size: 13px; width: 100%;">
                        <i class="bi bi-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    </div>
                    <button type="submit" class="btn btn-sm" style="background-color: var(--brand); color: #fff; border-radius: 8px; font-weight: 600; padding: 7px 14px; white-space: nowrap;">Cari</button>
                    @if(!empty($search))
                        <a href="{{ route('buku-pembantu.index', ['jenis' => $jenis]) }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; padding: 7px 12px; white-space: nowrap;">Reset</a>
                    @endif
                </form>
            </div>

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
                    <div class="metric-value">Rp {{ number_format($summary['total_saldo'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">
                        @if(in_array($jenis, ['utang', 'um-pembelian'])) Jumlah Akun / Supplier @else Jumlah Akun / Customer @endif
                    </div>
                    <div class="metric-value dark">{{ $summary['total_akun'] ?? 0 }}</div>
                </div>
                <div class="metric-card">
                    <div class="metric-title">Transaksi Belum Lunas / Aktif</div>
                    <div class="metric-value dark">{{ $summary['total_pending'] ?? 0 }}</div>
                </div>
            </div>

            <!-- Entity Cards -->
            <div class="entity-grid">
                @forelse($entities as $entity)
                    <div class="entity-card">
                        <span class="entity-code">{{ $entity->kode_akun }}</span>
                        
                        <span class="badge-status {{ $entity->status == 'Lunas' ? 'badge-lunas' : 'badge-jatuh-tempo' }}">
                            {{ $entity->status }}
                        </span>

                        <div class="entity-name">{{ $entity->nama }}</div>
                        <div class="entity-sub">{{ $entity->keterangan_sub }}</div>
                        
                        <div style="margin-top:12px;">
                            <span class="saldo-label">Saldo</span><br>
                            <span class="saldo-value">Rp {{ number_format($entity->saldo, 0, ',', '.') }}</span>
                            <a href="{{ route('buku-pembantu.show', ['jenis' => $jenis, 'id' => $entity->entity_id]) }}" class="btn-detail">Detail →</a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        🗂️ Belum ada data buku pembantu untuk kategori ini.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>