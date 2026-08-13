@extends('layouts.app')

@section('title', $gerakan['name'])
@section('subtitle', $karakter['name'].' — gerakan '.$position.' dari '.$total)

@push('styles')
<style>
    .breadcrumb { display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem; color: var(--text-gray); margin-bottom: 1.1rem; flex-wrap: wrap; }
    .breadcrumb a { color: var(--text-gray); text-decoration: none; }
    .breadcrumb a:hover { color: var(--primary-orange); }

    .video-frame { position: relative; background: #000; border-radius: var(--radius-sm); overflow: hidden; aspect-ratio: 16/9; }
    .video-frame video { width: 100%; height: 100%; object-fit: contain; display: block; }
    .video-placeholder {
        width: 100%; height: 100%;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.6rem;
        background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card-hover) 100%);
        text-align: center; padding: 1.75rem;
    }
    .video-placeholder .ico { font-size: 3rem; opacity: 0.45; }

    .instruction-block { margin-bottom: 1.35rem; }
    .instruction-block h4 { font-size: 0.95rem; font-weight: 600; margin-bottom: 0.55rem; color: var(--primary-orange); }
    .instruction-block ul { margin-left: 1.15rem; }
    .instruction-block li { font-size: 0.88rem; line-height: 1.8; color: var(--text-gray); margin-bottom: 0.25rem; }

    .tip-card { background: rgba(255,255,255,0.03); border-radius: 12px; padding: 0.95rem; border-left: 3px solid var(--primary-orange); }
    .tip-card h5 { font-size: 0.85rem; margin-bottom: 0.3rem; font-weight: 600; }
    .tip-card p { font-size: 0.78rem; color: var(--text-gray); line-height: 1.55; }

    .frame-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 0.7rem; }
    .frame-thumb { border-radius: var(--radius-sm); overflow: hidden; background: #000; cursor: zoom-in; border: 1px solid var(--border); aspect-ratio: 16/9; }
    .frame-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s ease; }
    .frame-thumb:hover img { transform: scale(1.06); }

    .lightbox { position: fixed; inset: 0; z-index: 2000; background: rgba(0,0,0,0.92); display: none; align-items: center; justify-content: center; padding: 2rem; cursor: zoom-out; }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 100%; max-height: 100%; border-radius: var(--radius-sm); }
</style>
@endpush

