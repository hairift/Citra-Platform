{{-- Primary navigation. Included by layouts/app.blade.php. --}}
@php
    $navUser = Auth::user();
    $navItems = [
        ['route' => 'dashboard',   'icon' => '🏠', 'label' => 'Dashboard'],
        ['route' => 'practice',    'icon' => '🎭', 'label' => 'Mode Latihan'],
        ['route' => 'tutorial',    'icon' => '📚', 'label' => 'Tutorial'],
        ['route' => 'dataset',     'icon' => '🧠', 'label' => 'Dataset AI'],
        ['route' => 'leaderboard', 'icon' => '🏆', 'label' => 'Leaderboard'],
        ['route' => 'history',     'icon' => '📜', 'label' => 'Riwayat'],
        ['route' => 'profile',     'icon' => '👤', 'label' => 'Profil'],
        ['route' => 'settings',    'icon' => '⚙️', 'label' => 'Pengaturan'],
    ];
@endphp

<style>
    .app-sidebar {
        background: var(--bg-card);
        padding: 1.35rem 1.1rem;
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
    }

    .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 1.75rem;
        text-decoration: none;
        color: var(--text-white);
    }

    .sidebar-logo-icon {
        width: 40px; height: 40px;
        background: linear-gradient(135deg, #E85A20 0%, #FF8C42 100%);
        border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; color: #fff; font-size: 1.05rem;
        flex-shrink: 0;
    }

    .sidebar-nav { list-style: none; flex: 1; }
    .sidebar-nav-item { margin-bottom: 0.28rem; }

    .sidebar-nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.68rem 0.95rem;
        border-radius: var(--radius-sm);
        text-decoration: none;
        color: var(--text-gray);
        transition: all 0.25s ease;
        font-weight: 500;
        font-size: 0.88rem;
    }
    .sidebar-nav-link:hover { background: var(--primary-orange-soft); color: var(--primary-orange); }
    .sidebar-nav-link.active { background: var(--primary-orange); color: #fff; }

    .sidebar-nav-icon { font-size: 1.1rem; width: 22px; text-align: center; flex-shrink: 0; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; }

    .sidebar-user {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.8rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: background 0.25s ease;
    }
    .sidebar-user:hover { background: rgba(255, 255, 255, 0.06); }
    .sidebar-user-info { min-width: 0; }
    .sidebar-user-info h4 { font-size: 0.82rem; font-weight: 600; color: var(--text-white); }
    .sidebar-user-info p { font-size: 0.7rem; color: var(--text-gray); }

    .sidebar-logout {
        width: 100%;
        margin-top: 0.55rem;
        padding: 0.6rem;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-gray);
        font-family: inherit;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        transition: all 0.25s ease;
    }
    .sidebar-logout:hover {
        border-color: var(--error-red);
        color: var(--error-red);
        background: rgba(239, 68, 68, 0.08);
    }

    /* Hamburger + overlay (tablet / mobile) */
    .sidebar-hamburger {
        display: none;
        position: fixed;
        top: 0.85rem; left: 0.85rem;
        z-index: 1100;
        width: 42px; height: 42px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        cursor: pointer;
        align-items: center; justify-content: center;
        flex-direction: column; gap: 5px; padding: 10px;
        transition: all 0.25s ease;
    }
    .sidebar-hamburger:hover { border-color: var(--primary-orange); }
    .sidebar-hamburger span {
        display: block; width: 20px; height: 2px;
        background: var(--text-white); border-radius: 2px;
        transition: all 0.25s ease;
    }
    .sidebar-hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
    .sidebar-hamburger.active span:nth-child(2) { opacity: 0; }
    .sidebar-hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }

    .sidebar-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(0, 0, 0, 0.65);
        z-index: 999;
        backdrop-filter: blur(2px);
    }
    .sidebar-overlay.active { display: block; }

    @media (max-width: 1024px) {
        .app-sidebar {
            position: fixed;
            top: 0; left: -280px;
            width: 260px; height: 100vh;
            transition: left 0.3s ease;
        }
        .app-sidebar.open { left: 0; box-shadow: 4px 0 30px rgba(0, 0, 0, 0.55); }
        .sidebar-hamburger { display: flex; }
    }

    @media (max-width: 640px) {
        .app-sidebar { width: 84%; max-width: 300px; left: -100%; }
        .app-sidebar.open { left: 0; }
    }
</style>

<button class="sidebar-hamburger" id="sidebarHamburger" aria-label="Buka menu" aria-expanded="false">
    <span></span><span></span><span></span>
</button>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="app-sidebar" id="appSidebar">
    <a href="{{ route('dashboard') }}" class="sidebar-logo">
        <div class="sidebar-logo-icon">C</div>
        <span>CITRA</span>
    </a>

    <ul class="sidebar-nav">
        @foreach ($navItems as $item)
            <li class="sidebar-nav-item">
                <a href="{{ route($item['route']) }}"
                   class="sidebar-nav-link {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*') ? 'active' : '' }}">
                    <span class="sidebar-nav-icon">{{ $item['icon'] }}</span>
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="sidebar-footer">
        <a href="{{ route('profile') }}" class="sidebar-user">
            <div class="avatar avatar-sm">
                @if ($navUser?->avatar_url)
                    <img src="{{ $navUser->avatar_url }}" alt="{{ $navUser->name }}">
                @else
                    {{ $navUser?->initial ?? 'U' }}
                @endif
            </div>
            <div class="sidebar-user-info">
                <h4 class="truncate">{{ $navUser?->name ?? 'Pengguna' }}</h4>
                <p>{{ $navUser?->level ?? 'Pemula' }}</p>
            </div>
        </a>

        {{-- Logout was missing entirely from the old sidebar: signed-in users
             had no way out of the app. --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-logout">
                <span>🚪</span> Keluar
            </button>
        </form>
    </div>
</aside>

<script>
(function () {
    const hamburger = document.getElementById('sidebarHamburger');
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!hamburger || !sidebar || !overlay) return;

    function setOpen(open) {
        sidebar.classList.toggle('open', open);
        overlay.classList.toggle('active', open);
        hamburger.classList.toggle('active', open);
        hamburger.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open && window.innerWidth <= 1024 ? 'hidden' : '';
    }

    hamburger.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
    overlay.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });

    sidebar.querySelectorAll('.sidebar-nav-link').forEach((link) => {
        link.addEventListener('click', () => { if (window.innerWidth <= 1024) setOpen(false); });
    });

    // Reset the mobile drawer state when resizing back up to desktop.
    window.addEventListener('resize', () => { if (window.innerWidth > 1024) setOpen(false); });
})();
</script>
