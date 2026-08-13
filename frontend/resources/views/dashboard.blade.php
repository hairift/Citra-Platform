@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Ringkasan perkembangan latihan Anda')

@section('content')
<div class="container-wide">

    <div class="page-header">
        <h1>Selamat datang, {{ $user->name }}! 👋</h1>
        <p>
            @if ($summary['practice_count'] === 0)
                Mulai sesi latihan pertama Anda untuk melihat perkembangan di sini.
            @elseif ($summary['current_streak'] > 1)
                Anda sudah berlatih {{ $summary['current_streak'] }} hari berturut-turut. Pertahankan! 🔥
            @else
                Lanjutkan perjalanan belajar Tari Topeng Cirebon Anda.
            @endif
        </p>
    </div>

    {{-- ============ STATS ============ --}}
    <div class="grid grid-stats mb-3">
        <div class="stat-card">
            <div class="stat-icon orange">🎯</div>
            <div class="stat-value">{{ number_format($summary['total_score']) }}</div>
            <div class="stat-label">Total Skor</div>
            <div class="stat-sub">Peringkat #{{ $summary['rank'] }} global</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">📈</div>
            <div class="stat-value">{{ $summary['practice_count'] }}</div>
            <div class="stat-label">Sesi Latihan</div>
            <div class="stat-sub">
                @if ($summary['avg_score'] > 0)
                    Rata-rata {{ $summary['avg_score'] }}
                @else
                    Belum ada sesi
                @endif
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon blue">⏱️</div>
            <div class="stat-value">{{ $summary['total_minutes'] }}<span class="fs-sm muted">m</span></div>
            <div class="stat-label">Waktu Latihan</div>
            <div class="stat-sub">Skor terbaik {{ $summary['best_score'] }}</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">🏆</div>
            <div class="stat-value fs-lg" style="font-size: 1.5rem;">{{ $summary['level'] }}</div>
            <div class="stat-label">Level Saat Ini</div>
            <div class="stat-sub">
                🔥 {{ $summary['current_streak'] }} hari beruntun
                (rekor {{ $summary['longest_streak'] }})
            </div>
        </div>
    </div>

    {{-- ============ QUICK ACTIONS ============ --}}
    <div class="grid grid-3 mb-3">
        @foreach ($quickActions as $action)
            <a href="{{ route($action['route']) }}" class="list-item" style="padding: 1.1rem;">
                <div class="list-icon" style="width:52px;height:52px;font-size:1.45rem;background: {{ $action['color'] }}22;">
                    {{ $action['icon'] }}
                </div>
                <div class="list-body">
                    <div class="fw-600" style="font-size:0.95rem;">{{ $action['title'] }}</div>
                    <div class="list-meta">{{ $action['desc'] }}</div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- ============ MAIN GRID ============ --}}
    <div class="grid grid-main mb-3">

        {{-- Weekly progress chart --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="section-title">📊 Progres 7 Hari Terakhir</h3>
                <a href="{{ route('history') }}" class="panel-link">Lihat riwayat →</a>
            </div>

            @if (collect($weeklyProgress)->sum('sessions') === 0)
                <div class="empty-state">
                    <div class="icon">📉</div>
                    <h4>Belum ada data minggu ini</h4>
                    <p>Selesaikan sesi latihan untuk melihat grafik perkembangan skor harian Anda.</p>
                    <a href="{{ route('practice') }}" class="btn btn-primary">🎭 Mulai Latihan</a>
                </div>
            @else
                <div class="bar-chart">
                    @foreach ($weeklyProgress as $day)
                        @php $pct = $chartMax > 0 ? round(100 * $day['score'] / $chartMax) : 0; @endphp
                        <div class="bar-col" title="{{ $day['date'] }} — {{ $day['sessions'] }} sesi, rata-rata {{ $day['score'] }}">
                            <div class="bar-track">
                                <div class="bar-value {{ $day['is_today'] ? 'today' : '' }} {{ $day['score'] == 0 ? 'zero' : '' }}"
                                     style="height: 0%;" data-height="{{ max($pct, $day['score'] > 0 ? 4 : 2) }}"></div>
                            </div>
                            <div class="bar-score">{{ $day['score'] > 0 ? $day['score'] : '—' }}</div>
                            <div class="bar-label">{{ $day['day'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="row-between mt-2 fs-xs muted">
                    <span>{{ collect($weeklyProgress)->sum('sessions') }} sesi minggu ini</span>
                    <span>{{ collect($weeklyProgress)->sum('minutes') }} menit total</span>
                </div>
            @endif
        </div>

        {{-- Character mastery --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="section-title">🎭 Penguasaan Karakter</h3>
            </div>
            <div class="stack">
                @foreach ($characterMastery as $char)
                    <a href="{{ route('practice', ['karakter' => $char['slug']]) }}" class="list-item" style="padding:0.7rem;">
                        <div class="list-icon" style="background: {{ $char['color'] }}22;">{{ $char['icon'] }}</div>
                        <div class="list-body">
                            <div class="row-between" style="margin-bottom:0.3rem;">
                                <span class="list-title">{{ $char['name'] }}</span>
                                <span class="fw-700 fs-sm" style="color: {{ $char['color'] }};">
                                    {{ $char['mastery'] }}%
                                </span>
                            </div>
                            <div class="progress-bar thin">
                                <div class="progress-fill" style="width:0%; background: {{ $char['color'] }};"
                                     data-width="{{ $char['mastery'] }}"></div>
                            </div>
                            <div class="list-meta mt-1">
                                {{ $char['level'] }} · {{ $char['sessions'] }} sesi ·
                                {{ $char['gerakan_done'] }}/{{ $char['gerakan_total'] }} gerakan
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ============ BOTTOM GRID ============ --}}
    <div class="grid grid-main">

        {{-- Recent sessions --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="section-title">🕐 Sesi Latihan Terakhir</h3>
                <a href="{{ route('history') }}" class="panel-link">Lihat semua →</a>
            </div>

            @forelse ($recentSessions as $session)
                <a href="{{ route('history.show', $session->id) }}" class="list-item mb-1">
                    <div class="list-icon">{{ $session->karakter_icon }}</div>
                    <div class="list-body">
                        <div class="list-title">{{ $session->title }}</div>
                        <div class="list-meta">
                            {{ $session->created_at?->diffForHumans() }} ·
                            {{ $session->duration_for_humans }} ·
                            akurasi {{ $session->accuracy }}%
                        </div>
                    </div>
                    <span class="score-badge score-{{ $session->score_class }}">
                        {{ round($session->total_score) }}
                    </span>
                </a>
            @empty
                <div class="empty-state">
                    <div class="icon">🎭</div>
                    <h4>Belum ada sesi latihan</h4>
                    <p>Sesi latihan Anda akan muncul di sini setelah Anda menyelesaikan latihan pertama.</p>
                    <a href="{{ route('practice') }}" class="btn btn-primary">Mulai Sekarang</a>
                </div>
            @endforelse
        </div>

        <div class="stack-lg">
            {{-- Improvement areas --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">📈 Area Perbaikan</h3>
                </div>
                @forelse ($weakestJoints as $joint)
                    <div class="mb-2">
                        <div class="row-between fs-sm" style="margin-bottom:0.3rem;">
                            <span>{{ $joint['label'] }}</span>
                            <span class="fw-600 {{ $joint['score'] >= 75 ? 'text-success' : ($joint['score'] >= 55 ? 'text-warning' : 'text-error') }}">
                                {{ $joint['score'] }}%
                            </span>
                        </div>
                        <div class="progress-bar thin">
                            <div class="progress-fill" style="width:0%;" data-width="{{ $joint['score'] }}"></div>
                        </div>
                    </div>
                @empty
                    <p class="muted fs-sm">
                        Analisis per-sendi akan muncul setelah Anda berlatih dengan
                        server AI aktif.
                    </p>
                @endforelse
            </div>

            {{-- Achievements --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">🏅 Pencapaian Terbaru</h3>
                    <a href="{{ route('profile') }}" class="panel-link">Semua →</a>
                </div>
                @if (count($unlockedAchievements))
                    <div class="row">
                        @foreach ($unlockedAchievements as $a)
                            <div class="text-center" style="width:76px;" title="{{ $a['description'] }}">
                                <div style="font-size:1.7rem;">{{ $a['icon'] }}</div>
                                <div class="fs-xs muted" style="line-height:1.25;">{{ $a['name'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted fs-sm">
                        Belum ada pencapaian. Selesaikan sesi latihan pertama untuk
                        membuka lencana <strong>Langkah Pertama</strong> 🌟
                    </p>
                @endif
            </div>

            {{-- Featured maestro video --}}
            @if ($featured && $featured->video_url)
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="section-title">🎬 Referensi Maestro</h3>
                    </div>
                    <video
                        src="{{ $featured->video_url }}"
                        @if ($featured->poster_url) poster="{{ $featured->poster_url }}" @endif
                        controls preload="metadata"
                        style="width:100%; border-radius:var(--radius-sm); background:#000; aspect-ratio:16/9;">
                    </video>
                    <div class="fw-600 fs-sm mt-2">{{ $featured->gerakan_name }}</div>
                    <div class="list-meta">
                        {{ $featured->duration_for_humans }} ·
                        {{ number_format($featured->frame_count) }} frame terekstrak
                    </div>
                    <a href="{{ route('tutorial', ['karakter' => $featured->karakter]) }}"
                       class="btn btn-secondary btn-sm btn-block mt-2">Buka Tutorial</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
