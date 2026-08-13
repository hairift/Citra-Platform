@extends('layouts.app')

@section('title', 'Profil Saya')
@section('subtitle', 'Kelola akun dan lihat pencapaian Anda')

@push('styles')
<style>
    .profile-header { display: flex; align-items: center; gap: 1.75rem; flex-wrap: wrap; margin-bottom: 1.75rem; }
    .avatar-wrap { position: relative; }
    .avatar-edit {
        position: absolute; bottom: -6px; right: -6px;
        width: 34px; height: 34px;
        border-radius: 50%;
        background: var(--primary-orange);
        border: 3px solid var(--bg-dark);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; font-size: 0.85rem;
        transition: background 0.2s ease;
    }
    .avatar-edit:hover { background: var(--primary-orange-hover); }

    .achievement-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 0.75rem; }
    .achievement-item {
        background: rgba(255,255,255,0.03);
        border: 1px solid transparent;
        border-radius: 12px;
        padding: 0.9rem 0.5rem;
        text-align: center;
        transition: transform 0.2s ease;
    }
    .achievement-item.unlocked {
        background: rgba(232, 90, 32, 0.1);
        border-color: var(--primary-orange);
    }
    .achievement-item.unlocked:hover { transform: translateY(-3px); }
    .achievement-item.locked { opacity: 0.38; filter: grayscale(1); }
    .achievement-item .ico { font-size: 1.75rem; margin-bottom: 0.35rem; }
    .achievement-item .nm { font-size: 0.7rem; font-weight: 500; line-height: 1.3; }
</style>
@endpush

