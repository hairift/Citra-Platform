{{-- Shared design system for every authenticated CITRA page. --}}
<style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    :root {
        --primary-orange: #E85A20;
        --primary-orange-hover: #FF6B2E;
        --primary-orange-soft: rgba(232, 90, 32, 0.12);
        --bg-dark: #0D0D0D;
        --bg-card: #1A1A1A;
        --bg-card-hover: #252525;
        --border: rgba(255, 255, 255, 0.07);
        --text-white: #FFFFFF;
        --text-gray: #A0A0A0;
        --text-dim: #6B6B6B;
        --success-green: #22C55E;
        --warning-yellow: #EAB308;
        --error-red: #EF4444;
        --info-blue: #3B82F6;
        --purple: #A855F7;
        --gold: #FFD700;
        --silver: #C0C0C0;
        --bronze: #CD7F32;
        --radius: 16px;
        --radius-sm: 10px;
    }

    html { scroll-behavior: smooth; }

    body {
        font-family: 'Poppins', system-ui, -apple-system, sans-serif;
        background-color: var(--bg-dark);
        color: var(--text-white);
        min-height: 100vh;
        -webkit-font-smoothing: antialiased;
    }

    a { color: inherit; }

    ::-webkit-scrollbar { width: 10px; height: 10px; }
    ::-webkit-scrollbar-track { background: var(--bg-dark); }
    ::-webkit-scrollbar-thumb { background: #2E2E2E; border-radius: 5px; }
    ::-webkit-scrollbar-thumb:hover { background: #3E3E3E; }

    /* ===================== LAYOUT ===================== */
    .app-layout {
        display: grid;
        grid-template-columns: 260px minmax(0, 1fr);
        min-height: 100vh;
    }

    .app-main {
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100vh;
        overflow-y: auto;
    }

    .app-content { padding: 1.75rem 2rem 3rem; flex: 1; }

    .container-narrow { max-width: 1100px; margin: 0 auto; }
    .container-wide { max-width: 1400px; margin: 0 auto; }

    /* ===================== TYPOGRAPHY ===================== */
    .page-header { margin-bottom: 1.75rem; }
    .page-header h1 { font-size: 1.7rem; font-weight: 700; margin-bottom: 0.25rem; }
    .page-header p { color: var(--text-gray); font-size: 0.92rem; }

    .section-title {
        font-size: 1.05rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .muted { color: var(--text-gray); }
    .dim { color: var(--text-dim); }
    .text-success { color: var(--success-green); }
    .text-warning { color: var(--warning-yellow); }
    .text-error { color: var(--error-red); }
    .text-orange { color: var(--primary-orange); }

    /* ===================== CARDS / PANELS ===================== */
    .panel {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.35rem;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.1rem;
        flex-wrap: wrap;
    }

    .panel-link {
        color: var(--primary-orange);
        font-size: 0.83rem;
        text-decoration: none;
        font-weight: 500;
        white-space: nowrap;
    }
    .panel-link:hover { text-decoration: underline; }

    /* ===================== GRIDS ===================== */
    .grid { display: grid; gap: 1.25rem; }
    .grid-stats { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .grid-2 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
    .grid-3 { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .grid-main { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); }

    @media (max-width: 1100px) {
        .grid-main { grid-template-columns: minmax(0, 1fr); }
    }

    /* ===================== STAT CARD ===================== */
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem;
        transition: transform 0.25s ease, border-color 0.25s ease;
    }
    .stat-card:hover { transform: translateY(-3px); border-color: rgba(232, 90, 32, 0.35); }

    .stat-icon {
        width: 46px; height: 46px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.35rem;
        margin-bottom: 0.9rem;
    }
    .stat-icon.orange { background: rgba(232, 90, 32, 0.15); }
    .stat-icon.green  { background: rgba(34, 197, 94, 0.15); }
    .stat-icon.blue   { background: rgba(59, 130, 246, 0.15); }
    .stat-icon.purple { background: rgba(168, 85, 247, 0.15); }
    .stat-icon.yellow { background: rgba(234, 179, 8, 0.15); }

    .stat-value { font-size: 1.85rem; font-weight: 800; line-height: 1.1; }
    .stat-label { color: var(--text-gray); font-size: 0.82rem; margin-top: 0.15rem; }
    .stat-sub { color: var(--text-dim); font-size: 0.75rem; margin-top: 0.35rem; }

    /* ===================== BUTTONS ===================== */
    .btn {
        padding: 0.7rem 1.35rem;
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-weight: 600;
        font-size: 0.88rem;
        cursor: pointer;
        transition: all 0.25s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .btn-primary { background: var(--primary-orange); color: #fff; }
    .btn-primary:hover:not(:disabled) { background: var(--primary-orange-hover); }

    .btn-secondary { background: var(--bg-card-hover); color: #fff; border: 1px solid var(--border); }
    .btn-secondary:hover:not(:disabled) { background: #303030; }

    .btn-success { background: var(--success-green); color: #fff; }
    .btn-danger  { background: var(--error-red); color: #fff; }
    .btn-danger:hover:not(:disabled) { background: #DC2626; }

    .btn-ghost { background: transparent; color: var(--text-gray); border: 1px solid var(--border); }
    .btn-ghost:hover { color: #fff; border-color: var(--primary-orange); }

    .btn-sm { padding: 0.45rem 0.9rem; font-size: 0.78rem; }
    .btn-block { width: 100%; }

    /* ===================== FORMS ===================== */
    .form-group { margin-bottom: 1.1rem; }
    .form-label {
        display: block;
        font-size: 0.83rem;
        margin-bottom: 0.45rem;
        color: var(--text-gray);
        font-weight: 500;
    }
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 0.72rem 0.95rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-white);
        font-size: 0.92rem;
        font-family: inherit;
        transition: border-color 0.2s ease;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--primary-orange);
        background: rgba(255, 255, 255, 0.08);
    }
    .form-input::placeholder { color: var(--text-dim); }
    .form-input:disabled { opacity: 0.55; cursor: not-allowed; }
    .form-select option { background: var(--bg-card); color: #fff; }
    .form-error { color: var(--error-red); font-size: 0.78rem; margin-top: 0.35rem; }
    .form-hint { color: var(--text-dim); font-size: 0.75rem; margin-top: 0.35rem; }

    .toggle-switch { position: relative; width: 48px; height: 26px; flex-shrink: 0; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
        position: absolute; inset: 0;
        cursor: pointer;
        background: rgba(255, 255, 255, 0.12);
        border-radius: 26px;
        transition: 0.3s;
    }
    .toggle-slider::before {
        content: '';
        position: absolute;
        height: 20px; width: 20px;
        left: 3px; bottom: 3px;
        background: #fff;
        border-radius: 50%;
        transition: 0.3s;
    }
    input:checked + .toggle-slider { background: var(--primary-orange); }
    input:checked + .toggle-slider::before { transform: translateX(22px); }

    input[type="range"] { accent-color: var(--primary-orange); }

    /* ===================== BADGES ===================== */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.7rem;
        border-radius: 999px;
        font-size: 0.73rem;
        font-weight: 600;
    }
    .badge-orange  { background: var(--primary-orange); color: #fff; }
    .badge-soft    { background: rgba(255, 255, 255, 0.08); color: var(--text-gray); }
    .badge-success { background: rgba(34, 197, 94, 0.15); color: var(--success-green); }
    .badge-warning { background: rgba(234, 179, 8, 0.15); color: var(--warning-yellow); }
    .badge-error   { background: rgba(239, 68, 68, 0.15); color: var(--error-red); }
    .badge-info    { background: rgba(59, 130, 246, 0.15); color: var(--info-blue); }

    .score-badge {
        display: inline-block;
        padding: 0.28rem 0.75rem;
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .score-high   { background: rgba(34, 197, 94, 0.14); color: var(--success-green); }
    .score-medium { background: rgba(234, 179, 8, 0.14); color: var(--warning-yellow); }
    .score-low    { background: rgba(239, 68, 68, 0.14); color: var(--error-red); }

    /* ===================== PROGRESS BARS ===================== */
    .progress-bar {
        width: 100%;
        height: 8px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-bar.thin { height: 6px; }
    .progress-fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, var(--primary-orange), #FF8C42);
        transition: width 0.6s ease;
    }
    .progress-fill.wiraga { background: linear-gradient(90deg, #22C55E, #4ADE80); }
    .progress-fill.wirama { background: linear-gradient(90deg, #3B82F6, #60A5FA); }
    .progress-fill.wirasa { background: linear-gradient(90deg, #A855F7, #C084FC); }

    /* ===================== LISTS ===================== */
    .list-item {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        padding: 0.8rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: var(--radius-sm);
        text-decoration: none;
        color: inherit;
        transition: background 0.25s ease;
    }
    a.list-item:hover { background: rgba(255, 255, 255, 0.06); }

    .list-icon {
        width: 42px; height: 42px;
        background: var(--primary-orange-soft);
        border-radius: var(--radius-sm);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .list-body { flex: 1; min-width: 0; }
    .list-title { font-size: 0.88rem; font-weight: 500; }
    .list-meta { font-size: 0.74rem; color: var(--text-gray); }

    .stack { display: flex; flex-direction: column; gap: 0.7rem; }
    .stack-lg { display: flex; flex-direction: column; gap: 1.25rem; }
    .row { display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; }
    .row-between { display: flex; align-items: center; justify-content: space-between; gap: 0.7rem; }

    /* ===================== AVATAR ===================== */
    .avatar {
        border-radius: 12px;
        background: linear-gradient(135deg, var(--primary-orange), #FF8C42);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
        object-fit: cover;
        overflow: hidden;
    }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }
    .avatar-sm { width: 38px; height: 38px; font-size: 0.9rem; }
    .avatar-md { width: 52px; height: 52px; font-size: 1.2rem; border-radius: 14px; }
    .avatar-lg { width: 110px; height: 110px; font-size: 2.7rem; border-radius: 24px; }

    /* ===================== EMPTY STATE ===================== */
    .empty-state {
        text-align: center;
        padding: 2.5rem 1.5rem;
        color: var(--text-gray);
    }
    .empty-state .icon { font-size: 2.75rem; opacity: 0.55; margin-bottom: 0.85rem; }
    .empty-state h4 { color: var(--text-white); font-size: 1rem; margin-bottom: 0.4rem; font-weight: 600; }
    .empty-state p { font-size: 0.85rem; max-width: 420px; margin: 0 auto 1.1rem; line-height: 1.6; }

    /* ===================== ALERTS ===================== */
    .alert {
        padding: 0.9rem 1.1rem;
        border-radius: var(--radius-sm);
        margin-bottom: 1.25rem;
        font-size: 0.87rem;
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
        line-height: 1.55;
    }
    .alert-success { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.4); color: #86EFAC; }
    .alert-error   { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.4); color: #FCA5A5; }
    .alert-warning { background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.4); color: #FDE047; }
    .alert-info    { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.4); color: #93C5FD; }

    /* ===================== TABS / FILTERS ===================== */
    .filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .filter-tab {
        padding: 0.48rem 1.05rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 999px;
        cursor: pointer;
        font-size: 0.82rem;
        font-family: inherit;
        color: var(--text-gray);
        text-decoration: none;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
    .filter-tab:hover { color: #fff; border-color: rgba(232, 90, 32, 0.5); }
    .filter-tab.active {
        background: var(--primary-orange);
        border-color: var(--primary-orange);
        color: #fff;
    }

    /* ===================== TABLE ===================== */
    .data-table { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
    .data-row {
        display: grid;
        align-items: center;
        gap: 0.75rem;
        padding: 0.9rem 1.3rem;
        border-bottom: 1px solid var(--border);
        transition: background 0.2s ease;
    }
    .data-row:last-child { border-bottom: none; }
    .data-row:hover { background: rgba(255, 255, 255, 0.02); }
    .data-row.header {
        background: rgba(255, 255, 255, 0.03);
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--text-gray);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .mobile-label { display: none; color: var(--text-dim); font-size: 0.72rem; margin-right: 0.4rem; }

    /* ===================== PAGINATION ===================== */
    .pagination-wrap { margin-top: 1.5rem; display: flex; justify-content: center; }
    .pagination-wrap nav { display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: center; }
    .pagination-wrap svg { width: 16px; height: 16px; }
    .pagination-wrap a, .pagination-wrap span {
        padding: 0.45rem 0.85rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-gray);
        text-decoration: none;
        font-size: 0.83rem;
        display: inline-flex;
        align-items: center;
    }
    .pagination-wrap a:hover { background: var(--bg-card-hover); color: #fff; }
    .pagination-wrap [aria-current="page"] span,
    .pagination-wrap span[aria-current="page"] {
        background: var(--primary-orange);
        border-color: var(--primary-orange);
        color: #fff;
    }

    /* ===================== CHART ===================== */
    .bar-chart { display: flex; align-items: flex-end; gap: 0.65rem; height: 180px; }
    .bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.45rem; height: 100%; }
    .bar-track {
        flex: 1;
        width: 100%;
        background: rgba(255, 255, 255, 0.04);
        border-radius: 8px 8px 0 0;
        position: relative;
        display: flex;
        align-items: flex-end;
        overflow: hidden;
    }
    .bar-value {
        width: 100%;
        background: linear-gradient(to top, var(--primary-orange), #FF8C42);
        border-radius: 8px 8px 0 0;
        transition: height 0.7s cubic-bezier(0.22, 1, 0.36, 1);
        min-height: 3px;
    }
    .bar-value.today { background: linear-gradient(to top, var(--success-green), #4ADE80); }
    .bar-value.zero { background: rgba(255, 255, 255, 0.07); }
    .bar-label { font-size: 0.7rem; color: var(--text-gray); }
    .bar-score { font-size: 0.68rem; color: var(--text-dim); }

    /* ===================== UTILITIES ===================== */
    .mt-0 { margin-top: 0; } .mt-1 { margin-top: 0.5rem; } .mt-2 { margin-top: 1rem; }
    .mt-3 { margin-top: 1.5rem; } .mt-4 { margin-top: 2rem; }
    .mb-1 { margin-bottom: 0.5rem; } .mb-2 { margin-bottom: 1rem; } .mb-3 { margin-bottom: 1.5rem; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .flex-1 { flex: 1; }
    .w-full { width: 100%; }
    .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nowrap { white-space: nowrap; }
    .hidden { display: none !important; }
    .fw-600 { font-weight: 600; } .fw-700 { font-weight: 700; } .fw-800 { font-weight: 800; }
    .fs-sm { font-size: 0.82rem; } .fs-xs { font-size: 0.73rem; } .fs-lg { font-size: 1.15rem; }

    /* ===================== RESPONSIVE ===================== */
    @media (max-width: 1024px) {
        .app-layout { grid-template-columns: minmax(0, 1fr); }
        .app-main { height: auto; min-height: 100vh; }
        .app-content { padding: 1.25rem 1rem 3rem; }
    }

    @media (max-width: 768px) {
        .page-header h1 { font-size: 1.35rem; }
        .stat-value { font-size: 1.5rem; }
        .grid { gap: 1rem; }
        .panel { padding: 1.1rem; }
        .mobile-label { display: inline; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
    }
</style>
