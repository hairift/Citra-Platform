@extends('layouts.app')

@section('title', 'Tutorial')
@section('subtitle', 'Pelajari gerakan Tari Topeng dari rekaman maestro')

@push('styles')
<style>
    .karakter-tabs { display: flex; gap: 0.85rem; flex-wrap: wrap; justify-content: center; margin-bottom: 2rem; }
    .karakter-tab {
        display: flex; flex-direction: column; align-items: center;
        padding: 1rem 1.35rem;
        background: var(--bg-card);
        border: 2px solid transparent;
        border-radius: var(--radius);
        cursor: pointer; transition: all 0.25s ease;
        min-width: 118px; text-decoration: none; color: inherit;
    }
    .karakter-tab:hover { background: var(--bg-card-hover); transform: translateY(-3px); }
    .karakter-tab.active { border-color: var(--primary-orange); background: rgba(232,90,32,0.1); }
    .karakter-tab .ico { font-size: 2.1rem; margin-bottom: 0.35rem; }
    .karakter-tab .nm { font-weight: 600; font-size: 0.92rem; }
    .karakter-tab .lv { font-size: 0.7rem; color: var(--text-gray); }

    .gerakan-item {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.75rem;
        background: rgba(255,255,255,0.03);
        border-radius: var(--radius-sm);
        cursor: pointer; transition: all 0.25s ease;
        border: 2px solid transparent;
        text-decoration: none; color: inherit;
        width: 100%; text-align: left; font-family: inherit;
    }
    .gerakan-item:hover { background: rgba(255,255,255,0.06); }
    .gerakan-item.active { border-color: var(--primary-orange); background: rgba(232,90,32,0.1); }
    .gerakan-item .num {
        width: 28px; height: 28px; flex-shrink: 0;
        background: var(--bg-dark); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.78rem; font-weight: 600;
    }
    .gerakan-item.done .num { background: var(--success-green); color: #fff; }
    .gerakan-item .info h4 { font-size: 0.85rem; font-weight: 500; }
    .gerakan-item .info p { font-size: 0.7rem; color: var(--text-gray); }

    .video-frame {
        position: relative; background: #000;
        border-radius: var(--radius-sm); overflow: hidden;
        aspect-ratio: 16/9;
    }
    .video-frame video { width: 100%; height: 100%; object-fit: contain; display: block; }
    .video-placeholder {
        width: 100%; height: 100%;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 0.6rem;
        background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-hover) 100%);
        text-align: center; padding: 1.5rem;
    }
    .video-placeholder .ico { font-size: 3rem; opacity: 0.45; }

    .frame-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; }
    .frame-thumb {
        position: relative; border-radius: var(--radius-sm);
        overflow: hidden; background: #000; cursor: zoom-in;
        border: 1px solid var(--border); aspect-ratio: 16/9;
    }
    .frame-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s ease; }
    .frame-thumb:hover img { transform: scale(1.06); }

    .lightbox {
        position: fixed; inset: 0; z-index: 2000;
        background: rgba(0,0,0,0.92);
        display: none; align-items: center; justify-content: center;
        padding: 2rem; cursor: zoom-out;
    }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 100%; max-height: 100%; border-radius: var(--radius-sm); }

    .content-grid { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 1.5rem; }
    @media (max-width: 900px) { .content-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('content')
<div class="container-wide">

    <div class="page-header text-center">
        <h1>📚 Tutorial Tari Topeng Cirebon</h1>
        <p>Pelajari gerakan dasar hingga mahir dari lima karakter topeng.</p>
    </div>

    {{-- ============ CHARACTER TABS ============ --}}
    <div class="karakter-tabs">
        @foreach ($karakters as $slug => $meta)
            <a href="{{ route('tutorial', ['karakter' => $slug]) }}"
               class="karakter-tab {{ $selectedKarakter === $slug ? 'active' : '' }}">
                <span class="ico">{{ $meta['icon'] }}</span>
                <span class="nm">{{ $meta['name'] }}</span>
                <span class="lv">{{ count($meta['gerakan']) }} gerakan</span>
            </a>
        @endforeach
    </div>

    @php $karakter = $karakters[$selectedKarakter]; @endphp

    {{-- ============ OVERVIEW ============ --}}
    <div class="panel mb-3" style="background: linear-gradient(135deg, {{ $karakter['color'] }}18, transparent);">
        <div class="row-between">
            <div style="max-width: 620px;">
                <h2 style="font-size:1.25rem;font-weight:700;margin-bottom:0.4rem;">
                    {{ $karakter['icon'] }} {{ $karakter['name'] }}
                </h2>
                <p class="muted fs-sm" style="line-height:1.7;">{{ $karakter['filosofi'] }}</p>
                <div class="row mt-2">
                    <span class="badge badge-soft">Tingkat: {{ $karakter['difficulty'] }}</span>
                    <span class="badge badge-soft">Tempo {{ $karakter['tempo'][0] }}–{{ $karakter['tempo'][1] }} BPM</span>
                    <span class="badge badge-soft">Ekspresi: {{ implode(', ', $karakter['ekspresi']) }}</span>
                </div>
            </div>
            <div class="text-center">
                <div class="stat-value text-orange">{{ $overview['percent'] }}%</div>
                <div class="stat-label">{{ $overview['completed'] }}/{{ $overview['total'] }} gerakan dikuasai</div>
                <div class="progress-bar thin mt-2" style="width:160px;">
                    <div class="progress-fill" style="width:0%;" data-width="{{ $overview['percent'] }}"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-grid">

        {{-- ============ GERAKAN LIST ============ --}}
        <div class="panel" style="height:fit-content; position:sticky; top:5rem;">
            <div class="panel-header">
                <h3 class="section-title">📝 Daftar Gerakan</h3>
            </div>
            <div class="stack">
                @foreach ($gerakanList as $g)
                    <a href="{{ route('tutorial.show', ['karakter' => $selectedKarakter, 'gerakan' => $g['slug']]) }}"
                       class="gerakan-item {{ $g['completed'] ? 'done' : '' }}">
                        <span class="num">{{ $g['completed'] ? '✓' : $g['index'] }}</span>
                        <div class="info flex-1" style="min-width:0;">
                            <h4 class="truncate">{{ $g['name'] }}</h4>
                            <p>{{ $g['desc'] }} · {{ $g['hitungan'] }} hitungan</p>
                        </div>
                        @if ($g['has_video'])
                            <span title="Video tersedia">🎬</span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="row-between mt-3 fs-xs muted">
                <span>{{ $overview['videos'] }} video</span>
                <span>{{ number_format($overview['dataset_frames']) }} frame dataset</span>
            </div>
        </div>

        {{-- ============ MAIN CONTENT ============ --}}
        <div class="stack-lg">

            {{-- Full performance videos --}}
            @if ($fullPerformances->count())
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="section-title">🎬 Rekaman Maestro</h3>
                        <a href="{{ route('dataset', ['karakter' => $selectedKarakter]) }}" class="panel-link">
                            Lihat dataset AI →
                        </a>
                    </div>

                    @foreach ($fullPerformances as $ref)
                        <div class="mb-3">
                            @if ($ref->video_url)
                                <div class="video-frame">
                                    <video src="{{ $ref->video_url }}"
                                           @if ($ref->poster_url) poster="{{ $ref->poster_url }}" @endif
                                           controls preload="metadata" playsinline></video>
                                </div>
                            @else
                                <div class="video-frame">
                                    <div class="video-placeholder">
                                        <span class="ico">🎬</span>
                                        <p class="muted fs-sm">Video belum tersedia untuk referensi ini.</p>
                                    </div>
                                </div>
                            @endif

                            <div class="row-between mt-2">
                                <div>
                                    <div class="fw-600 fs-sm">{{ $ref->gerakan_name }}</div>
                                    <div class="list-meta">
                                        {{ $ref->duration_for_humans }}
                                        @if ($ref->frame_count)
                                            · {{ number_format($ref->frame_count) }} frame
                                            · {{ round($ref->detection_rate * 100, 1) }}% terdeteksi
                                        @endif
                                    </div>
                                </div>
                                <span class="badge {{ $ref->role === 'maestro' ? 'badge-orange' : 'badge-soft' }}">
                                    {{ $ref->role === 'maestro' ? 'Referensi Emas' : 'Data Latihan' }}
                                </span>
                            </div>

                            @if (count($ref->frame_urls))
                                <details class="mt-2">
                                    <summary style="cursor:pointer; font-size:0.85rem; color:var(--primary-orange);">
                                        Lihat {{ count($ref->frame_urls) }} frame dengan titik sendi
                                    </summary>
                                    <div class="frame-gallery mt-2">
                                        @foreach ($ref->frame_urls as $url)
                                            <div class="frame-thumb" data-full="{{ $url }}">
                                                <img src="{{ $url }}" alt="Titik sendi {{ $ref->gerakan_name }}" loading="lazy">
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Gerakan cards --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">🎯 Gerakan {{ $karakter['name'] }}</h3>
                </div>

                <div class="grid grid-2">
                    @foreach ($gerakanList as $g)
                        <a href="{{ route('tutorial.show', ['karakter' => $selectedKarakter, 'gerakan' => $g['slug']]) }}"
                           class="panel" style="background:rgba(255,255,255,0.03); text-decoration:none; color:inherit; display:block;">
                            <div class="row-between mb-1">
                                <span class="fw-600">{{ $g['index'] }}. {{ $g['name'] }}</span>
                                @if ($g['completed'])
                                    <span class="badge badge-success">✓ Dikuasai</span>
                                @elseif ($g['attempts'] > 0)
                                    <span class="badge badge-warning">{{ $g['attempts'] }}× dicoba</span>
                                @else
                                    <span class="badge badge-soft">Belum</span>
                                @endif
                            </div>
                            <p class="muted fs-sm mb-2">{{ $g['desc'] }}</p>
                            <div class="row-between fs-xs muted">
                                <span>{{ $g['hitungan'] }} hitungan · {{ ucfirst($g['difficulty']) }}</span>
                                @if ($g['best_score'] > 0)
                                    <span class="text-orange fw-600">Terbaik: {{ $g['best_score'] }}</span>
                                @endif
                            </div>
                            @if ($g['best_score'] > 0)
                                <div class="progress-bar thin mt-2">
                                    <div class="progress-fill" style="width:0%;" data-width="{{ $g['best_score'] }}"></div>
                                </div>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="text-center">
                <a href="{{ route('practice', ['karakter' => $selectedKarakter]) }}" class="btn btn-primary">
                    🎭 Mulai Latihan {{ $karakter['name'] }}
                </a>
            </div>
        </div>
    </div>
</div>

<div class="lightbox" id="lightbox"><img src="" alt="" id="lightboxImg"></div>
@endsection

@push('scripts')
<script>
(function () {
    const lightbox = document.getElementById('lightbox');
    const img = document.getElementById('lightboxImg');

    document.querySelectorAll('.frame-thumb').forEach((thumb) => {
        thumb.addEventListener('click', () => {
            img.src = thumb.dataset.full;
            lightbox.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    });

    function close() {
        lightbox.classList.remove('open');
        document.body.style.overflow = '';
        img.src = '';
    }

    lightbox.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
@endpush