@section('content')
<div class="container-wide">

    <nav class="breadcrumb">
        <a href="{{ route('tutorial') }}">Tutorial</a> <span>/</span>
        <a href="{{ route('tutorial', ['karakter' => $karakterSlug]) }}">{{ $karakter['name'] }}</a>
        <span>/</span>
        <span style="color: var(--text-white);">{{ $gerakan['name'] }}</span>
    </nav>

    {{-- ============ HEADER ============ --}}
    <div class="panel mb-3">
        <div class="row-between">
            <div>
                <h1 style="font-size:1.5rem;font-weight:700;">
                    {{ $karakter['icon'] }} {{ $gerakan['name'] }}
                </h1>
                <div class="row mt-1">
                    <span class="badge badge-soft">Gerakan {{ $position }}/{{ $total }}</span>
                    <span class="badge badge-soft">{{ $gerakan['hitungan'] }} hitungan</span>
                    <span class="badge badge-soft">{{ ucfirst($gerakan['difficulty']) }}</span>
                    @if ($progress?->completed)
                        <span class="badge badge-success">✓ Dikuasai</span>
                    @elseif ($progress && $progress->attempts > 0)
                        <span class="badge badge-warning">
                            {{ $progress->attempts }}× dicoba · terbaik {{ round($progress->best_score) }}
                        </span>
                    @endif
                </div>
            </div>
            <a href="{{ route('practice', ['karakter' => $karakterSlug, 'gerakan' => $gerakan['slug']]) }}"
               class="btn btn-primary">🎭 Latih Gerakan Ini</a>
        </div>
    </div>

    <div class="grid grid-main">

        {{-- ============ LEFT ============ --}}
        <div class="stack-lg">

            {{-- Video --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">🎬 Video Tutorial</h3>
                    @if ($reference?->video_url)
                        <span class="badge badge-soft">{{ $reference->duration_for_humans }}</span>
                    @endif
                </div>

                @if ($reference?->video_url)
                    <div class="video-frame">
                        <video src="{{ $reference->video_url }}"
                               @if ($reference->poster_url) poster="{{ $reference->poster_url }}" @endif
                               controls preload="metadata" playsinline></video>
                    </div>
                    @if ($reference->frame_count)
                        <div class="row-between mt-2 fs-xs muted">
                            <span>{{ number_format($reference->frame_count) }} frame dianalisis MediaPipe</span>
                            <span>{{ round($reference->detection_rate * 100, 1) }}% pose terdeteksi</span>
                        </div>
                    @endif
                @else
                    <div class="video-frame">
                        <div class="video-placeholder">
                            <span class="ico">🎬</span>
                            <h4 class="fw-600">Video belum tersedia</h4>
                            <p class="muted fs-sm" style="max-width:380px;">
                                Rekaman maestro untuk gerakan ini belum diunggah.
                                Panduan tertulis di bawah tetap dapat Anda ikuti,
                                dan AI tetap menilai gerakan Anda saat berlatih.
                            </p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Instructions --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">📖 Panduan Gerakan</h3>
                </div>

                @forelse ($reference?->instructions ?? [] as $block)
                    <div class="instruction-block">
                        <h4>{{ $block['title'] ?? '' }}</h4>
                        <ul>
                            @foreach ($block['points'] ?? [] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <div class="instruction-block">
                        <h4>Petunjuk Umum</h4>
                        <ul>
                            <li>{{ $gerakan['desc'] }} sesuai pakem karakter {{ $karakter['name'] }}.</li>
                            <li>Ikuti irama gamelan selama {{ $gerakan['hitungan'] }} hitungan.</li>
                            <li>Jaga keseimbangan dan kontrol setiap transisi gerakan.</li>
                        </ul>
                    </div>
                @endforelse
            </div>

            {{-- Dataset frames --}}
            @if ($reference && count($reference->frame_urls))
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="section-title">🦴 Referensi Titik Sendi</h3>
                        <span class="badge badge-soft">{{ count($reference->frame_urls) }} frame</span>
                    </div>
                    <p class="muted fs-sm mb-2">
                        Frame hasil ekstraksi MediaPipe dengan 33 titik sendi dan sudut
                        persendian - inilah acuan yang dipakai AI untuk menilai Wiraga Anda.
                    </p>
                    <div class="frame-gallery">
                        @foreach ($reference->frame_urls as $url)
                            <div class="frame-thumb" data-full="{{ $url }}">
                                <img src="{{ $url }}" alt="Titik sendi {{ $gerakan['name'] }}" loading="lazy">
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ============ RIGHT ============ --}}
        <div class="stack-lg">

            {{-- Tips --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">💡 Tips & Catatan</h3>
                </div>
                <div class="stack">
                    @forelse ($reference?->tips ?? [] as $tip)
                        <div class="tip-card">
                            <h5>{{ $tip['icon'] ?? '💡' }} {{ $tip['title'] ?? '' }}</h5>
                            <p>{{ $tip['text'] ?? '' }}</p>
                        </div>
                    @empty
                        <div class="tip-card">
                            <h5>⏱️ Durasi</h5>
                            <p>{{ $gerakan['hitungan'] }} hitungan gamelan</p>
                        </div>
                        <div class="tip-card">
                            <h5>🎵 Tempo</h5>
                            <p>{{ $karakter['tempo'][0] }}–{{ $karakter['tempo'][1] }} BPM</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Character --}}
            <div class="panel">
                <div class="panel-header">
                    <h3 class="section-title">{{ $karakter['icon'] }} Karakter {{ $karakter['name'] }}</h3>
                </div>
                <p class="muted fs-sm" style="line-height:1.7;">{{ $karakter['filosofi'] }}</p>
                <div class="row mt-2">
                    @foreach ($karakter['ekspresi'] as $e)
                        <span class="badge badge-soft">{{ ucfirst($e) }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Your progress --}}
            @if ($progress)
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="section-title">📊 Progres Anda</h3>
                    </div>
                    <div class="row-between fs-sm mb-1">
                        <span class="muted">Skor terbaik</span>
                        <span class="fw-700 text-orange">{{ round($progress->best_score, 1) }}</span>
                    </div>
                    <div class="progress-bar mb-2">
                        <div class="progress-fill" style="width:0%;" data-width="{{ $progress->best_score }}"></div>
                    </div>
                    <div class="row-between fs-sm">
                        <span class="muted">Percobaan</span>
                        <span class="fw-600">{{ $progress->attempts }}×</span>
                    </div>
                    @if ($progress->completed)
                        <div class="alert alert-success mt-2" style="margin-bottom:0;">
                            <span>🎉</span>
                            <div>Gerakan ini sudah Anda kuasai (skor ≥ 75).</div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Navigation --}}
            <div class="panel">
                <div class="stack">
                    @if ($previous)
                        <a href="{{ route('tutorial.show', ['karakter' => $karakterSlug, 'gerakan' => $previous['slug']]) }}"
                           class="btn btn-secondary btn-block">← {{ $previous['name'] }}</a>
                    @endif
                    @if ($next)
                        <a href="{{ route('tutorial.show', ['karakter' => $karakterSlug, 'gerakan' => $next['slug']]) }}"
                           class="btn btn-secondary btn-block">{{ $next['name'] }} →</a>
                    @endif
                    <a href="{{ route('tutorial', ['karakter' => $karakterSlug]) }}"
                       class="btn btn-ghost btn-block">Semua Gerakan</a>
                </div>
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
    document.querySelectorAll('.frame-thumb').forEach((t) => {
        t.addEventListener('click', () => {
            img.src = t.dataset.full;
            lightbox.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    });
    function close() { lightbox.classList.remove('open'); document.body.style.overflow = ''; img.src = ''; }
    lightbox.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
@endpush
