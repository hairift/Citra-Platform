@extends('layouts.app')

@section('title', 'Mode Latihan')
@section('subtitle', 'Analisis gerakan real-time dengan AI')

@push('styles')
<style>
    .practice-grid { display: grid; grid-template-columns: minmax(0, 1fr) 350px; gap: 1.25rem; }
    @media (max-width: 1200px) { .practice-grid { grid-template-columns: minmax(0, 1fr); } }

    .stage { display: flex; flex-direction: column; gap: 1rem; }

    .video-stage {
        position: relative;
        background: #000;
        border-radius: var(--radius);
        overflow: hidden;
        aspect-ratio: 16 / 9;
    }
    .video-stage.compare { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; background: var(--border); }
    .video-pane { position: relative; background: #000; overflow: hidden; }

    #videoElement { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; }
    #canvasElement { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    #maestroVideo { width: 100%; height: 100%; object-fit: contain; background: #000; }

    .pane-tag {
        position: absolute; top: 0.6rem; left: 0.6rem; z-index: 6;
        background: rgba(0,0,0,0.68); padding: 0.25rem 0.65rem;
        border-radius: 999px; font-size: 0.7rem; font-weight: 600;
    }

    .stage-overlay {
        position: absolute; top: 0.75rem; left: 0.75rem; right: 0.75rem;
        display: flex; justify-content: space-between; align-items: flex-start;
        z-index: 8; pointer-events: none; gap: 0.5rem;
    }
    .status-badge {
        background: rgba(0,0,0,0.68);
        padding: 0.4rem 0.85rem; border-radius: 999px;
        display: inline-flex; align-items: center; gap: 0.45rem;
        font-size: 0.78rem;
    }
    .status-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--error-red); flex-shrink: 0; }
    .status-dot.active { background: var(--success-green); animation: dotPulse 2s infinite; }
    @keyframes dotPulse { 0%,100% { opacity: 1; } 50% { opacity: 0.35; } }

    .timer {
        background: rgba(0,0,0,0.68);
        padding: 0.4rem 0.9rem; border-radius: 999px;
        font-size: 1.05rem; font-weight: 700; font-family: monospace;
    }

    .live-score {
        position: absolute; bottom: 0.75rem; left: 0.75rem; z-index: 8;
        background: rgba(0,0,0,0.72); border-radius: var(--radius-sm);
        padding: 0.55rem 0.95rem; display: none;
    }
    .live-score.on { display: block; }
    .live-score .num { font-size: 1.7rem; font-weight: 800; line-height: 1; }
    .live-score .lbl { font-size: 0.65rem; color: var(--text-gray); }

    .countdown-overlay {
        position: absolute; inset: 0; z-index: 20;
        background: rgba(0,0,0,0.72);
        display: none; align-items: center; justify-content: center;
        flex-direction: column; gap: 0.5rem;
    }
    .countdown-overlay.on { display: flex; }
    .countdown-num { font-size: 6rem; font-weight: 800; color: var(--primary-orange); line-height: 1; }

    .loading-overlay {
        position: absolute; inset: 0; z-index: 25;
        background: rgba(0,0,0,0.88);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 0.9rem; text-align: center; padding: 1.5rem;
    }
    .loading-overlay.hidden { display: none; }
    .spinner {
        width: 48px; height: 48px;
        border: 4px solid rgba(255,255,255,0.12);
        border-top-color: var(--primary-orange);
        border-radius: 50%;
        animation: spin 0.9s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .control-bar {
        display: flex; justify-content: center; gap: 0.75rem;
        padding: 0.85rem; background: var(--bg-card);
        border: 1px solid var(--border); border-radius: var(--radius);
        flex-wrap: wrap;
    }
    .control-btn {
        width: 46px; height: 46px; border-radius: 50%;
        border: 1px solid var(--border); background: var(--bg-card-hover);
        color: #fff; cursor: pointer; font-size: 1.05rem;
        display: flex; align-items: center; justify-content: center;
        transition: all 0.2s ease;
    }
    .control-btn:hover:not(:disabled) { border-color: var(--primary-orange); }
    .control-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .control-btn.on { background: var(--primary-orange); border-color: var(--primary-orange); }
    .control-btn.rec { background: var(--error-red); border-color: var(--error-red); }
    .control-btn.rec.active { animation: recPulse 1.1s infinite; }
    @keyframes recPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.11); } }

    .side { display: flex; flex-direction: column; gap: 1rem; }
    @media (max-width: 1200px) {
        .side { display: grid; grid-template-columns: repeat(auto-fit, minmax(290px, 1fr)); }
    }

    .main-score {
        font-size: 3.4rem; font-weight: 800; line-height: 1;
        background: linear-gradient(135deg, var(--primary-orange), #FF8C42);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .score-row { display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 0.25rem; }

    .feedback-list { display: flex; flex-direction: column; gap: 0.45rem; max-height: 210px; overflow-y: auto; }
    .feedback-item {
        padding: 0.6rem 0.75rem; background: rgba(255,255,255,0.03);
        border-radius: 8px; font-size: 0.8rem; line-height: 1.5;
        border-left: 3px solid var(--primary-orange);
        animation: slideIn 0.3s ease;
    }
    @keyframes slideIn { from { opacity: 0; transform: translateX(-8px); } to { opacity: 1; transform: none; } }
    .feedback-item.success { border-left-color: var(--success-green); background: rgba(34,197,94,0.08); }
    .feedback-item.warning { border-left-color: var(--warning-yellow); background: rgba(234,179,8,0.08); }
    .feedback-item.error   { border-left-color: var(--error-red); background: rgba(239,68,68,0.08); }
    .feedback-item.info    { border-left-color: var(--info-blue); background: rgba(59,130,246,0.06); }

    .karakter-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
    .karakter-btn {
        padding: 0.7rem 0.4rem; background: rgba(255,255,255,0.04);
        border: 2px solid transparent; border-radius: var(--radius-sm);
        cursor: pointer; text-align: center; font-size: 0.72rem;
        color: var(--text-white); font-family: inherit;
        text-decoration: none; display: block;
        transition: all 0.2s ease;
    }
    .karakter-btn:hover { background: rgba(255,255,255,0.08); }
    .karakter-btn.active { border-color: var(--primary-orange); background: rgba(232,90,32,0.12); }
    .karakter-btn .ico { font-size: 1.35rem; margin-bottom: 0.2rem; }

    .mini-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 0.55rem; }
    .mini-stat { text-align: center; padding: 0.55rem; background: rgba(255,255,255,0.03); border-radius: 8px; }
    .mini-stat .v { font-size: 1.15rem; font-weight: 700; }
    .mini-stat .l { font-size: 0.66rem; color: var(--text-gray); }

    .sparkline { display: flex; align-items: flex-end; gap: 3px; height: 62px; }
    .spark-bar { flex: 1; border-radius: 3px 3px 0 0; background: linear-gradient(to top, var(--primary-orange), transparent); min-height: 3px; }
    .spark-bar.current { background: linear-gradient(to top, var(--success-green), transparent); }

    .check-row { display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.82rem; }
    .check-row input { width: 17px; height: 17px; accent-color: var(--primary-orange); cursor: pointer; }

    .result-modal {
        position: fixed; inset: 0; z-index: 3000;
        background: rgba(0,0,0,0.86);
        display: none; align-items: center; justify-content: center; padding: 1.5rem;
    }
    .result-modal.open { display: flex; }
    .result-card {
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: var(--radius); padding: 2rem;
        max-width: 460px; width: 100%; text-align: center;
        max-height: 90vh; overflow-y: auto;
    }
    .result-grade { font-size: 4rem; font-weight: 800; line-height: 1; color: var(--primary-orange); }
</style>
@endpush

@section('content')
<div class="container-wide">

    {{-- ============ HEADER ============ --}}
    <div class="row-between mb-3">
        <div>
            <h1 style="font-size:1.4rem;font-weight:700;">
                {{ $karakters[$selectedKarakter]['icon'] }}
                Latihan {{ $karakters[$selectedKarakter]['name'] }}
            </h1>
            <p class="muted fs-sm">
                {{ $karakters[$selectedKarakter]['description'] }}
                @if ($recentBest)
                    · Skor terbaik Anda: <strong class="text-orange">{{ round($recentBest) }}</strong>
                @endif
            </p>
        </div>
        <button class="btn btn-primary" id="sessionBtn">▶ Mulai Sesi</button>
    </div>

    <div class="practice-grid">

        {{-- ============ STAGE ============ --}}
        <div class="stage">
            <div class="video-stage" id="videoStage">
                <div class="video-pane" id="userPane">
                    <span class="pane-tag hidden" id="userTag">Anda</span>
                    <video id="videoElement" autoplay playsinline muted></video>
                    <canvas id="canvasElement"></canvas>

                    <div class="stage-overlay">
                        <span class="status-badge">
                            <span class="status-dot" id="camDot"></span>
                            <span id="camText">Kamera mati</span>
                        </span>
                        <span class="timer" id="timer">00:00</span>
                    </div>

                    <div class="live-score" id="liveScore">
                        <div class="num" id="liveTotal">0</div>
                        <div class="lbl">Skor langsung</div>
                    </div>

                    <div class="countdown-overlay" id="countdown">
                        <div class="countdown-num" id="countdownNum">3</div>
                        <div class="muted">Bersiap…</div>
                    </div>

                    <div class="loading-overlay" id="loading">
                        <div class="spinner"></div>
                        <p class="muted fs-sm" id="loadingText">Memuat AI motion capture…</p>
                    </div>
                </div>

                <div class="video-pane hidden" id="maestroPane">
                    <span class="pane-tag">Maestro</span>
                    <video id="maestroVideo" playsinline muted loop
                           @if ($activeReference?->video_url) src="{{ $activeReference->video_url }}" @endif
                           @if ($activeReference?->poster_url) poster="{{ $activeReference->poster_url }}" @endif></video>
                </div>
            </div>

            <div class="control-bar">
                <button class="control-btn" id="camBtn" title="Nyalakan/matikan kamera">📷</button>
                <button class="control-btn" id="compareBtn" title="Mode banding dengan maestro"
                        @disabled(!$activeReference?->video_url)>👥</button>
                <button class="control-btn" id="musicBtn" title="Musik gamelan">🎵</button>
                <button class="control-btn rec" id="recBtn" title="Rekam latihan">⏺</button>
                <button class="control-btn" id="shotBtn" title="Screenshot pose">📸</button>
                <button class="control-btn" id="pipBtn" title="Picture-in-Picture">🖼</button>
                <button class="control-btn" id="fullBtn" title="Layar penuh">⛶</button>
            </div>

            {{-- Gerakan picker --}}
            <div class="panel">
                <div class="panel-header" style="margin-bottom:0.75rem;">
                    <h3 class="section-title">🎯 Fokus Gerakan</h3>
                    <a href="{{ route('tutorial', ['karakter' => $selectedKarakter]) }}" class="panel-link">
                        Buka tutorial →
                    </a>
                </div>
                <div class="filter-tabs">
                    <a href="{{ route('practice', ['karakter' => $selectedKarakter]) }}"
                       class="filter-tab {{ !$selectedGerakan ? 'active' : '' }}">Semua Gerakan</a>
                    @foreach ($gerakanList as $g)
                        @php $p = $progress->get($g['slug']); @endphp
                        <a href="{{ route('practice', ['karakter' => $selectedKarakter, 'gerakan' => $g['slug']]) }}"
                           class="filter-tab {{ $selectedGerakan === $g['slug'] ? 'active' : '' }}"
                           title="{{ $g['desc'] }} ({{ $g['hitungan'] }} hitungan)">
                            @if ($p?->completed) ✓ @endif
                            {{ $g['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ============ SIDEBAR ============ --}}
        <div class="side">

            {{-- Score --}}
            <div class="panel text-center">
                <div class="main-score" id="totalScore">0</div>
                <div class="muted fs-sm">Skor Total</div>
                <span class="badge badge-orange mt-1" id="gradeBadge">-</span>

                <div class="mt-3" style="text-align:left;">
                    <div class="score-row">
                        <span class="muted">Wiraga (Gerakan)</span>
                        <span class="fw-600" id="wiragaVal">0%</span>
                    </div>
                    <div class="progress-bar thin mb-2">
                        <div class="progress-fill wiraga" id="wiragaBar" style="width:0%"></div>
                    </div>

                    <div class="score-row">
                        <span class="muted">Wirama (Irama)</span>
                        <span class="fw-600" id="wiramaVal">0%</span>
                    </div>
                    <div class="progress-bar thin mb-2">
                        <div class="progress-fill wirama" id="wiramaBar" style="width:0%"></div>
                    </div>

                    <div class="score-row">
                        <span class="muted">Wirasa (Ekspresi)</span>
                        <span class="fw-600" id="wirasaVal">0%</span>
                    </div>
                    <div class="progress-bar thin">
                        <div class="progress-fill wirasa" id="wirasaBar" style="width:0%"></div>
                    </div>
                </div>
            </div>

            {{-- Session stats --}}
            <div class="panel">
                <h3 class="section-title mb-2">📊 Statistik Sesi</h3>
                <div class="mini-stats">
                    <div class="mini-stat"><div class="v" id="statFrames">0</div><div class="l">Frame Dianalisis</div></div>
                    <div class="mini-stat"><div class="v" id="statCorrect">0</div><div class="l">Pose Benar</div></div>
                    <div class="mini-stat"><div class="v" id="statAcc">0%</div><div class="l">Akurasi</div></div>
                    <div class="mini-stat"><div class="v" id="statStreak">0</div><div class="l">Streak Terbaik</div></div>
                </div>
            </div>

            {{-- Feedback --}}
            <div class="panel">
                <h3 class="section-title mb-2">💡 Feedback Real-time</h3>
                <div class="feedback-list" id="feedbackList">
                    <div class="feedback-item info">Tekan "Mulai Sesi" untuk memulai penilaian.</div>
                </div>
            </div>

            {{-- Recent performance --}}
            <div class="panel">
                <h3 class="section-title mb-2">📈 7 Sesi Terakhir</h3>
                @if (count($recentScores))
                    <div class="sparkline">
                        @foreach ($recentScores as $s)
                            <div class="spark-bar" style="height: {{ max(4, round($s)) }}%;" title="{{ $s }}"></div>
                        @endforeach
                        <div class="spark-bar current" id="sparkCurrent" style="height:3%;" title="Sesi ini"></div>
                    </div>
                    <div class="row-between fs-xs muted mt-1">
                        <span>Terlama</span><span>Sesi ini</span>
                    </div>
                @else
                    <p class="muted fs-sm">Belum ada sesi sebelumnya.</p>
                @endif
            </div>

            {{-- Character switch --}}
            <div class="panel">
                <h3 class="section-title mb-2">🎭 Ganti Karakter</h3>
                <div class="karakter-grid">
                    @foreach ($karakters as $slug => $meta)
                        <a href="{{ route('practice', ['karakter' => $slug]) }}"
                           class="karakter-btn {{ $selectedKarakter === $slug ? 'active' : '' }}">
                            <div class="ico">{{ $meta['icon'] }}</div>
                            {{ $meta['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Display settings --}}
            <div class="panel">
                <h3 class="section-title mb-2">🎨 Tampilan</h3>
                <div class="stack">
                    <label class="check-row">
                        <input type="checkbox" id="optSkeleton" @checked($settings['showSkeleton'])>
                        <span>Tampilkan skeleton</span>
                    </label>
                    <label class="check-row">
                        <input type="checkbox" id="optLandmarks" @checked($settings['showLandmarks'])>
                        <span>Tampilkan titik sendi</span>
                    </label>
                    <label class="check-row">
                        <input type="checkbox" id="optHands" checked>
                        <span>Tampilkan tangan</span>
                    </label>
                    <label class="check-row">
                        <input type="checkbox" id="optMirror" @checked($settings['mirrorMode'])>
                        <span>Mode mirror</span>
                    </label>
                    <label class="check-row">
                        <input type="checkbox" id="optCountdown" @checked((int) $settings['countdown'] > 0)>
                        <span>Hitung mundur {{ max((int) $settings['countdown'], 3) }} detik</span>
                    </label>
                </div>
                <a href="{{ route('settings') }}" class="btn btn-ghost btn-sm btn-block mt-2">
                    ⚙️ Pengaturan lengkap
                </a>
            </div>

            {{-- Audio --}}
            <div class="panel">
                <h3 class="section-title mb-2">🎵 Audio Gamelan</h3>
                <div class="score-row">
                    <span class="muted">Volume</span>
                    <span id="volVal">{{ $settings['musicVolume'] }}%</span>
                </div>
                <input type="range" id="volSlider" min="0" max="100"
                       value="{{ $settings['musicVolume'] }}" class="w-full mb-2">

                <div class="score-row">
                    <span class="muted">Tempo</span>
                    <span id="tempoVal">100%</span>
                </div>
                <input type="range" id="tempoSlider" min="50" max="150" value="100" class="w-full">

                <p class="muted fs-xs mt-2" id="audioHint">
                    Letakkan file gamelan di <code>public/audio/gamelan/</code>
                    dengan nama <code>{{ $selectedKarakter }}.mp3</code> untuk mengaktifkan iringan.
                </p>
            </div>
        </div>
    </div>
</div>

<audio id="gamelanAudio" preload="none" loop></audio>

{{-- ============ RESULT MODAL ============ --}}
<div class="result-modal" id="resultModal">
    <div class="result-card">
        <div style="font-size:2.5rem;">🎉</div>
        <h2 style="font-size:1.35rem;font-weight:700;margin:0.5rem 0;">Sesi Selesai!</h2>
        <div class="result-grade" id="resGrade">B</div>
        <div class="muted fs-sm mb-3" id="resTitle"></div>

        <div class="grid" style="grid-template-columns:repeat(3,1fr);gap:0.6rem;margin-bottom:1rem;">
            <div class="mini-stat"><div class="v text-success" id="resWiraga">0</div><div class="l">Wiraga</div></div>
            <div class="mini-stat"><div class="v" style="color:var(--info-blue)" id="resWirama">0</div><div class="l">Wirama</div></div>
            <div class="mini-stat"><div class="v" style="color:var(--purple)" id="resWirasa">0</div><div class="l">Wirasa</div></div>
        </div>

        <div class="mini-stats mb-3">
            <div class="mini-stat"><div class="v" id="resTotal">0</div><div class="l">Skor Total</div></div>
            <div class="mini-stat"><div class="v" id="resDuration">0:00</div><div class="l">Durasi</div></div>
        </div>

        <div id="resAchievements" class="mb-3"></div>

        <div class="row" style="justify-content:center;">
            <a href="#" class="btn btn-primary" id="resDetailBtn">Lihat Detail</a>
            <button class="btn btn-secondary" id="resCloseBtn">Latihan Lagi</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils@0.3/camera_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils@0.3/drawing_utils.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/holistic@0.5/holistic.js" crossorigin="anonymous"></script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>

<script>
const CFG = @json($aiConfig);
const KEYFRAME_URL = CFG.maestroReferenceId
    ? '{{ url('/api/maestro') }}/' + CFG.maestroReferenceId + '/keyframes'
    : null;
const GAMELAN_SRC = '{{ asset('audio/gamelan') }}/{{ $selectedKarakter }}.mp3';
const MIN_SECONDS = CFG.minSeconds || 10;

/* =====================================================================
   Pose geometry - a faithful JS port of backend/ai/pose_utils.py.
   Keeping the two in sync means the browser fallback and the Python
   scorer produce the same numbers for the same pose.
   ===================================================================== */
const L = {
    nose: 0, left_shoulder: 11, right_shoulder: 12, left_elbow: 13, right_elbow: 14,
    left_wrist: 15, right_wrist: 16, left_hip: 23, right_hip: 24,
    left_knee: 25, right_knee: 26, left_ankle: 27, right_ankle: 28,
    left_foot_index: 31, right_foot_index: 32,
};

const ANGLE_JOINTS = [
    ['left_shoulder', 'left_elbow', 'left_wrist'],
    ['right_shoulder', 'right_elbow', 'right_wrist'],
    ['left_elbow', 'left_shoulder', 'left_hip'],
    ['right_elbow', 'right_shoulder', 'right_hip'],
    ['left_shoulder', 'left_hip', 'left_knee'],
    ['right_shoulder', 'right_hip', 'right_knee'],
    ['left_hip', 'left_knee', 'left_ankle'],
    ['right_hip', 'right_knee', 'right_ankle'],
    ['left_knee', 'left_ankle', 'left_foot_index'],
    ['right_knee', 'right_ankle', 'right_foot_index'],
    ['right_shoulder', 'left_shoulder', 'left_elbow'],
    ['left_shoulder', 'right_shoulder', 'right_elbow'],
];
const ANGLE_NAMES = ANGLE_JOINTS.map(([a, b, c]) => `${a}_${b}_${c}`);

const ANGLE_LABELS = {
    'left_shoulder_left_elbow_left_wrist': 'tekukan siku kiri',
    'right_shoulder_right_elbow_right_wrist': 'tekukan siku kanan',
    'left_elbow_left_shoulder_left_hip': 'angkatan lengan kiri',
    'right_elbow_right_shoulder_right_hip': 'angkatan lengan kanan',
    'left_shoulder_left_hip_left_knee': 'kemiringan pinggul kiri',
    'right_shoulder_right_hip_right_knee': 'kemiringan pinggul kanan',
    'left_hip_left_knee_left_ankle': 'tekukan lutut kiri',
    'right_hip_right_knee_right_ankle': 'tekukan lutut kanan',
    'left_knee_left_ankle_left_foot_index': 'pergelangan kaki kiri',
    'right_knee_right_ankle_right_foot_index': 'pergelangan kaki kanan',
    'right_shoulder_left_shoulder_left_elbow': 'bukaan bahu kiri',
    'left_shoulder_right_shoulder_right_elbow': 'bukaan bahu kanan',
};

function jointAngle(a, b, c) {
    const bax = a.x - b.x, bay = a.y - b.y, baz = (a.z || 0) - (b.z || 0);
    const bcx = c.x - b.x, bcy = c.y - b.y, bcz = (c.z || 0) - (b.z || 0);
    const nba = Math.hypot(bax, bay, baz);
    const nbc = Math.hypot(bcx, bcy, bcz);
    if (nba < 1e-8 || nbc < 1e-8) return 0;
    const cos = (bax * bcx + bay * bcy + baz * bcz) / (nba * nbc);
    return Math.acos(Math.min(1, Math.max(-1, cos))) * 180 / Math.PI;
}

function computeAngles(lm) {
    const out = {};
    ANGLE_JOINTS.forEach(([a, b, c], i) => {
        out[ANGLE_NAMES[i]] = jointAngle(lm[L[a]], lm[L[b]], lm[L[c]]);
    });
    return out;
}

/**
 * Gaussian angle similarity, identical to pose_utils.angle_similarity.
 * A single bad joint should not destroy an otherwise correct pose, but a
 * systematically wrong posture must still score low.
 */
function compareAngles(userAngles, refAngles, tolerance) {
    const perJoint = {};
    const diffs = [];
    let sum = 0, n = 0;

    for (const name of ANGLE_NAMES) {
        if (!(name in userAngles) || !(name in refAngles)) continue;
        const d = Math.abs(userAngles[name] - refAngles[name]);
        const s = 100 * Math.exp(-0.5 * Math.pow(d / tolerance, 2));
        perJoint[name] = Math.round(s * 10) / 10;
        diffs.push([name, d]);
        sum += s; n++;
    }
    if (!n) return null;

    diffs.sort((a, b) => b[1] - a[1]);
    return { score: sum / n, jointScores: perJoint, worst: diffs.slice(0, 3), tolerance };
}

function visibilityOf(lm) {
    const idx = Object.values(L);
    let s = 0;
    idx.forEach((i) => { s += (lm[i]?.visibility ?? 1); });
    return s / idx.length;
}

/* Wirasa from bearing - the dancer wears a mask, so head carriage and torso
   openness carry the expression, not the face. Mirrors ExpressionAnalyzer. */
const WIRASA_PROFILE = @json(config('citra.karakters.'.$selectedKarakter));

function scoreWirasa(lm) {
    const ls = lm[L.left_shoulder], rs = lm[L.right_shoulder];
    const lh = lm[L.left_hip], rh = lm[L.right_hip];
    const nose = lm[L.nose];
    if (!ls || !rs || !lh || !rh) return null;

    const shoulderW = Math.hypot(rs.x - ls.x, rs.y - ls.y);
    const hipW = Math.hypot(rh.x - lh.x, rh.y - lh.y) || 1e-6;
    const openness = Math.min(1, Math.max(0, (shoulderW / hipW) / 2.5));

    const midShoulderY = (ls.y + rs.y) / 2;
    const midHipY = (lh.y + rh.y) / 2;
    const torso = Math.abs(midHipY - midShoulderY) || 1e-6;
    const headLift = (midShoulderY - nose.y) / torso;

    // Klana carries the mask high and the chest broad.
    const targetOpen = 0.75, targetLift = 0.55;
    const openScore = 100 * Math.exp(-Math.pow((openness - targetOpen) / 0.28, 2));
    const liftScore = 100 * Math.exp(-Math.pow((headLift - targetLift) / 0.32, 2));

    const tilt = Math.abs(ls.y - rs.y) / (shoulderW || 1e-6);
    const steady = 100 * Math.exp(-Math.pow(tilt / 0.35, 2));

    return 0.4 * openScore + 0.35 * liftScore + 0.25 * steady;
}

/* ===================================================================== */

const el = (id) => document.getElementById(id);
const dom = {
    stage: el('videoStage'), video: el('videoElement'), canvas: el('canvasElement'),
    maestroPane: el('maestroPane'), maestro: el('maestroVideo'), userTag: el('userTag'),
    loading: el('loading'), loadingText: el('loadingText'),
    camDot: el('camDot'), camText: el('camText'), timer: el('timer'),
    liveScore: el('liveScore'), liveTotal: el('liveTotal'),
    countdown: el('countdown'), countdownNum: el('countdownNum'),
    sessionBtn: el('sessionBtn'),
    total: el('totalScore'), grade: el('gradeBadge'),
    wiragaVal: el('wiragaVal'), wiramaVal: el('wiramaVal'), wirasaVal: el('wirasaVal'),
    wiragaBar: el('wiragaBar'), wiramaBar: el('wiramaBar'), wirasaBar: el('wirasaBar'),
    feedback: el('feedbackList'),
    statFrames: el('statFrames'), statCorrect: el('statCorrect'),
    statAcc: el('statAcc'), statStreak: el('statStreak'),
    sparkCurrent: el('sparkCurrent'),
    audio: el('gamelanAudio'), audioHint: el('audioHint'),
    modal: el('resultModal'),
};
const ctx = dom.canvas.getContext('2d');

const opts = {
    skeleton: el('optSkeleton'), landmarks: el('optLandmarks'),
    hands: el('optHands'), mirror: el('optMirror'), countdown: el('optCountdown'),
};

const state = {
    holistic: null, stream: null, socket: null,
    ready: false, camOn: false, running: false, active: false,
    sessionId: null, aiSessionId: null, startedAt: null, timerId: null,
    keyframes: [], compare: false, recorder: null, chunks: [],
    tolerance: 12,
    // accumulators
    frames: 0, correct: 0, streak: 0, bestStreak: 0,
    wiraga: [], wirama: [], wirasa: [],
    jointTotals: {}, jointCounts: {},
    timeline: [], series: [], lastSeriesAt: 0, lastFeedback: '',
    lastAnglesAt: 0, prevLandmarks: null,
};

const WEIGHTS = CFG.weights || { wiraga: 0.45, wirama: 0.30, wirasa: 0.25 };
const DIFFICULTY_TOLERANCE = { easy: 18, medium: 12, hard: 8 };
state.tolerance = DIFFICULTY_TOLERANCE['{{ $settings['difficulty'] }}'] ?? 12;

/* ------------------------- feedback UI ------------------------- */
function addFeedback(message, type = 'info') {
    if (message === state.lastFeedback) return;
    state.lastFeedback = message;

    const node = document.createElement('div');
    node.className = 'feedback-item ' + type;
    node.textContent = message;
    dom.feedback.insertBefore(node, dom.feedback.firstChild);
    while (dom.feedback.children.length > 12) dom.feedback.lastChild.remove();

    if (state.active) {
        const at = elapsed();
        if (!state.timeline.length || at - state.timeline[state.timeline.length - 1].at >= 3) {
            state.timeline.push({ at: Math.round(at * 10) / 10, type: 'wiraga', message, severity: type });
        }
    }
}

const elapsed = () => state.startedAt ? (Date.now() - state.startedAt) / 1000 : 0;

/* ------------------------- MediaPipe ------------------------- */
async function initHolistic() {
    dom.loadingText.textContent = 'Memuat model AI (MediaPipe Holistic)…';

    state.holistic = new Holistic({
        locateFile: (f) => `https://cdn.jsdelivr.net/npm/@mediapipe/holistic@0.5/${f}`,
    });
    state.holistic.setOptions({
        modelComplexity: 1,
        smoothLandmarks: true,
        refineFaceLandmarks: false,
        minDetectionConfidence: 0.6,
        minTrackingConfidence: 0.6,
    });
    state.holistic.onResults(onResults);
    await state.holistic.initialize();
    state.ready = true;
}

async function initCamera() {
    dom.loadingText.textContent = 'Mengakses kamera…';

    if (state.stream) {
        state.stream.getTracks().forEach((t) => t.stop());
        state.stream = null;
    }

    const quality = '{{ $settings['videoQuality'] }}';
    const sizes = { low: [640, 480], medium: [1280, 720], high: [1920, 1080] };
    const [w, h] = sizes[quality] || sizes.medium;

    const attempts = [
        { video: { width: { ideal: w }, height: { ideal: h }, facingMode: 'user' }, audio: false },
        { video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }, audio: false },
        { video: true, audio: false },
    ];

    let stream = null, lastError = null;
    for (const constraint of attempts) {
        try {
            stream = await navigator.mediaDevices.getUserMedia(constraint);
            break;
        } catch (e) { lastError = e; }
    }

    if (!stream) {
        const reason = lastError?.name === 'NotAllowedError'
            ? 'Izin kamera ditolak. Klik ikon kamera di address bar lalu izinkan.'
            : lastError?.name === 'NotFoundError'
                ? 'Kamera tidak ditemukan pada perangkat ini.'
                : 'Kamera sedang dipakai aplikasi lain atau tidak dapat diakses.';
        throw new Error(reason);
    }

    state.stream = stream;
    dom.video.srcObject = stream;

    await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('Kamera tidak merespons.')), 12000);
        dom.video.onloadedmetadata = () => {
            clearTimeout(timeout);
            dom.canvas.width = dom.video.videoWidth || 640;
            dom.canvas.height = dom.video.videoHeight || 480;
            dom.video.play().then(resolve).catch(reject);
        };
        dom.video.onerror = () => { clearTimeout(timeout); reject(new Error('Video error.')); };
    });

    state.camOn = true;
    dom.camDot.classList.add('active');
    dom.camText.textContent = 'Kamera aktif';
}

/* Frame pump, throttled to the configured FPS so a laptop CPU can keep up. */
function startPump() {
    if (state.running) return;
    state.running = true;

    const interval = 1000 / (CFG.targetFps || 12);
    let last = 0, busy = false;

    async function pump(ts) {
        if (!state.running) return;
        requestAnimationFrame(pump);

        if (busy || ts - last < interval) return;
        last = ts;

        if (!state.camOn || dom.video.readyState < 2) return;

        busy = true;
        try {
            await state.holistic.send({ image: dom.video });
        } catch (e) {
            // A transient send failure must not kill the loop.
        } finally {
            busy = false;
        }
    }
    requestAnimationFrame(pump);
}

function stopPump() { state.running = false; }

/* ------------------------- drawing + scoring ------------------------- */
function onResults(results) {
    const w = dom.canvas.width, h = dom.canvas.height;
    const mirror = opts.mirror.checked;

    ctx.save();
    ctx.clearRect(0, 0, w, h);
    if (mirror) { ctx.translate(w, 0); ctx.scale(-1, 1); }

    if (results.image) ctx.drawImage(results.image, 0, 0, w, h);

    if (results.poseLandmarks) {
        if (opts.skeleton.checked && window.POSE_CONNECTIONS) {
            drawConnectors(ctx, results.poseLandmarks, POSE_CONNECTIONS,
                { color: 'rgba(232,90,32,0.85)', lineWidth: 4 });
        }
        if (opts.landmarks.checked) {
            drawLandmarks(ctx, results.poseLandmarks,
                { color: '#FFFFFF', fillColor: '#FFFFFF', lineWidth: 1, radius: 4 });
        }
    }

    if (opts.hands.checked && window.HAND_CONNECTIONS) {
        if (results.leftHandLandmarks) {
            drawConnectors(ctx, results.leftHandLandmarks, HAND_CONNECTIONS, { color: 'rgba(68,255,68,0.8)', lineWidth: 2 });
            drawLandmarks(ctx, results.leftHandLandmarks, { color: '#44FF44', lineWidth: 1, radius: 2 });
        }
        if (results.rightHandLandmarks) {
            drawConnectors(ctx, results.rightHandLandmarks, HAND_CONNECTIONS, { color: 'rgba(255,68,68,0.8)', lineWidth: 2 });
            drawLandmarks(ctx, results.rightHandLandmarks, { color: '#FF4444', lineWidth: 1, radius: 2 });
        }
    }
    ctx.restore();

    if (!results.poseLandmarks) {
        if (state.active) addFeedback('Pose tidak terdeteksi - pastikan seluruh badan terlihat kamera', 'warning');
        return;
    }
    if (!state.active) return;

    scoreFrame(results);
}

function scoreFrame(results) {
    const lm = results.poseLandmarks;
    const vis = visibilityOf(lm);
    const angles = computeAngles(lm);

    /* ---- Wiraga: compare against the maestro keyframe at this moment ---- */
    let wiragaScore = null, comparison = null;

    if (state.keyframes.length) {
        const t = state.compare && !dom.maestro.paused
            ? dom.maestro.currentTime
            : elapsed() % (state.keyframes[state.keyframes.length - 1].t || 1);

        const ref = nearestKeyframe(t);
        if (ref) {
            comparison = compareAngles(angles, ref.angles, state.tolerance);
            if (comparison) {
                wiragaScore = comparison.score;
                // A barely-visible dancer must not score highly.
                if (vis < 0.5) wiragaScore *= Math.max(0.4, vis / 0.5);
            }
        }
    }

    if (wiragaScore === null) {
        // No reference dataset: score posture stability + visibility so the
        // session is still meaningful rather than a flat zero.
        wiragaScore = 100 * vis * postureQuality(angles);
    }

    /* ---- Wirasa ---- */
    const wirasaScore = scoreWirasa(lm);

    /* ---- Wirama: movement smoothness against the character's tempo ---- */
    const wiramaScore = scoreWirama(lm);

    /* ---- accumulate ---- */
    state.frames++;
    state.wiraga.push(wiragaScore);
    if (wirasaScore !== null) state.wirasa.push(wirasaScore);
    if (wiramaScore !== null) state.wirama.push(wiramaScore);

    if (wiragaScore >= 70) {
        state.correct++;
        state.streak++;
        state.bestStreak = Math.max(state.bestStreak, state.streak);
    } else {
        state.streak = 0;
    }

    if (comparison) {
        for (const [joint, s] of Object.entries(comparison.jointScores)) {
            state.jointTotals[joint] = (state.jointTotals[joint] || 0) + s;
            state.jointCounts[joint] = (state.jointCounts[joint] || 0) + 1;
        }
        emitCoaching(comparison, wiragaScore);
    }

    if (state.socket?.connected) {
        state.socket.emit('pose_frame', {
            session_id: state.aiSessionId,
            landmarks: lm.map((p) => ({ x: p.x, y: p.y, z: p.z, visibility: p.visibility })),
            karakter: CFG.karakter,
            video_time: state.compare ? dom.maestro.currentTime : elapsed(),
        });
    }

    updateHud();
}

function nearestKeyframe(t) {
    const kf = state.keyframes;
    if (!kf.length) return null;
    let lo = 0, hi = kf.length - 1;
    while (lo < hi) {
        const mid = (lo + hi) >> 1;
        if (kf[mid].t < t) lo = mid + 1; else hi = mid;
    }
    const a = kf[Math.max(0, lo - 1)], b = kf[lo];
    return Math.abs(a.t - t) <= Math.abs(b.t - t) ? a : b;
}

/** Are the joints in a plausible dance posture (not slumped / arms limp)? */
function postureQuality(angles) {
    const knees = (angles['left_hip_left_knee_left_ankle'] + angles['right_hip_right_knee_right_ankle']) / 2;
    const arms  = (angles['left_elbow_left_shoulder_left_hip'] + angles['right_elbow_right_shoulder_right_hip']) / 2;
    // Tari Topeng holds bent knees (~120-160) and lifted arms (~40-120).
    const kneeQ = Math.exp(-Math.pow((knees - 140) / 45, 2));
    const armQ  = Math.exp(-Math.pow((arms - 80) / 55, 2));
    return Math.min(1, 0.55 * kneeQ + 0.45 * armQ + 0.1);
}

/** Wirama from movement cadence versus the character's expected tempo band. */
function scoreWirama(lm) {
    const now = performance.now();
    if (!state.prevLandmarks) {
        state.prevLandmarks = lm.map((p) => ({ x: p.x, y: p.y }));
        state.lastAnglesAt = now;
        return null;
    }

    const dt = (now - state.lastAnglesAt) / 1000;
    if (dt < 0.03) return null;

    let motion = 0;
    const idx = Object.values(L);
    idx.forEach((i) => {
        const p = state.prevLandmarks[i], c = lm[i];
        if (p && c) motion += Math.hypot(c.x - p.x, c.y - p.y);
    });
    motion /= idx.length;

    state.prevLandmarks = lm.map((p) => ({ x: p.x, y: p.y }));
    state.lastAnglesAt = now;

    // Convert per-second motion into an approximate movement cadence, then
    // score how well it sits inside the character's tempo band.
    const speed = motion / dt;
    const [loBpm, hiBpm] = CFG.tempo || [70, 100];
    const targetSpeed = ((loBpm + hiBpm) / 2) / 260;   // empirical mapping
    const ratio = speed / (targetSpeed || 1e-6);

    if (ratio < 0.08) return null;                     // essentially still
    return 100 * Math.exp(-Math.pow(Math.log(ratio) / 0.85, 2));
}

function emitCoaching(comparison, score) {
    if (state.frames % 18 !== 0) return;               // ~1.5 s at 12 fps

    if (score >= 88) {
        addFeedback('Bagus! Gerakan sudah sesuai pakem', 'success');
        return;
    }
    const [name, diff] = comparison.worst[0] || [];
    if (!name || diff <= comparison.tolerance) {
        addFeedback('Pertahankan, gerakan sudah mendekati referensi', 'success');
        return;
    }
    const label = ANGLE_LABELS[name] || name;
    if (diff > 3 * comparison.tolerance) {
        addFeedback(`Perbaiki ${label} - selisih ${Math.round(diff)}° dari maestro`, 'error');
    } else if (diff > 1.8 * comparison.tolerance) {
        addFeedback(`Sesuaikan ${label} (selisih ${Math.round(diff)}°)`, 'warning');
    } else {
        addFeedback(`Sedikit lagi pada ${label}`, 'warning');
    }
}

const avg = (arr, n) => {
    if (!arr.length) return 0;
    const slice = n ? arr.slice(-n) : arr;
    return slice.reduce((a, b) => a + b, 0) / slice.length;
};

function weightedTotal(wiraga, wirama, wirasa) {
    // Aspects with no samples are excluded, so a session without audio does
    // not get a 0 for Wirama dragging the total down unfairly.
    let sum = 0, weight = 0;
    if (state.wiraga.length) { sum += wiraga * WEIGHTS.wiraga; weight += WEIGHTS.wiraga; }
    if (state.wirama.length) { sum += wirama * WEIGHTS.wirama; weight += WEIGHTS.wirama; }
    if (state.wirasa.length) { sum += wirasa * WEIGHTS.wirasa; weight += WEIGHTS.wirasa; }
    return weight > 0 ? sum / weight : 0;
}

function gradeFor(score) {
    const tiers = [[95,'A+'],[90,'A'],[85,'A-'],[80,'B+'],[75,'B'],[70,'B-'],
                   [65,'C+'],[60,'C'],[55,'C-'],[50,'D'],[0,'E']];
    for (const [min, g] of tiers) if (score >= min) return g;
    return 'E';
}

function updateHud() {
    const wiraga = avg(state.wiraga, 60);
    const wirama = avg(state.wirama, 60);
    const wirasa = avg(state.wirasa, 60);
    const total = weightedTotal(wiraga, wirama, wirasa);

    dom.total.textContent = Math.round(total);
    dom.liveTotal.textContent = Math.round(total);
    dom.grade.textContent = gradeFor(total);
    dom.grade.style.background = total >= 85 ? 'var(--success-green)'
        : total >= 60 ? 'var(--warning-yellow)' : 'var(--error-red)';

    dom.wiragaVal.textContent = Math.round(wiraga) + '%';
    dom.wiramaVal.textContent = Math.round(wirama) + '%';
    dom.wirasaVal.textContent = Math.round(wirasa) + '%';
    dom.wiragaBar.style.width = wiraga + '%';
    dom.wiramaBar.style.width = wirama + '%';
    dom.wirasaBar.style.width = wirasa + '%';

    dom.statFrames.textContent = state.frames;
    dom.statCorrect.textContent = state.correct;
    dom.statAcc.textContent = Math.round(100 * state.correct / Math.max(state.frames, 1)) + '%';
    dom.statStreak.textContent = state.bestStreak;

    if (dom.sparkCurrent) dom.sparkCurrent.style.height = Math.max(3, Math.round(total)) + '%';

    const now = elapsed();
    if (now - state.lastSeriesAt >= 1) {
        state.lastSeriesAt = now;
        state.series.push({
            t: Math.round(now * 10) / 10,
            wiraga: Math.round(wiraga * 10) / 10,
            wirama: Math.round(wirama * 10) / 10,
            wirasa: Math.round(wirasa * 10) / 10,
        });
    }
}

/* ------------------------- session lifecycle ------------------------- */
async function startSession() {
    if (!state.camOn) {
        addFeedback('Nyalakan kamera terlebih dahulu', 'warning');
        return;
    }

    dom.sessionBtn.disabled = true;
    const res = await window.citraPost(CFG.startUrl, {
        karakter: CFG.karakter,
        gerakan: CFG.gerakan,
        maestro_reference_id: CFG.maestroReferenceId,
    });
    dom.sessionBtn.disabled = false;

    if (!res.ok || !res.data?.success) {
        addFeedback('Gagal memulai sesi: ' + (res.data?.message || 'kesalahan server'), 'error');
        return;
    }

    state.sessionId = res.data.session_id;
    state.aiSessionId = res.data.ai_session_id;

    if (state.aiSessionId && state.socket?.connected) {
        state.socket.emit('join_session', {
            session_id: state.aiSessionId,
            maestro_reference_id: CFG.maestroReferenceId,
        });
    }

    if (opts.countdown.checked) await runCountdown(Math.max({{ (int) $settings['countdown'] }}, 3));

    resetAccumulators();
    state.active = true;
    state.startedAt = Date.now();
    state.timerId = setInterval(tickTimer, 250);

    dom.liveScore.classList.add('on');
    dom.sessionBtn.textContent = '⏹ Akhiri Sesi';
    dom.sessionBtn.classList.replace('btn-primary', 'btn-danger');

    if (state.compare && dom.maestro.src) { dom.maestro.currentTime = 0; dom.maestro.play().catch(() => {}); }
    if (dom.audio.src) dom.audio.play().catch(() => {});

    addFeedback(`Sesi dimulai - karakter ${CFG.karakter}. Ikuti gerakan maestro!`, 'success');
}

function resetAccumulators() {
    Object.assign(state, {
        frames: 0, correct: 0, streak: 0, bestStreak: 0,
        wiraga: [], wirama: [], wirasa: [],
        jointTotals: {}, jointCounts: {},
        timeline: [], series: [], lastSeriesAt: 0, lastFeedback: '',
        prevLandmarks: null,
    });
    updateHud();
}

function runCountdown(seconds) {
    return new Promise((resolve) => {
        let n = seconds;
        dom.countdown.classList.add('on');
        dom.countdownNum.textContent = n;
        const id = setInterval(() => {
            n--;
            if (n <= 0) {
                clearInterval(id);
                dom.countdown.classList.remove('on');
                resolve();
            } else {
                dom.countdownNum.textContent = n;
            }
        }, 1000);
    });
}

function tickTimer() {
    const s = Math.floor(elapsed());
    dom.timer.textContent =
        String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}

async function endSession() {
    if (!state.active) return;

    state.active = false;
    clearInterval(state.timerId);
    dom.liveScore.classList.remove('on');
    dom.sessionBtn.textContent = '▶ Mulai Sesi';
    dom.sessionBtn.classList.replace('btn-danger', 'btn-primary');
    dom.maestro.pause();
    dom.audio.pause();

    const duration = Math.round(elapsed());

    if (state.aiSessionId && state.socket?.connected) {
        state.socket.emit('leave_session', { session_id: state.aiSessionId });
    }

    if (duration < MIN_SECONDS) {
        addFeedback(`Sesi terlalu singkat (minimal ${MIN_SECONDS} detik) - tidak disimpan.`, 'warning');
        await window.citraPost('{{ route('practice.abort') }}', { session_id: state.sessionId });
        state.sessionId = null;
        return;
    }

    const jointScores = {};
    for (const joint of Object.keys(state.jointTotals)) {
        jointScores[joint] = Math.round((state.jointTotals[joint] / state.jointCounts[joint]) * 10) / 10;
    }

    const payload = {
        session_id: state.sessionId,
        wiraga_score: Math.round(avg(state.wiraga) * 10) / 10,
        wirama_score: Math.round(avg(state.wirama) * 10) / 10,
        wirasa_score: Math.round(avg(state.wirasa) * 10) / 10,
        duration,
        frames_analyzed: state.frames,
        correct_frames: state.correct,
        best_streak: state.bestStreak,
        feedback: buildSummaryFeedback(),
        timeline: state.timeline.slice(-100),
        score_series: state.series.slice(-600),
        joint_scores: jointScores,
    };

    dom.sessionBtn.disabled = true;
    const res = await window.citraPost(CFG.endUrl, payload);
    dom.sessionBtn.disabled = false;

    if (!res.ok || !res.data?.success) {
        addFeedback('Gagal menyimpan sesi: ' + (res.data?.message || 'kesalahan server'), 'error');
        return;
    }

    showResult(res.data);
    state.sessionId = null;
}

function buildSummaryFeedback() {
    const wiraga = avg(state.wiraga), wirama = avg(state.wirama), wirasa = avg(state.wirasa);
    const out = [];

    out.push(wiraga >= 85 ? 'Gerakan tubuh sangat presisi dan sesuai pakem!'
        : wiraga >= 70 ? 'Gerakan tubuh cukup baik, beberapa posisi masih perlu diperbaiki.'
        : wiraga >= 50 ? 'Perlu latihan lebih untuk memperbaiki ketepatan posisi tubuh.'
        : 'Fokus pelajari gerakan dasar terlebih dahulu sebelum kombinasi.');

    if (state.wirama.length) {
        out.push(wirama >= 85 ? 'Kecepatan gerak sudah sesuai karakter!'
            : wirama >= 70 ? 'Tempo cukup sesuai, jaga konsistensinya.'
            : 'Perhatikan tempo - sesuaikan kecepatan dengan karakter yang ditarikan.');
    }
    if (state.wirasa.length) {
        out.push(wirasa >= 85 ? 'Penghayatan dan sikap tubuh sangat menjiwai karakter!'
            : wirasa >= 70 ? 'Penghayatan sudah baik, tingkatkan intensitasnya.'
            : 'Perhatikan sikap badan dan arah pandang untuk memperkuat karakter.');
    }

    // Name the two weakest joints - far more actionable than a generic note.
    const worst = Object.entries(state.jointTotals)
        .map(([j, sum]) => [j, sum / state.jointCounts[j]])
        .sort((a, b) => a[1] - b[1])
        .slice(0, 2)
        .filter(([, s]) => s < 75);

    worst.forEach(([joint, s]) => {
        out.push(`Perlu perhatian pada ${ANGLE_LABELS[joint] || joint} (rata-rata ${Math.round(s)}%).`);
    });

    return out;
}

function showResult(data) {
    el('resGrade').textContent = data.grade;
    el('resTitle').textContent = data.session.title;
    el('resWiraga').textContent = Math.round(data.session.wiraga);
    el('resWirama').textContent = Math.round(data.session.wirama);
    el('resWirasa').textContent = Math.round(data.session.wirasa);
    el('resTotal').textContent = Math.round(data.session.total);
    el('resDuration').textContent = data.session.duration;
    el('resDetailBtn').href = data.detail_url;

    const box = el('resAchievements');
    box.innerHTML = (data.achievements || []).length
        ? '<div class="alert alert-success" style="margin:0;"><span>🏅</span><div>'
          + data.achievements.map((a) => `${a.icon} <strong>${a.name}</strong>`).join('<br>')
          + '</div></div>'
        : '';

    dom.modal.classList.add('open');
}

/* ------------------------- socket (optional) ------------------------- */
function initSocket() {
    if (!window.io || !CFG.wsUrl) return;
    try {
        state.socket = io(CFG.wsUrl, {
            transports: ['websocket', 'polling'],
            reconnectionAttempts: 3,
            reconnectionDelay: 2000,
            timeout: 5000,
            // JWT minted by Laravel from the shared secret, so an already
            // signed-in user does not have to authenticate a second time.
            auth: { token: CFG.token },
        });
        state.socket.on('connect', () => addFeedback('Terhubung ke server AI - penilaian mendalam aktif', 'success'));
        state.socket.on('disconnect', () => { /* offline mode still scores locally */ });
        state.socket.on('connect_error', () => { /* expected when Flask is not running */ });

        state.socket.on('pose_result', (data) => {
            if (data?.error || !data?.feedback) return;
            // The Python scorer is authoritative when available - it adds the
            // deep-learning terms the browser cannot compute.
            const f = data.feedback;
            if (typeof f.wiraga === 'number' && f.frame_count > 5) {
                dom.total.textContent = Math.round(f.total);
                dom.liveTotal.textContent = Math.round(f.total);
                dom.grade.textContent = f.grade;
            }
        });
    } catch (e) { /* ignore - offline mode */ }
}

/* ------------------------- controls ------------------------- */
el('camBtn').addEventListener('click', async function () {
    if (state.camOn) {
        stopPump();
        state.stream?.getTracks().forEach((t) => t.stop());
        state.stream = null;
        state.camOn = false;
        dom.video.srcObject = null;
        dom.camDot.classList.remove('active');
        dom.camText.textContent = 'Kamera mati';
        ctx.fillStyle = '#111';
        ctx.fillRect(0, 0, dom.canvas.width, dom.canvas.height);
        this.classList.remove('on');
        addFeedback('Kamera dimatikan', 'info');
    } else {
        try {
            await initCamera();
            startPump();
            this.classList.add('on');
            addFeedback('Kamera diaktifkan', 'success');
        } catch (e) {
            addFeedback(e.message, 'error');
        }
    }
});

el('compareBtn').addEventListener('click', function () {
    if (!dom.maestro.src) {
        addFeedback('Video maestro belum tersedia untuk karakter ini', 'warning');
        return;
    }
    state.compare = !state.compare;
    dom.stage.classList.toggle('compare', state.compare);
    dom.maestroPane.classList.toggle('hidden', !state.compare);
    dom.userTag.classList.toggle('hidden', !state.compare);
    this.classList.toggle('on', state.compare);

    if (state.compare) {
        dom.maestro.currentTime = 0;
        if (state.active) dom.maestro.play().catch(() => {});
        addFeedback('Mode banding aktif - ikuti gerakan maestro di sebelah kanan', 'info');
    } else {
        dom.maestro.pause();
        addFeedback('Mode normal', 'info');
    }
});

el('musicBtn').addEventListener('click', function () {
    if (!dom.audio.src) {
        // Probe once; if the file is absent we say so plainly instead of
        // silently doing nothing (which is what the old build did).
        dom.audio.src = GAMELAN_SRC;
        dom.audio.volume = el('volSlider').value / 100;
    }
    if (dom.audio.paused) {
        dom.audio.play().then(() => {
            this.classList.add('on');
            this.textContent = '⏸';
            addFeedback('Musik gamelan diputar', 'success');
        }).catch(() => {
            addFeedback('File gamelan belum tersedia di public/audio/gamelan/{{ $selectedKarakter }}.mp3', 'warning');
            dom.audio.removeAttribute('src');
        });
    } else {
        dom.audio.pause();
        this.classList.remove('on');
        this.textContent = '🎵';
    }
});

el('volSlider').addEventListener('input', function () {
    dom.audio.volume = this.value / 100;
    el('volVal').textContent = this.value + '%';
});

el('tempoSlider').addEventListener('input', function () {
    const rate = this.value / 100;
    dom.audio.playbackRate = rate;
    if (dom.maestro.src) dom.maestro.playbackRate = rate;
    el('tempoVal').textContent = this.value + '%';
});

el('recBtn').addEventListener('click', function () {
    if (!state.recorder || state.recorder.state === 'inactive') {
        try {
            const stream = dom.canvas.captureStream(24);
            const mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9')
                ? 'video/webm;codecs=vp9' : 'video/webm';
            state.recorder = new MediaRecorder(stream, { mimeType: mime });
            state.chunks = [];
            state.recorder.ondataavailable = (e) => { if (e.data.size) state.chunks.push(e.data); };
            state.recorder.onstop = () => {
                const blob = new Blob(state.chunks, { type: 'video/webm' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `latihan_${CFG.karakter}_${Date.now()}.webm`;
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                addFeedback('Rekaman tersimpan ke folder unduhan', 'success');
            };
            state.recorder.start();
            this.classList.add('active');
            addFeedback('Mulai merekam latihan', 'info');
        } catch (e) {
            addFeedback('Perangkat tidak mendukung perekaman', 'error');
        }
    } else {
        state.recorder.stop();
        this.classList.remove('active');
    }
});

el('shotBtn').addEventListener('click', () => {
    try {
        const a = document.createElement('a');
        a.download = `pose_${CFG.karakter}_${Date.now()}.png`;
        a.href = dom.canvas.toDataURL('image/png');
        a.click();
        addFeedback('Screenshot pose tersimpan', 'success');
    } catch (e) {
        addFeedback('Gagal mengambil screenshot', 'error');
    }
});

el('pipBtn').addEventListener('click', async () => {
    try {
        if (document.pictureInPictureElement) {
            await document.exitPictureInPicture();
        } else if (dom.maestro.src && state.compare) {
            await dom.maestro.requestPictureInPicture();
            addFeedback('Video maestro dipindah ke jendela mengambang', 'success');
        } else {
            addFeedback('Aktifkan mode banding untuk memakai PiP video maestro', 'warning');
        }
    } catch (e) {
        addFeedback('Browser tidak mendukung Picture-in-Picture', 'warning');
    }
});

el('fullBtn').addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen();
    else dom.stage.requestFullscreen?.().catch(() => addFeedback('Layar penuh tidak didukung', 'warning'));
});

dom.sessionBtn.addEventListener('click', () => state.active ? endSession() : startSession());

el('resCloseBtn').addEventListener('click', () => dom.modal.classList.remove('open'));
dom.modal.addEventListener('click', (e) => { if (e.target === dom.modal) dom.modal.classList.remove('open'); });

// Don't leave an "active" session row behind if the tab is closed mid-session.
window.addEventListener('beforeunload', (e) => {
    if (state.active) {
        navigator.sendBeacon?.(
            '{{ route('practice.abort') }}',
            new Blob([JSON.stringify({ session_id: state.sessionId, _token: window.CITRA.csrf })],
                     { type: 'application/json' })
        );
        e.preventDefault();
        e.returnValue = '';
    }
});

/* ------------------------- boot ------------------------- */
async function loadKeyframes() {
    if (!KEYFRAME_URL) return;
    const { ok, data } = await window.citraGet(KEYFRAME_URL);
    if (ok && data?.data?.length) {
        state.keyframes = data.data;
        addFeedback(`Referensi maestro dimuat (${data.count} pose acuan)`, 'success');
    } else {
        addFeedback('Referensi pose maestro belum tersedia - penilaian memakai analisis postur', 'info');
    }
}

(async function boot() {
    try {
        await initHolistic();
        initSocket();
        await loadKeyframes();
        await initCamera();
        startPump();
        el('camBtn').classList.add('on');
        dom.loading.classList.add('hidden');
        addFeedback('Sistem siap. Tekan "Mulai Sesi" untuk memulai penilaian.', 'success');
    } catch (e) {
        dom.loadingText.innerHTML =
            `<strong>${e.message}</strong><br>
             <span class="fs-xs">Anda tetap bisa menutup pesan ini dan mencoba
             menyalakan kamera lewat tombol 📷.</span>`;
        dom.loading.querySelector('.spinner').style.borderTopColor = 'var(--error-red)';
        setTimeout(() => dom.loading.classList.add('hidden'), 4500);
        addFeedback(e.message, 'error');
    }
})();
</script>
@endpush
