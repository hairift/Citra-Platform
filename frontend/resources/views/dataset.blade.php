@extends('layouts.app')

@section('title', 'Dataset AI')
@section('subtitle', 'Golden dataset pose hasil ekstraksi MediaPipe')

@push('styles')
<style>
    .frame-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; }
    .frame-thumb { border-radius: var(--radius-sm); overflow: hidden; background: #000; cursor: zoom-in; border: 1px solid var(--border); aspect-ratio: 16/9; position: relative; }
    .frame-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.3s ease; }
    .frame-thumb:hover img { transform: scale(1.06); }
    .frame-thumb .idx {
        position: absolute; bottom: 4px; right: 6px;
        background: rgba(0,0,0,0.7); padding: 1px 6px;
        border-radius: 4px; font-size: 0.65rem; color: #fff;
    }

    .lightbox { position: fixed; inset: 0; z-index: 2000; background: rgba(0,0,0,0.93); display: none; align-items: center; justify-content: center; padding: 2rem; cursor: zoom-out; }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 100%; max-height: 100%; border-radius: var(--radius-sm); }

    .video-frame { background: #000; border-radius: var(--radius-sm); overflow: hidden; aspect-ratio: 16/9; }
    .video-frame video { width: 100%; height: 100%; object-fit: contain; display: block; }

    .segment-strip { display: flex; gap: 2px; height: 34px; border-radius: 6px; overflow: hidden; background: rgba(255,255,255,0.04); }
    .segment-block { flex-shrink: 0; position: relative; cursor: help; transition: opacity 0.2s ease; }
    .segment-block:hover { opacity: 0.75; }
    .segment-block.tenang  { background: rgba(59, 130, 246, 0.55); }
    .segment-block.sedang  { background: rgba(234, 179, 8, 0.55); }
    .segment-block.dinamis { background: rgba(232, 90, 32, 0.75); }
</style>
@endpush

@section('content')
<div class="container-wide">

    <div class="page-header">
        <h1>🧠 Dataset AI - Titik Sendi Tari Topeng</h1>
        <p>
            Setiap video maestro diproses MediaPipe Holistic (model complexity 2) untuk
            mengekstrak 33 titik sendi, 12 sudut persendian, orientasi tubuh, dan
            landmark tangan pada setiap frame. Data inilah yang menjadi acuan penilaian
            Wiraga dan bahan latih model deep learning.
        </p>
    </div>

    {{-- ============ TOTALS ============ --}}
    <div class="grid grid-stats mb-3">
        <div class="stat-card">
            <div class="stat-icon orange">🎬</div>
            <div class="stat-value">{{ $totals['videos'] }}</div>
            <div class="stat-label">Video Diproses</div>
            <div class="stat-sub">{{ $totals['minutes'] }} menit rekaman</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🦴</div>
            <div class="stat-value">{{ number_format($totals['frames']) }}</div>
            <div class="stat-label">Frame Dianalisis</div>
            <div class="stat-sub">{{ number_format($totals['detected']) }} pose terdeteksi</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🎯</div>
            <div class="stat-value">{{ $totals['detection_rate'] }}%</div>
            <div class="stat-label">Tingkat Deteksi</div>
            <div class="stat-sub">Akurasi pipeline MediaPipe</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">📐</div>
            <div class="stat-value">{{ number_format($totals['segments']) }}</div>
            <div class="stat-label">Segmen Gerakan</div>
            <div class="stat-sub">Hasil segmentasi otomatis</div>
        </div>
    </div>

    {{-- ============ FILTERS ============ --}}
    <div class="filter-tabs mb-3">
        <a href="{{ route('dataset') }}" class="filter-tab {{ !$karakter ? 'active' : '' }}">Semua</a>
        @foreach (config('citra.karakters') as $slug => $meta)
            <a href="{{ route('dataset', ['karakter' => $slug]) }}"
               class="filter-tab {{ $karakter === $slug ? 'active' : '' }}">
                {{ $meta['icon'] }} {{ $meta['name'] }}
            </a>
        @endforeach
    </div>

    @forelse ($datasets as $dataset)
        <div class="panel mb-3">
            <div class="panel-header">
                <div>
                    <h3 class="section-title">{{ $dataset->title }}</h3>
                    <div class="list-meta mt-1">
                        <code>{{ $dataset->slug }}</code>
                    </div>
                </div>
                <span class="badge {{ $dataset->role === 'maestro' ? 'badge-orange' : 'badge-info' }}">
                    {{ $dataset->role === 'maestro' ? 'Referensi Emas' : 'Data Latihan' }}
                </span>
            </div>

            @if ($dataset->description)
                <p class="muted fs-sm mb-3" style="line-height:1.7;">{{ $dataset->description }}</p>
            @endif

            <div class="grid grid-main mb-3">
                <div>
                    @if ($dataset->video_url)
                        <div class="video-frame">
                            <video src="{{ $dataset->video_url }}"
                                   @if ($dataset->poster_url) poster="{{ $dataset->poster_url }}" @endif
                                   controls preload="metadata" playsinline></video>
                        </div>
                    @else
                        <div class="empty-state" style="background:rgba(255,255,255,0.02);border-radius:var(--radius-sm);">
                            <div class="icon">🎬</div>
                            <p class="fs-sm">Video web belum tersedia untuk dataset ini.</p>
                        </div>
                    @endif
                </div>

                <div class="stack">
                    <div class="row-between fs-sm" style="padding:0.45rem 0;border-bottom:1px solid var(--border);">
                        <span class="muted">Durasi</span>
                        <span class="fw-600">{{ $dataset->duration_for_humans }}</span>
                    </div>
                    <div class="row-between fs-sm" style="padding:0.45rem 0;border-bottom:1px solid var(--border);">
                        <span class="muted">Resolusi Sumber</span>
                        <span class="fw-600">{{ $dataset->resolution ?? '—' }}</span>
                    </div>
                    <div class="row-between fs-sm" style="padding:0.45rem 0;border-bottom:1px solid var(--border);">
                        <span class="muted">Sampling</span>
                        <span class="fw-600">{{ $dataset->sample_fps }} fps</span>
                    </div>
                    <div class="row-between fs-sm" style="padding:0.45rem 0;border-bottom:1px solid var(--border);">
                        <span class="muted">Frame Diambil</span>
                        <span class="fw-600">{{ number_format($dataset->sampled_frames) }}</span>
                    </div>
                    <div class="row-between fs-sm" style="padding:0.45rem 0;border-bottom:1px solid var(--border);">
                        <span class="muted">Pose Terdeteksi</span>
                        <span class="fw-600 {{ $dataset->detection_percent >= 95 ? 'text-success' : 'text-warning' }}">
                            {{ number_format($dataset->detected_frames) }} ({{ $dataset->detection_percent }}%)
                        </span>
                    </div>
                    <div class="row-between fs-sm" style="padding:0.45rem 0;">
                        <span class="muted">Segmen Gerakan</span>
                        <span class="fw-600">{{ $dataset->segment_count }}</span>
                    </div>
                </div>
            </div>

            {{-- Segment timeline --}}
            @if (count($dataset->segments ?? []))
                @php
                    $segs = collect($dataset->segments);
                    $span = max($segs->max('end_time') ?: 1, 1);
                @endphp
                <div class="mb-2">
                    <div class="row-between fs-sm mb-1">
                        <span class="fw-600">Segmentasi Gerakan Otomatis</span>
                        <span class="muted fs-xs">
                            Berdasarkan energi gerakan · biru = tenang, kuning = sedang, oranye = dinamis
                        </span>
                    </div>
                    <div class="segment-strip">
                        @foreach ($segs as $seg)
                            <div class="segment-block {{ $seg['intensity'] ?? 'sedang' }}"
                                 style="width: {{ max(0.4, 100 * ($seg['duration'] ?? 0) / $span) }}%;"
                                 title="{{ $seg['label'] ?? '' }} — {{ $seg['start_time'] ?? 0 }}s s/d {{ $seg['end_time'] ?? 0 }}s ({{ $seg['intensity'] ?? '' }})"></div>
                        @endforeach
                    </div>
                    <div class="row-between fs-xs muted mt-1">
                        <span>0:00</span>
                        <span>{{ $dataset->duration_for_humans }}</span>
                    </div>
                </div>
            @endif

            {{-- Annotated frames --}}
            @if (count($dataset->frame_urls))
                <details open>
                    <summary style="cursor:pointer; font-size:0.88rem; color:var(--primary-orange); margin-bottom:0.75rem;">
                        {{ count($dataset->frame_urls) }} frame dengan titik sendi terlabel
                    </summary>
                    <div class="frame-gallery">
                        @foreach ($dataset->frame_urls as $i => $frame)
                            <div class="frame-thumb" data-full="{{ $frame['url'] }}">
                                <img src="{{ $frame['url'] }}" alt="Titik sendi {{ $dataset->title }}" loading="lazy">
                                <span class="idx">#{{ $i + 1 }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @else
                <p class="muted fs-sm">
                    Belum ada frame teranotasi yang dipublikasikan untuk dataset ini.
                    Jalankan <code>python build_dataset.py --publish-frames</code>.
                </p>
            @endif
        </div>
    @empty
        <div class="panel">
            <div class="empty-state">
                <div class="icon">🧠</div>
                <h4>Dataset belum tersedia</h4>
                <p>
                    Jalankan pipeline ekstraksi di folder <code>backend</code>:<br>
                    <code>python build_dataset.py</code><br>
                    lalu impor hasilnya:<br>
                    <code>php artisan citra:sync-dataset --publish</code>
                </p>
            </div>
        </div>
    @endforelse

    {{-- ============ TECHNICAL NOTE ============ --}}
    <div class="panel">
        <div class="panel-header">
            <h3 class="section-title">⚙️ Spesifikasi Teknis Pipeline</h3>
        </div>
        <div class="grid grid-2">
            <div>
                <h4 class="fw-600 fs-sm mb-1">Ekstraksi Pose</h4>
                <ul class="muted fs-sm" style="margin-left:1.15rem; line-height:1.85;">
                    <li>MediaPipe Holistic, <code>model_complexity=2</code> (paling akurat)</li>
                    <li>33 landmark pose + 21×2 landmark tangan per frame</li>
                    <li>Pemrosesan berurutan dengan tracking aktif (bukan static image mode)</li>
                    <li>Filter One-Euro untuk meredam jitter tanpa menambah lag</li>
                    <li>Landmark dunia (3D metrik) turut disimpan</li>
                </ul>
            </div>
            <div>
                <h4 class="fw-600 fs-sm mb-1">Representasi Fitur</h4>
                <ul class="muted fs-sm" style="margin-left:1.15rem; line-height:1.85;">
                    <li>Normalisasi: origin mid-hip, skala panjang torso, de-rotasi roll</li>
                    <li>17 landmark inti × 3 koordinat + 12 sudut sendi = 63 dimensi</li>
                    <li>Invarian terhadap posisi, tinggi badan, dan kemiringan kamera</li>
                    <li>Perbandingan runtime: Gaussian angle similarity + DTW sekuens</li>
                    <li>Segmentasi gerakan otomatis dari energi gerak</li>
                </ul>
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
