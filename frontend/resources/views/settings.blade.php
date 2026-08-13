@extends('layouts.app')

@section('title', 'Pengaturan')
@section('subtitle', 'Sesuaikan pengalaman latihan Anda')

@push('styles')
<style>
    .setting-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.25rem;
        padding: 0.95rem 0;
        border-bottom: 1px solid var(--border);
    }
    .setting-item:last-child { border-bottom: none; }
    .setting-info h4 { font-size: 0.92rem; font-weight: 500; margin-bottom: 0.2rem; }
    .setting-info p { font-size: 0.78rem; color: var(--text-gray); line-height: 1.5; }
    .setting-control { flex-shrink: 0; display: flex; align-items: center; gap: 0.6rem; }
    .setting-control .form-select { min-width: 165px; }
    .slider-value { font-size: 0.85rem; font-weight: 600; min-width: 44px; text-align: right; }
    .danger-zone { background: rgba(239, 68, 68, 0.07); border-color: rgba(239, 68, 68, 0.4); }

    @media (max-width: 640px) {
        .setting-item { flex-direction: column; align-items: stretch; gap: 0.7rem; }
        .setting-control { justify-content: flex-start; }
        .setting-control .form-select { flex: 1; }
    }
</style>
@endpush

@section('content')
<div class="container-narrow">

    <div class="page-header">
        <h1>⚙️ Pengaturan</h1>
        <p>Preferensi ini otomatis diterapkan pada Mode Latihan.</p>
    </div>

    {{-- ============ AI BACKEND STATUS ============ --}}
    <div class="panel mb-3">
        <div class="panel-header" style="margin-bottom:0.85rem;">
            <h3 class="section-title">🤖 Server AI</h3>
            @if ($aiHealth)
                <span class="badge badge-success">● Aktif</span>
            @else
                <span class="badge badge-error">● Nonaktif</span>
            @endif
        </div>

        @if ($aiHealth)
            <div class="grid grid-3" style="gap:0.75rem;">
                <div>
                    <div class="list-meta">Status</div>
                    <div class="fw-600 fs-sm">{{ ucfirst($aiHealth['status'] ?? 'ok') }}</div>
                </div>
                <div>
                    <div class="list-meta">Database</div>
                    <div class="fw-600 fs-sm">
                        {{ ($aiHealth['database']['connected'] ?? false) ? 'Terhubung' : 'Terputus' }}
                    </div>
                </div>
                <div>
                    <div class="list-meta">Sesi Aktif</div>
                    <div class="fw-600 fs-sm">{{ $aiHealth['active_sessions'] ?? 0 }}</div>
                </div>
            </div>

            @php $deep = $aiHealth['deep_models'] ?? []; @endphp
            <div class="row mt-2">
                <span class="badge {{ ($deep['gerakan_classifier'] ?? false) ? 'badge-success' : 'badge-soft' }}">
                    LSTM Gerakan {{ ($deep['gerakan_classifier'] ?? false) ? '✓' : '—' }}
                </span>
                <span class="badge {{ ($deep['pose_autoencoder'] ?? false) ? 'badge-success' : 'badge-soft' }}">
                    Autoencoder {{ ($deep['pose_autoencoder'] ?? false) ? '✓' : '—' }}
                </span>
                <span class="badge {{ ($deep['tempo_regressor'] ?? false) ? 'badge-success' : 'badge-soft' }}">
                    Tempo CNN {{ ($deep['tempo_regressor'] ?? false) ? '✓' : '—' }}
                </span>
            </div>
        @else
            <p class="muted fs-sm">
                Server AI tidak terdeteksi di <code>{{ $aiUrl }}</code>.
                Mode Latihan tetap berjalan dengan deteksi pose di browser, tetapi
                penilaian mendalam (perbandingan maestro, model deep learning)
                membutuhkan server ini.
            </p>
            <div class="alert alert-info mt-2" style="margin-bottom:0;">
                <span>💡</span>
                <div>
                    Jalankan di terminal:<br>
                    <code>cd backend</code><br>
                    <code>venv\Scripts\activate</code><br>
                    <code>python app.py</code>
                </div>
            </div>
        @endif
    </div>

    {{-- ============ PREFERENCES FORM ============ --}}
    <form method="POST" action="{{ route('settings.update') }}">
        @csrf

        {{-- Camera & display --}}
        <div class="panel mb-3">
            <h3 class="section-title mb-2">📹 Kamera & Tampilan</h3>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Kualitas Video</h4>
                    <p>Resolusi kamera saat latihan. Kualitas lebih tinggi butuh perangkat lebih kuat.</p>
                </div>
                <div class="setting-control">
                    <select name="videoQuality" class="form-select">
                        <option value="low"    @selected($settings['videoQuality'] === 'low')>480p (Hemat)</option>
                        <option value="medium" @selected($settings['videoQuality'] === 'medium')>720p (Standar)</option>
                        <option value="high"   @selected($settings['videoQuality'] === 'high')>1080p (Tinggi)</option>
                    </select>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Tampilkan Skeleton</h4>
                    <p>Menampilkan garis rangka tubuh di atas video kamera.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="showSkeleton" value="1" @checked($settings['showSkeleton'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Tampilkan Titik Sendi</h4>
                    <p>Menampilkan 33 titik sendi hasil deteksi MediaPipe.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="showLandmarks" value="1" @checked($settings['showLandmarks'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Mode Mirror</h4>
                    <p>Cerminkan tampilan kamera seperti bercermin - lebih intuitif saat menirukan gerakan.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="mirrorMode" value="1" @checked($settings['mirrorMode'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Audio --}}
        <div class="panel mb-3">
            <h3 class="section-title mb-2">🔊 Audio</h3>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Volume Musik Gamelan</h4>
                    <p>Volume iringan gamelan saat latihan.</p>
                </div>
                <div class="setting-control">
                    <input type="range" name="musicVolume" id="musicVolume"
                           min="0" max="100" value="{{ $settings['musicVolume'] }}" style="width:150px;">
                    <span class="slider-value" id="musicVolumeValue">{{ $settings['musicVolume'] }}%</span>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Volume Feedback</h4>
                    <p>Volume suara notifikasi umpan balik dari AI.</p>
                </div>
                <div class="setting-control">
                    <input type="range" name="feedbackVolume" id="feedbackVolume"
                           min="0" max="100" value="{{ $settings['feedbackVolume'] }}" style="width:150px;">
                    <span class="slider-value" id="feedbackVolumeValue">{{ $settings['feedbackVolume'] }}%</span>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Suara Feedback</h4>
                    <p>Bunyi pendek saat AI memberi koreksi gerakan.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="soundFeedback" value="1" @checked($settings['soundFeedback'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Practice --}}
        <div class="panel mb-3">
            <h3 class="section-title mb-2">🎭 Latihan</h3>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Tingkat Kesulitan</h4>
                    <p>Menentukan seberapa ketat toleransi sudut sendi saat dinilai.</p>
                </div>
                <div class="setting-control">
                    <select name="difficulty" class="form-select">
                        <option value="easy"   @selected($settings['difficulty'] === 'easy')>Mudah (toleransi ±18°)</option>
                        <option value="medium" @selected($settings['difficulty'] === 'medium')>Menengah (±12°)</option>
                        <option value="hard"   @selected($settings['difficulty'] === 'hard')>Sulit (±8°)</option>
                    </select>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Hitung Mundur Sebelum Mulai</h4>
                    <p>Waktu persiapan untuk mengambil posisi sebelum penilaian dimulai.</p>
                </div>
                <div class="setting-control">
                    <select name="countdown" class="form-select">
                        <option value="0"  @selected((int) $settings['countdown'] === 0)>Tanpa Hitung Mundur</option>
                        <option value="3"  @selected((int) $settings['countdown'] === 3)>3 Detik</option>
                        <option value="5"  @selected((int) $settings['countdown'] === 5)>5 Detik</option>
                        <option value="10" @selected((int) $settings['countdown'] === 10)>10 Detik</option>
                    </select>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Tampilkan Referensi Maestro</h4>
                    <p>Menampilkan video maestro berdampingan sebagai panduan.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="showMaestro" value="1" @checked($settings['showMaestro'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Auto-save Sesi</h4>
                    <p>Simpan hasil sesi secara otomatis saat latihan diakhiri.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="autoSave" value="1" @checked($settings['autoSave'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Notifications --}}
        <div class="panel mb-3">
            <h3 class="section-title mb-2">🔔 Notifikasi</h3>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Update Leaderboard</h4>
                    <p>Beri tahu saat Anda masuk 10 besar papan peringkat.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="leaderboardNotify" value="1" @checked($settings['leaderboardNotify'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Pencapaian</h4>
                    <p>Beri tahu saat Anda membuka lencana pencapaian baru.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="achievementNotify" value="1" @checked($settings['achievementNotify'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-item">
                <div class="setting-info">
                    <h4>Pengingat Latihan Harian</h4>
                    <p>Tampilkan pengingat di dashboard jika belum berlatih hari ini.</p>
                </div>
                <div class="setting-control">
                    <label class="toggle-switch">
                        <input type="checkbox" name="reminderEnabled" value="1" @checked($settings['reminderEnabled'])>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="text-center mb-3">
            <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
        </div>
    </form>

    {{-- ============ DATA ============ --}}
    <div class="panel mb-3">
        <h3 class="section-title mb-2">📦 Data Saya</h3>
        <div class="setting-item">
            <div class="setting-info">
                <h4>Ekspor Data Latihan</h4>
                <p>Unduh seluruh riwayat latihan Anda dalam format JSON.</p>
            </div>
            <div class="setting-control">
                <a href="{{ route('settings.export') }}" class="btn btn-secondary btn-sm">⬇ Unduh JSON</a>
            </div>
        </div>
    </div>

    {{-- ============ DANGER ZONE ============ --}}
    <div class="panel danger-zone">
        <h3 class="section-title mb-2">⚠️ Zona Bahaya</h3>

        <div class="setting-item">
            <div class="setting-info">
                <h4>Reset Progress</h4>
                <p>Menghapus semua sesi latihan, skor, peringkat, dan pencapaian.
                   Akun Anda tetap ada. Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="setting-control">
                <button type="button" class="btn btn-danger btn-sm" id="showResetForm">Reset Semua</button>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.reset') }}" id="resetForm" class="hidden"
              data-confirm="Yakin ingin menghapus SEMUA progress latihan Anda? Tindakan ini tidak dapat dibatalkan.">
            @csrf
            <div class="form-group mt-2">
                <label class="form-label" for="reset_password">Konfirmasi dengan password Anda</label>
                <input type="password" id="reset_password" name="password" class="form-input"
                       placeholder="Password akun Anda" autocomplete="current-password">
            </div>
            <div class="row">
                <button type="submit" class="btn btn-danger btn-sm">Ya, Reset Progress</button>
                <button type="button" class="btn btn-ghost btn-sm" data-cancel="resetForm">Batal</button>
            </div>
        </form>

        <div class="setting-item">
            <div class="setting-info">
                <h4>Hapus Akun</h4>
                <p>Menghapus akun beserta seluruh data secara permanen.
                   Data tidak dapat dipulihkan.</p>
            </div>
            <div class="setting-control">
                <button type="button" class="btn btn-danger btn-sm" id="showDeleteForm">Hapus Akun</button>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.delete') }}" id="deleteForm" class="hidden"
              data-confirm="PERINGATAN: Akun dan seluruh data Anda akan dihapus permanen. Lanjutkan?">
            @csrf
            @method('DELETE')
            <div class="form-group mt-2">
                <label class="form-label" for="delete_password">Password Anda</label>
                <input type="password" id="delete_password" name="password" class="form-input"
                       placeholder="Password akun Anda" autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="delete_confirm">Ketik <strong>HAPUS</strong> untuk mengonfirmasi</label>
                <input type="text" id="delete_confirm" name="confirm" class="form-input" placeholder="HAPUS">
            </div>
            <div class="row">
                <button type="submit" class="btn btn-danger btn-sm">Hapus Akun Permanen</button>
                <button type="button" class="btn btn-ghost btn-sm" data-cancel="deleteForm">Batal</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Live slider read-outs
    [['musicVolume', 'musicVolumeValue'], ['feedbackVolume', 'feedbackVolumeValue']]
        .forEach(([input, output]) => {
            const el = document.getElementById(input);
            const out = document.getElementById(output);
            el?.addEventListener('input', () => { out.textContent = el.value + '%'; });
        });

    // Reveal the confirmation forms only when the user asks for them.
    function bindReveal(buttonId, formId) {
        document.getElementById(buttonId)?.addEventListener('click', () => {
            const form = document.getElementById(formId);
            form.classList.remove('hidden');
            form.querySelector('input')?.focus();
        });
    }
    bindReveal('showResetForm', 'resetForm');
    bindReveal('showDeleteForm', 'deleteForm');

    document.querySelectorAll('[data-cancel]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const form = document.getElementById(btn.dataset.cancel);
            form.reset();
            form.classList.add('hidden');
        });
    });
})();
</script>
@endpush
