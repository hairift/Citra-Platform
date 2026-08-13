@extends('layouts.app')

@section('title', 'Leaderboard')
@section('subtitle', 'Peringkat penari terbaik platform CITRA')

@push('styles')
<style>
    .podium { display: grid; grid-template-columns: 1fr 1.15fr 1fr; gap: 1rem; align-items: end; }
    .podium-item {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.35rem 1rem;
        text-align: center;
        position: relative;
    }
    .podium-item.first { padding: 2rem 1rem 1.6rem; border-color: var(--gold); box-shadow: 0 0 34px rgba(255,215,0,0.16); }
    .podium-item.second { border-color: var(--silver); }
    .podium-item.third  { border-color: var(--bronze); }

    .rank-badge {
        position: absolute; top: -15px; left: 50%;
        transform: translateX(-50%);
        width: 34px; height: 34px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1rem;
    }
    .rank-badge.gold   { background: var(--gold);   color: #000; }
    .rank-badge.silver { background: var(--silver); color: #000; }
    .rank-badge.bronze { background: var(--bronze); color: #fff; }

    .podium-avatar { margin: 0.9rem auto 0.7rem; }
    .podium-item.first .podium-avatar { width: 88px; height: 88px; font-size: 2.2rem; }

    .lb-row { grid-template-columns: 58px minmax(0, 1fr) 110px 100px; }
    .lb-row.me { background: rgba(232, 90, 32, 0.09); border-left: 3px solid var(--primary-orange); }

    @media (max-width: 768px) {
        .podium { grid-template-columns: 1fr; }
        .podium-item.first { order: -1; }
        .lb-row { grid-template-columns: 44px minmax(0, 1fr) 80px; }
        .lb-karakter { display: none; }
    }
</style>
@endpush

@section('content')
<div class="container-narrow">

    <div class="page-header text-center">
        <h1>🏆 Leaderboard</h1>
        <p>Bandingkan capaian Anda dengan penari lain di platform CITRA.</p>
    </div>

    {{-- ============ FILTERS ============ --}}
    <div class="filter-tabs mb-2" style="justify-content:center;">
        @foreach ($karakters as $slug => $meta)
            <a href="{{ route('leaderboard', ['karakter' => $slug, 'period' => $selectedPeriod]) }}"
               class="filter-tab {{ $selectedKarakter === $slug ? 'active' : '' }}">
                <span>{{ $meta['icon'] }}</span> {{ $meta['name'] }}
            </a>
        @endforeach
    </div>

    <div class="filter-tabs mb-3" style="justify-content:center;">
        @foreach ($periods as $key => $label)
            <a href="{{ route('leaderboard', ['karakter' => $selectedKarakter, 'period' => $key]) }}"
               class="filter-tab {{ $selectedPeriod === $key ? 'active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- ============ MY RANK ============ --}}
    <div class="panel mb-3" style="background: linear-gradient(135deg, rgba(232,90,32,0.12), transparent);">
        <div class="row-between">
            <div class="row">
                <div class="avatar avatar-md">
                    @if (Auth::user()->avatar_url)
                        <img src="{{ Auth::user()->avatar_url }}" alt="{{ Auth::user()->name }}">
                    @else
                        {{ Auth::user()->initial }}
                    @endif
                </div>
                <div>
                    <div class="fw-600">Peringkat Anda</div>
                    <div class="list-meta">
                        {{ Auth::user()->name }} · {{ Auth::user()->level }}
                    </div>
                </div>
            </div>
            <div class="text-right">
                @if ($myRank)
                    <div class="stat-value text-orange">#{{ $myRank }}</div>
                    @if ($myRow)
                        <div class="list-meta">{{ number_format($myRow['score'], 1) }} poin</div>
                    @endif
                @else
                    <div class="muted fs-sm" style="max-width:220px;">
                        Belum ada skor untuk kategori ini.
                        <a href="{{ route('practice') }}" class="text-orange">Mulai latihan →</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="panel">
            <div class="empty-state">
                <div class="icon">🏆</div>
                <h4>Papan peringkat masih kosong</h4>
                <p>Belum ada sesi latihan yang tercatat untuk kategori ini. Jadilah yang pertama!</p>
                <a href="{{ route('practice') }}" class="btn btn-primary">🎭 Mulai Latihan</a>
            </div>
        </div>
    @else
        {{-- ============ PODIUM ============ --}}
        @if ($topThree->count() >= 3)
            @php
                $ordered = [$topThree[1], $topThree[0], $topThree[2]];
                $classes = ['second', 'first', 'third'];
                $badges  = ['silver', 'gold', 'bronze'];
            @endphp
            <div class="podium mb-3">
                @foreach ($ordered as $i => $entry)
                    <div class="podium-item {{ $classes[$i] }}">
                        <span class="rank-badge {{ $badges[$i] }}">{{ $entry['rank'] }}</span>
                        <div class="avatar avatar-md podium-avatar">
                            @if ($entry['avatar'])
                                <img src="{{ $entry['avatar'] }}" alt="{{ $entry['name'] }}">
                            @else
                                {{ $entry['initial'] }}
                            @endif
                        </div>
                        <div class="fw-600 truncate">{{ $entry['name'] }}</div>
                        <div class="list-meta mb-1">{{ $entry['level'] }}</div>
                        <div class="fw-800 text-orange" style="font-size:{{ $i === 1 ? '1.75rem' : '1.35rem' }};">
                            {{ number_format($entry['score'], 1) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ============ FULL TABLE ============ --}}
        <div class="data-table">
            <div class="data-row header lb-row">
                <div>Rank</div>
                <div>Pengguna</div>
                <div class="lb-karakter">Kategori</div>
                <div class="text-right">Skor</div>
            </div>

            @foreach (($topThree->count() >= 3 ? $rest : $rows) as $entry)
                <div class="data-row lb-row {{ $entry['user_id'] === Auth::id() ? 'me' : '' }}">
                    <div class="fw-700" style="font-size:1rem;">#{{ $entry['rank'] }}</div>
                    <div class="row" style="gap:0.7rem; min-width:0;">
                        <div class="avatar avatar-sm">
                            @if ($entry['avatar'])
                                <img src="{{ $entry['avatar'] }}" alt="{{ $entry['name'] }}">
                            @else
                                {{ $entry['initial'] }}
                            @endif
                        </div>
                        <div style="min-width:0;">
                            <div class="list-title truncate">
                                {{ $entry['name'] }}
                                @if ($entry['user_id'] === Auth::id())
                                    <span class="badge badge-orange" style="margin-left:0.3rem;">Anda</span>
                                @endif
                            </div>
                            <div class="list-meta">{{ $entry['level'] }} · {{ $entry['sessions'] }} sesi</div>
                        </div>
                    </div>
                    <div class="lb-karakter muted fs-sm">{{ $entry['karakter'] }}</div>
                    <div class="text-right fw-700 text-orange">{{ number_format($entry['score'], 1) }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