@section('content')
<div class="container-narrow">

    {{-- ============ HEADER ============ --}}
    <div class="profile-header">
        <div class="avatar-wrap">
            <div class="avatar avatar-lg">
                @if ($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                @else
                    {{ $user->initial }}
                @endif
            </div>
            <label class="avatar-edit" for="avatarInput" title="Ubah foto profil">📷</label>
        </div>

        <div class="flex-1" style="min-width: 240px;">
            <h1 style="font-size:1.75rem; font-weight:700;">{{ $user->name }}</h1>
            <p class="muted">{{ $user->email }}</p>
            <div class="row mt-1">
                <span class="badge badge-orange">{{ $user->level }}</span>
                <span class="badge badge-soft">Peringkat #{{ $summary['rank'] }}</span>
                <span class="badge badge-soft">
                    Bergabung {{ $user->created_at?->translatedFormat('M Y') }}
                </span>
                @if ($user->isAdmin())
                    <span class="badge badge-info">Admin</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Hidden avatar upload form, triggered by the camera button above --}}
    <form id="avatarForm" method="POST" action="{{ route('profile.avatar') }}"
          enctype="multipart/form-data" class="hidden">
        @csrf
        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/jpg,image/webp">
    </form>

    {{-- ============ STATS ============ --}}
    <div class="grid grid-stats mb-3">
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ number_format($summary['total_score']) }}</div>
            <div class="stat-label">Total Skor</div>
        </div>
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ $summary['practice_count'] }}</div>
            <div class="stat-label">Sesi Latihan</div>
        </div>
        <div class="panel text-center">
            <div class="stat-value text-orange">{{ $summary['total_minutes'] }}</div>
            <div class="stat-label">Menit Latihan</div>
        </div>
        <div class="panel text-center">
            <div class="stat-value text-orange">🔥 {{ $summary['current_streak'] }}</div>
            <div class="stat-label">Hari Beruntun</div>
        </div>
    </div>

    <div class="grid grid-2 mb-3">

        {{-- ============ ACHIEVEMENTS ============ --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="section-title">🏆 Pencapaian</h3>
                <span class="badge badge-soft">{{ $unlockedCount }} / {{ count($achievements) }}</span>
            </div>
            <div class="achievement-grid">
                @foreach ($achievements as $a)
                    <div class="achievement-item {{ $a['unlocked'] ? 'unlocked' : 'locked' }}"
                         title="{{ $a['description'] }}{{ $a['unlocked'] ? '' : ' (belum terbuka)' }}">
                        <div class="ico">{{ $a['icon'] }}</div>
                        <div class="nm">{{ $a['name'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ============ CHARACTER MASTERY ============ --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="section-title">🎭 Penguasaan Karakter</h3>
            </div>
            @foreach ($characterMastery as $char)
                <div class="mb-2">
                    <div class="row-between fs-sm" style="margin-bottom:0.35rem;">
                        <span>{{ $char['icon'] }} {{ $char['name'] }}</span>
                        <span class="fw-600" style="color: {{ $char['color'] }};">{{ $char['mastery'] }}%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width:0%; background: {{ $char['color'] }};"
                             data-width="{{ $char['mastery'] }}"></div>
                    </div>
                    <div class="list-meta mt-1">
                        {{ $char['level'] }} · {{ $char['sessions'] }} sesi · terbaik {{ $char['best_score'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-2 mb-3">

        {{-- ============ RECENT ACTIVITY ============ --}}
        <div class="panel">
            <div class="panel-header">
                <h3 class="section-title">📊 Aktivitas Terakhir</h3>
                <a href="{{ route('history') }}" class="panel-link">Semua →</a>
            </div>
            @forelse ($recentSessions as $session)
                <a href="{{ route('history.show', $session->id) }}" class="list-item mb-1">
                    <div class="list-icon">{{ $session->karakter_icon }}</div>
                    <div class="list-body">
                        <div class="list-title truncate">{{ $session->title }}</div>
                        <div class="list-meta">{{ $session->created_at?->diffForHumans() }}</div>
                    </div>
                    <span class="score-badge score-{{ $session->score_class }}">
                        {{ round($session->total_score) }}
                    </span>
                </a>
            @empty
                <div class="empty-state" style="padding:1.75rem 1rem;">
                    <div class="icon">🎭</div>
                    <p class="fs-sm">Belum ada aktivitas latihan.</p>
                    <a href="{{ route('practice') }}" class="btn btn-primary btn-sm">Mulai Latihan</a>
                </div>
            @endforelse
        </div>

        <div class="stack-lg">
            {{-- ============ PERSONAL BEST ============ --}}
            @if ($bestSession)
                <div class="panel" style="background: linear-gradient(135deg, rgba(255,215,0,0.08), transparent);">
                    <div class="panel-header">
                        <h3 class="section-title">⭐ Rekor Pribadi</h3>
                    </div>
                    <div class="row-between">
                        <div>
                            <div class="fw-600">{{ $bestSession->title }}</div>
                            <div class="list-meta">
                                {{ $bestSession->created_at?->translatedFormat('d M Y') }} ·
                                {{ $bestSession->duration_for_humans }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="stat-value" style="color: var(--gold);">
                                {{ round($bestSession->total_score) }}
                            </div>
                            <div class="list-meta">Grade {{ $bestSession->resolved_grade }}</div>
                        </div>
                    </div>
                    <a href="{{ route('history.show', $bestSession->id) }}"
                       class="btn btn-secondary btn-sm btn-block mt-2">Lihat Detail</a>
                </div>
            @endif

            {{-- ============ IMPROVEMENT AREAS ============ --}}
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
                        Analisis per-sendi akan muncul setelah Anda berlatih dengan server AI aktif.
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============ EDIT PROFILE ============ --}}
    <div class="panel">
        <div class="panel-header">
            <h3 class="section-title">⚙️ Edit Profil</h3>
            @if ($user->avatar_url)
                <form method="POST" action="{{ route('profile.avatar.remove') }}"
                      data-confirm="Hapus foto profil Anda?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm">🗑 Hapus Foto</button>
                </form>
            @endif
        </div>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="grid grid-2" style="gap: 0 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-input"
                           value="{{ old('name', $user->name) }}" required maxlength="255">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="{{ old('email', $user->email) }}" required maxlength="255">
                </div>
            </div>

            <hr style="border:none; border-top:1px solid var(--border); margin:0.5rem 0 1.25rem;">
            <p class="muted fs-sm mb-2">
                Kosongkan bagian password jika Anda tidak ingin mengubahnya.
            </p>

            <div class="grid grid-3" style="gap: 0 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="current_password">Password Lama</label>
                    <input type="password" id="current_password" name="current_password"
                           class="form-input" autocomplete="current-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password Baru</label>
                    <input type="password" id="password" name="password"
                           class="form-input" autocomplete="new-password">
                    <div class="form-hint">Minimal 8 karakter</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-input" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Submit the avatar as soon as a file is picked - no extra button needed.
    document.getElementById('avatarInput')?.addEventListener('change', function () {
        if (!this.files.length) return;

        const maxBytes = 2 * 1024 * 1024;
        if (this.files[0].size > maxBytes) {
            alert('Ukuran gambar maksimal 2 MB.');
            this.value = '';
            return;
        }
        document.getElementById('avatarForm').submit();
    });
</script>
@endpush
