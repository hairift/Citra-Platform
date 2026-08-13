@extends('layouts.app')

@section('title', 'Riwayat Latihan')
@section('subtitle', 'Semua sesi latihan yang pernah Anda selesaikan')

@push('styles')
<style>
    .history-row { grid-template-columns: minmax(0, 2.2fr) repeat(4, 0.75fr) 0.9fr 90px; }
    @media (max-width: 1000px) {
        .history-row { grid-template-columns: minmax(0, 1fr); gap: 0.4rem; }
        .history-row.header { display: none; }
        .history-row > div:not(.session-cell) { font-size: 0.82rem; }
    }
    .session-cell { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
</style>
@endpush

@section('content')
<div class="container-wide">

    <div class="page-header">
        <h1>📋 Riwayat Latihan</h1>
        <p>Tinjau perkembangan setiap sesi dan pelajari catatan dari AI.</p>
    </div>

    {{-- ============ FILTERS ============ --}}
    <form method="GET" action="{{ route('history') }}" class="panel mb-3">
        <div class="row" style="gap: 1rem;">
            <div style="flex:1; min-width:170px;">
                <label class="form-label" for="filter-karakter">Karakter</label>
                <select name="karakter" id="filter-karakter" class="form-select" data-auto-submit>
                    <option value="">Semua Karakter</option>
                    @foreach ($karakters as $slug => $meta)
                        <option value="{{ $slug }}" @selected($selectedKarakter === $slug)>
                            {{ $meta['icon'] }} {{ $meta['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="flex:1; min-width:170px;">
                <label class="form-label" for="filter-period">Periode</label>
                <select name="period" id="filter-period" class="form-select" data-auto-submit>
                    <option value="">Semua Waktu</option>
                    <option value="today" @selected($selectedPeriod === 'today')>Hari Ini</option>
                    <option value="week"  @selected($selectedPeriod === 'week')>Minggu Ini</option>
                    <option value="month" @selected($selectedPeriod === 'month')>Bulan Ini</option>
                </select>
            </div>

            <div style="flex:1; min-width:170px;">
                <label class="form-label" for="filter-sort">Urutkan</label>
                <select name="sort" id="filter-sort" class="form-select" data-auto-submit>
                    <option value="newest" @selected($sort === 'newest')>Terbaru</option>
                    <option value="oldest" @selected($sort === 'oldest')>Terlama</option>
                    <option value="best"   @selected($sort === 'best')>Skor Tertinggi</option>
                    <option value="worst"  @selected($sort === 'worst')>Skor Terendah</option>
                </select>
            </div>

            @if ($selectedKarakter || $selectedPeriod || $sort !== 'newest')
                <div style="align-self:flex-end;">
                    <a href="{{ route('history') }}" class="btn btn-ghost">✕ Reset</a>
                </div>
            @endif
        </div>
        <noscript>
            <button type="submit" class="btn btn-primary btn-sm mt-2">Terapkan Filter</button>
        </noscript>
    </form>

    {{-- ============ SUMMARY (reflects the active filter) ============ --}}
    <div class="grid grid-stats mb-3">
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ $summary['total_sessions'] }}</div>
            <div class="stat-label">Total Sesi</div>
        </div>
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ $summary['avg_score'] }}</div>
            <div class="stat-label">Rata-rata Skor</div>
        </div>
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ $summary['total_minutes'] }}m</div>
            <div class="stat-label">Total Waktu</div>
        </div>
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ $summary['best_score'] }}</div>
            <div class="stat-label">Skor Tertinggi</div>
        </div>
    </div>

    {{-- ============ TABLE ============ --}}
    @if ($sessions->count())
        <div class="data-table">
            <div class="data-row header history-row">
                <div>Sesi Latihan</div>
                <div>Wiraga</div>
                <div>Wirama</div>
                <div>Wirasa</div>
                <div>Total</div>
                <div>Durasi</div>
                <div></div>
            </div>

            @foreach ($sessions as $session)
                <div class="data-row history-row">
                    <div class="session-cell">
                        <div class="list-icon">{{ $session->karakter_icon }}</div>
                        <div style="min-width:0;">
                            <div class="list-title truncate">{{ $session->title }}</div>
                            <div class="list-meta">
                                {{ $session->created_at?->translatedFormat('d M Y, H:i') }}
                                · {{ $session->created_at?->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    <div><span class="mobile-label">Wiraga:</span>{{ round($session->wiraga_score) }}%</div>
                    <div><span class="mobile-label">Wirama:</span>{{ round($session->wirama_score) }}%</div>
                    <div><span class="mobile-label">Wirasa:</span>{{ round($session->wirasa_score) }}%</div>
                    <div>
                        <span class="mobile-label">Total:</span>
                        <span class="score-badge score-{{ $session->score_class }}">
                            {{ round($session->total_score) }} · {{ $session->resolved_grade }}
                        </span>
                    </div>
                    <div><span class="mobile-label">Durasi:</span>{{ $session->duration_for_humans }}</div>
                    <div>
                        <a href="{{ route('history.show', $session->id) }}" class="btn btn-secondary btn-sm">Detail</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="pagination-wrap">
            {{ $sessions->links() }}
        </div>

        <p class="text-center muted fs-xs mt-2">
            Menampilkan {{ $sessions->firstItem() }}–{{ $sessions->lastItem() }}
            dari {{ $sessions->total() }} sesi
        </p>
    @else
        <div class="panel">
            <div class="empty-state">
                <div class="icon">📭</div>
                <h4>
                    @if ($selectedKarakter || $selectedPeriod)
                        Tidak ada sesi yang cocok dengan filter
                    @else
                        Belum ada riwayat latihan
                    @endif
                </h4>
                <p>
                    @if ($selectedKarakter || $selectedPeriod)
                        Coba ubah atau hapus filter untuk melihat sesi lainnya.
                    @else
                        Setiap sesi latihan yang Anda selesaikan akan tercatat di sini
                        lengkap dengan skor Wiraga, Wirama, dan Wirasa.
                    @endif
                </p>
                @if ($selectedKarakter || $selectedPeriod)
                    <a href="{{ route('history') }}" class="btn btn-secondary">Hapus Filter</a>
                @else
                    <a href="{{ route('practice') }}" class="btn btn-primary">🎭 Mulai Latihan</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
