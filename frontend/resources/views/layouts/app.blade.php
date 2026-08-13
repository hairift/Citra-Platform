<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CITRA') — Pelestarian Tari Topeng Cirebon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @include('partials.styles')
    @stack('styles')
</head>
<body>
<div class="app-layout">
    @include('partials.sidebar')

    <main class="app-main">
        @include('partials.topbar')

        <div class="app-content">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>
</div>

<script>
    window.CITRA = {
        csrf: document.querySelector('meta[name="csrf-token"]').content,
        routes: {
            aiStatus: '{{ url('/api/ai/status') }}',
            notifications: '{{ url('/api/notifications') }}',
            notificationsRead: '{{ url('/api/notifications/read') }}',
        },
    };

    /** POST helper that always sends the CSRF token and parses JSON safely. */
    window.citraPost = async function (url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': window.CITRA.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* non-JSON error page */ }
        return { ok: res.ok, status: res.status, data };
    };

    window.citraGet = async function (url) {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        let data = null;
        try { data = await res.json(); } catch (e) { /* ignore */ }
        return { ok: res.ok, status: res.status, data };
    };
</script>
@include('partials.scripts')
@stack('scripts')
</body>
</html>
