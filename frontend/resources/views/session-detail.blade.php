@extends('layouts.app')

@section('title', 'Detail Sesi')
@section('subtitle', $session->title)

@push('styles')
<style>
    .breadcrumb { display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-gray); margin-bottom: 1.1rem; flex-wrap: wrap; }
    .breadcrumb a { color: var(--text-gray); text-decoration: none; }
    .breadcrumb a:hover { color: var(--primary-orange); }

    .score-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.35rem; text-align: center; }
    .score-card.total { background: linear-gradient(135deg, rgba(232,90,32,0.14), transparent); border-color: rgba(232,90,32,0.45); }
    .score-card .label { font-size: 0.8rem; color: var(--text-gray); margin-bottom: 0.4rem; }
    .score-card .value { font-size: 2.3rem; font-weight: 800; line-height: 1; }

    .timeline { position: relative; padding-left: 1.6rem; }
    .timeline::before { content: ''; position: absolute; left: 6px; top: 6px; bottom: 6px; width: 2px; background: var(--border); }
    .timeline-item { position: relative; padding-bottom: 1rem; }
    .timeline-item:last-child { padding-bottom: 0; }
    .timeline-item::before {
        content: ''; position: absolute; left: -1.6rem; top: 5px;
        width: 12px; height: 12px; border-radius: 50%;
        background: var(--text-dim); border: 2px solid var(--bg-card);
    }
    .timeline-item.success::before { background: var(--success-green); }
    .timeline-item.warning::before { background: var(--warning-yellow); }
    .timeline-item.error::before   { background: var(--error-red); }
    .timeline-time { font-size: 0.72rem; color: var(--text-dim); font-family: monospace; }
    .timeline-text { font-size: 0.85rem; margin-top: 0.1rem; }

    .series-chart { display: flex; align-items: flex-end; gap: 2px; height: 150px; }
    .series-bar { flex: 1; min-width: 2px; border-radius: 2px 2px 0 0; background: linear-gradient(to top, var(--primary-orange), #FF8C42); transition: height 0.5s ease; }

    .stat-row { display: flex; justify-content: space-between; padding: 0.55rem 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
    .stat-row:last-child { border-bottom: none; }

    .delta { font-weight: 700; font-size: 1.1rem; }
    .delta.up { color: var(--success-green); }
    .delta.down { color: var(--error-red); }
    .delta.flat { color: var(--text-gray); }
</style>
@endpush

@section('content')
<div class="container-wide">

    <nav class="breadcrumb">
        <a href="{{ route('dashboard') }}">Dashboard</a> <span>/</span>
        <a href="{{ route('history') }}">Riwayat</a> <span>/</span>
        <span style="color: var(--text-white);">Detail Sesi</span>
    </nav>

    {{-- ============ HEADER ============ --}}
    <div class="panel mb-3">
        <div class="row-between">
            <div class="row">
                <div class="list-icon" style="width:56px;height:56px;font-size:1.6rem;">
                    {{ $session->karakter_icon }}
                </div>
                <div>
                    <h1 style="font-size:1.4rem;font-weight:700;">{{ $session->title }}</h1>
                    <div class="list-meta">
                        📅 {{ $session->created_at?->translatedFormat('d F Y, H:i') }}
                        · ⏳ {{ $session->duration_for_humans }}
                        · 🎯 akurasi {{ $session->accuracy }}%
                        @if ($session->best_streak > 0)
                            · 🔥 streak {{ $session->best_streak }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="row">
                <a href="{{ route('practice', ['karakter' => $session->karakter, 'gerakan' => $session->gerakan]) }}"
                   class="btn btn-primary">🔄 Ulangi Latihan</a>
                <a href="{{ route('history') }}" class="btn btn-secondary">← Kembali</a>
            </div>
        </div>
    </div>

    {{-- ============ SCORES ============ --}}
    <div class="grid grid-stats mb-3">
        <div class="score-card">
            <div class="label">Wiraga (Gerak)</div>
            <div class="value text-success">{{ round($session->wiraga_score) }}</div>
            <div class="progress-bar thin mt-2">
                <div class="progress-fill wiraga" style="width:0%;" data-width="{{ $session->wiraga_score }}"></div>
            </div>
        </div>
        <div class="score-card">
            <div class="label">Wirama (Irama)</div>
            <div class="value" style="color: var(--info-blue);">{{ round($session->wirama_score) }}</div>
            <div class="progress-bar thin mt-2">
                <div class="progress-fill wirama" style="width:0%;" data-width="{{ $session->wirama_score }}"></div>
            </div>
        </div>
        <div class="score-card">
            <div class="label">Wirasa (Ekspresi)</div>
            <div class="value" style="color: var(--purple);">{{ round($session->wirasa_score) }}</div>
            <div class="progress-bar thin mt-2">
                <div class="progress-fill wirasa" style="width:0%;" data-width="{{ $session->wirasa_score }}"></div>
            </div>
        </div>
        <div class="score-card total">
            <div class="label">Skor Total</div>
            <div class="value text-orange">{{ round($session->total_score) }}</div>
            <span class="badge badge-orange mt-2">Grade {{ $session->resolved_grade }}</span>
        </div>
    </div>

    <div class="grid grid-main">

        {{-- ============ LEFT COLUMN ============ --}}
        <div class="stack-lg">

            {{-- Performance chart --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">📈 Grafik Performa Sesi</h3>
                    @if (count($session->score_series ?? []))
                        <span class="badge badge-soft">{{ count($session->score_series) }} sampel</span>
                    @endif
                </div>

                @if (count($session->score_series ?? []))
                    @php $series = collect($session->score_series); @endphp
                    <div class="series-chart">
                        @foreach ($series as $point)
                            @php
                                $total = ($point['wiraga'] ?? 0) * 0.45
                                       + ($point['wirama'] ?? 0) * 0.30
                                       + ($point['wirasa'] ?? 0) * 0.25;
                            @endphp
                            <div class="series-bar" style="height:0%;"
                                 data-height="{{ max(2, round($total)) }}"
                                 title="{{ $point['t'] ?? 0 }}s — {{ round($total) }}"></div>
                        @endforeach
                    </div>
                    <div class="row-between mt-2 fs-xs muted">
                        <span>0:00</span>
                        <span>{{ $session->duration_for_humans }}</span>
                    </div>
                @else
                    <div class="empty-state" style="padding:2rem 1rem;">
                        <div class="icon">📉</div>
                        <p class="fs-sm">
                            Grafik per-detik tersedia untuk sesi yang direkam dengan
                            server AI aktif.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Comparison with previous session --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">📊 Perbandingan dengan Sesi Sebelumnya</h3>
                    @if ($previousSession)
                        <a href="{{ route('history.show', $previousSession->id) }}" class="panel-link">
                            Lihat sesi sebelumnya →
                        </a>
                    @endif
                </div>

                @if ($comparison)
                    <div class="grid grid-stats" style="gap:0.75rem;">
                        @foreach ($comparison as $item)
                            <div class="text-center" style="padding:0.85rem; background:rgba(255,255,255,0.03); border-radius:var(--radius-sm);">
                                <div class="list-meta mb-1">{{ $item['label'] }}</div>
                                <div class="delta {{ $item['delta'] > 0.05 ? 'up' : ($item['delta'] < -0.05 ? 'down' : 'flat') }}">
                                    {{ $item['delta'] > 0 ? '+' : '' }}{{ $item['delta'] }}
                                </div>
                                <div class="fs-xs dim">
                                    {{ $item['previous'] }} → {{ $item['current'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted fs-sm">
                        Ini sesi pertama Anda untuk karakter
                        <strong>{{ $session->karakter_name }}</strong>.
                        Selesaikan sesi berikutnya untuk melihat perbandingan.
                    </p>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">⏱️ Timeline Feedback</h3>
                </div>

                @if (count($session->timeline ?? []))
                    <div class="timeline">
                        @foreach ($session->timeline as $item)
                            <div class="timeline-item {{ $item['severity'] ?? 'info' }}">
                                <div class="timeline-time">
                                    {{ sprintf('%02d:%02d', intdiv((int) ($item['at'] ?? 0), 60), (int) ($item['at'] ?? 0) % 60) }}
                                    <span class="badge badge-soft" style="margin-left:0.35rem;">
                                        {{ strtoupper($item['type'] ?? '') }}
                                    </span>
                                </div>
                                <div class="timeline-text">{{ $item['message'] ?? '' }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="muted fs-sm">
                        Timeline detik-per-detik direkam saat berlatih dengan server AI aktif.
                    </p>
                @endif
            </div>
        </div>

        {{-- ============ RIGHT COLUMN ============ --}}
        <div class="stack-lg">

            {{-- AI feedback --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">🤖 Feedback AI</h3>
                </div>
                @forelse ($session->feedback ?? [] as $item)
                    @php $text = is_array($item) ? ($item['message'] ?? '') : $item; @endphp
                    <div class="list-item mb-1" style="align-items:flex-start;">
                        <span style="font-size:1rem;">💡</span>
                        <div class="fs-sm" style="line-height:1.55;">{{ $text }}</div>
                    </div>
                @empty
                    <p class="muted fs-sm">Tidak ada catatan feedback pada sesi ini.</p>
                @endforelse
            </div>

            {{-- Session stats --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">📋 Statistik Sesi</h3>
                </div>
                <div class="stat-row">
                    <span class="muted">Frame Dianalisis</span>
                    <span class="fw-600">{{ number_format($session->frames_analyzed) }}</span>
                </div>
                <div class="stat-row">
                    <span class="muted">Frame Sesuai Pakem</span>
                    <span class="fw-600">{{ number_format($session->correct_frames) }}</span>
                </div>
                <div class="stat-row">
                    <span class="muted">Akurasi Pose</span>
                    <span class="fw-600">{{ $session->accuracy }}%</span>
                </div>
                <div class="stat-row">
                    <span class="muted">Streak Terbaik</span>
                    <span class="fw-600">{{ $session->best_streak }} frame</span>
                </div>
                <div class="stat-row">
                    <span class="muted">Durasi</span>
                    <span class="fw-600">{{ $session->duration_for_humans }}</span>
                </div>
                <div class="stat-row">
                    <span class="muted">Poin XP</span>
                    <span class="fw-600 text-orange">+{{ round($session->total_score) }}</span>
                </div>
            </div>

            {{-- Per-joint breakdown --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">🦴 Analisis Per Sendi</h3>
                </div>
                @forelse ($jointScores as $joint)
                    <div class="mb-2">
                        <div class="row-between fs-sm" style="margin-bottom:0.28rem;">
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
                        Rincian 12 sudut sendi direkam saat berlatih dengan server AI aktif.
                    </p>
                @endforelse
            </div>

            {{-- Character info --}}
            @if (!empty($karakterMeta))
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="section-title">{{ $karakterMeta['icon'] }} Tentang {{ $karakterMeta['name'] }}</h3>
                    </div>
                    <p class="fs-sm muted" style="line-height:1.65;">{{ $karakterMeta['filosofi'] }}</p>
                    <div class="row mt-2">
                        <span class="badge badge-soft">Tempo {{ $karakterMeta['tempo'][0] }}–{{ $karakterMeta['tempo'][1] }} BPM</span>
                        <span class="badge badge-soft">{{ $karakterMeta['difficulty'] }}</span>
                    </div>
                    <a href="{{ route('tutorial', ['karakter' => $session->karakter]) }}"
                       class="btn btn-secondary btn-sm btn-block mt-2">📚 Buka Tutorial</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
