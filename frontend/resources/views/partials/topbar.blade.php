{{-- Sticky top bar: page title, AI backend status, notification bell. --}}
<style>
    .app-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.85rem 2rem;
        background: rgba(26, 26, 26, 0.92);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border);
        position: sticky;
        top: 0;
        z-index: 900;
    }

    .topbar-title { font-size: 0.95rem; font-weight: 600; }
    .topbar-sub { font-size: 0.75rem; color: var(--text-gray); }
    .topbar-actions { display: flex; align-items: center; gap: 0.65rem; }

    .ai-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.73rem;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-gray);
        white-space: nowrap;
    }
    .ai-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--text-dim); flex-shrink: 0; }
    .ai-pill.online { color: var(--success-green); border-color: rgba(34, 197, 94, 0.35); }
    .ai-pill.online .ai-dot { background: var(--success-green); animation: aiPulse 2.2s infinite; }
    .ai-pill.offline { color: var(--text-dim); }
    @keyframes aiPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }

    .notif-wrap { position: relative; }
    .notif-btn {
        width: 38px; height: 38px;
        border-radius: var(--radius-sm);
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-white);
        cursor: pointer;
        font-size: 1rem;
        display: flex; align-items: center; justify-content: center;
        position: relative;
        transition: all 0.2s ease;
    }
    .notif-btn:hover { border-color: var(--primary-orange); }
    .notif-count {
        position: absolute;
        top: -5px; right: -5px;
        min-width: 18px; height: 18px;
        padding: 0 4px;
        background: var(--error-red);
        border-radius: 9px;
        font-size: 0.65rem;
        font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }

    .notif-panel {
        position: absolute;
        top: calc(100% + 0.6rem);
        right: 0;
        width: 330px;
        max-height: 420px;
        overflow-y: auto;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.55);
        z-index: 950;
        display: none;
    }
    .notif-panel.open { display: block; }
    .notif-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem; font-weight: 600;
    }
    .notif-head button {
        background: none; border: none; cursor: pointer;
        color: var(--primary-orange); font-size: 0.75rem; font-family: inherit;
    }
    .notif-item {
        display: flex; gap: 0.7rem;
        padding: 0.8rem 1rem;
        border-bottom: 1px solid var(--border);
        text-decoration: none; color: inherit;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: rgba(255, 255, 255, 0.03); }
    .notif-item.unread { background: rgba(232, 90, 32, 0.06); }
    .notif-item .icon { font-size: 1.1rem; flex-shrink: 0; }
    .notif-item h5 { font-size: 0.82rem; font-weight: 600; margin-bottom: 0.15rem; }
    .notif-item p { font-size: 0.75rem; color: var(--text-gray); line-height: 1.45; }
    .notif-item time { font-size: 0.68rem; color: var(--text-dim); }

    @media (max-width: 1024px) {
        .app-topbar { padding: 0.85rem 1rem 0.85rem 4rem; }
    }
    @media (max-width: 560px) {
        .ai-pill span.ai-label { display: none; }
        .notif-panel { width: calc(100vw - 2rem); right: -0.5rem; }
    }
</style>

<div class="app-topbar">
    <div>
        <div class="topbar-title">@yield('title', 'CITRA')</div>
        <div class="topbar-sub">@yield('subtitle', 'Pelestarian Tari Topeng Cirebon')</div>
    </div>

    <div class="topbar-actions">
        <span class="ai-pill" id="aiStatusPill" title="Status server AI (Flask + MediaPipe)">
            <span class="ai-dot"></span>
            <span class="ai-label">Mengecek AI…</span>
        </span>

        <div class="notif-wrap">
            <button class="notif-btn" id="notifBtn" aria-label="Notifikasi" aria-expanded="false">
                🔔
                <span class="notif-count hidden" id="notifCount">0</span>
            </button>

            <div class="notif-panel" id="notifPanel">
                <div class="notif-head">
                    <span>Notifikasi</span>
                    <button type="button" id="notifMarkRead">Tandai terbaca</button>
                </div>
                <div id="notifList">
                    <div class="empty-state" style="padding: 1.75rem 1rem;">
                        <p class="fs-sm">Memuat…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const pill = document.getElementById('aiStatusPill');
    const label = pill?.querySelector('.ai-label');
    const btn = document.getElementById('notifBtn');
    const panel = document.getElementById('notifPanel');
    const list = document.getElementById('notifList');
    const countEl = document.getElementById('notifCount');
    const markBtn = document.getElementById('notifMarkRead');

    async function refreshAiStatus() {
        if (!pill) return;
        try {
            const { data } = await window.citraGet(window.CITRA.routes.aiStatus);
            const online = !!(data && data.online);
            pill.classList.toggle('online', online);
            pill.classList.toggle('offline', !online);
            if (label) label.textContent = online ? 'AI aktif' : 'AI nonaktif';
            pill.title = online
                ? 'Server AI berjalan di ' + (data?.data?.time ? 'localhost:5000' : 'localhost:5000')
                : 'Server AI tidak berjalan. Jalankan: cd backend && python app.py';
        } catch (e) {
            pill.classList.add('offline');
            if (label) label.textContent = 'AI nonaktif';
        }
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    async function loadNotifications() {
        if (!list) return;
        const { data } = await window.citraGet(window.CITRA.routes.notifications);
        const items = data?.data ?? [];
        const unread = data?.unread ?? 0;

        countEl.textContent = unread > 9 ? '9+' : String(unread);
        countEl.classList.toggle('hidden', unread === 0);

        if (!items.length) {
            list.innerHTML = '<div class="empty-state" style="padding:1.75rem 1rem;">'
                + '<div class="icon">🔕</div><p class="fs-sm">Belum ada notifikasi.</p></div>';
            return;
        }

        list.innerHTML = items.map((n) => {
            const tag = n.link ? 'a' : 'div';
            const href = n.link ? ` href="${escapeHtml(n.link)}"` : '';
            return `<${tag}${href} class="notif-item ${n.read ? '' : 'unread'}">
                <span class="icon">${escapeHtml(n.icon || '🔔')}</span>
                <div style="min-width:0">
                    <h5>${escapeHtml(n.title)}</h5>
                    ${n.message ? `<p>${escapeHtml(n.message)}</p>` : ''}
                    <time>${escapeHtml(n.ago || '')}</time>
                </div>
            </${tag}>`;
        }).join('');
    }

    btn?.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = panel.classList.toggle('open');
        btn.setAttribute('aria-expanded', String(open));
        if (open) loadNotifications();
    });

    document.addEventListener('click', (e) => {
        if (panel && !panel.contains(e.target) && e.target !== btn) {
            panel.classList.remove('open');
            btn?.setAttribute('aria-expanded', 'false');
        }
    });

    markBtn?.addEventListener('click', async (e) => {
        e.stopPropagation();
        await window.citraPost(window.CITRA.routes.notificationsRead, {});
        loadNotifications();
    });

    refreshAiStatus();
    loadNotifications();
    // Re-probe periodically so starting the Python backend is reflected without
    // the user having to reload the page.
    setInterval(refreshAiStatus, 30000);
})();
</script>
