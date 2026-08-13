<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CITRA - platform pembelajaran Tari Topeng Cirebon berbasis deep learning dan motion analysis. Dapatkan penilaian Wiraga, Wirama, dan Wirasa secara real-time.">
    <title>CITRA — Pelestarian Tari Topeng Cirebon dengan AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary-orange: #E85A20;
            --primary-orange-hover: #FF6B2E;
            --bg-dark: #0D0D0D;
            --bg-card: #1A1A1A;
            --bg-card-hover: #252525;
            --border: rgba(255,255,255,0.07);
            --text-white: #FFFFFF;
            --text-gray: #A0A0A0;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Poppins', system-ui, sans-serif;
            background: var(--bg-dark);
            color: var(--text-white);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.8rem 1.7rem; border-radius: 10px;
            font-weight: 600; font-size: 0.92rem;
            border: 2px solid transparent; cursor: pointer;
            transition: all 0.28s ease;
        }
        .btn-primary { background: var(--primary-orange); color: #fff; border-color: var(--primary-orange); }
        .btn-primary:hover { background: var(--primary-orange-hover); transform: translateY(-2px); box-shadow: 0 10px 26px rgba(232,90,32,0.35); }
        .btn-outline { border-color: rgba(255,255,255,0.25); color: #fff; }
        .btn-outline:hover { border-color: var(--primary-orange); color: var(--primary-orange); }
        .btn-dark { background: var(--bg-dark); color: #fff; border-color: var(--bg-dark); }
        .btn-dark:hover { background: transparent; color: var(--bg-dark); }

        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 5%;
            background: rgba(13,13,13,0.86);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }
        .logo { display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 1.2rem; }
        .logo-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #E85A20, #FF8C42);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800;
        }
        .nav-links { display: flex; gap: 2rem; list-style: none; }
        .nav-links a { color: var(--text-gray); font-size: 0.9rem; transition: color 0.25s; }
        .nav-links a:hover { color: var(--primary-orange); }
        .nav-buttons { display: flex; gap: 0.65rem; align-items: center; }
        .mobile-menu-btn { display: none; background: none; border: none; color: #fff; font-size: 1.4rem; cursor: pointer; }

        /* ===== HERO ===== */
        .hero {
            display: flex; align-items: center; justify-content: space-between; gap: 3rem;
            padding: 9rem 5% 5rem;
            min-height: 100vh;
        }
        .hero-content { flex: 1; max-width: 620px; }
        .hero h1 { font-size: clamp(2.1rem, 5vw, 3.6rem); font-weight: 800; line-height: 1.15; margin-bottom: 1.2rem; }
        .hero h1 span { color: var(--primary-orange); }
        .hero-description { color: var(--text-gray); font-size: 1.06rem; margin-bottom: 2rem; max-width: 520px; }
        .hero-buttons { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2.5rem; }
        .hero-image { flex-shrink: 0; }
        .dancer-image { width: min(420px, 40vw); height: auto; filter: drop-shadow(0 20px 45px rgba(232,90,32,0.22)); }

        .hero-stats { display: flex; gap: 2.5rem; flex-wrap: wrap; }
        .hero-stat .num { font-size: 1.75rem; font-weight: 800; color: var(--primary-orange); line-height: 1; }
        .hero-stat .lbl { font-size: 0.78rem; color: var(--text-gray); }

        /* ===== SECTIONS ===== */
        .section { padding: 5rem 5%; }
        .section-title { text-align: center; font-size: clamp(1.6rem, 3.5vw, 2.2rem); font-weight: 700; margin-bottom: 0.75rem; }
        .section-sub { text-align: center; color: var(--text-gray); max-width: 620px; margin: 0 auto 3rem; }

        .tech-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(238px, 1fr)); gap: 1.5rem; max-width: 1200px; margin: 0 auto; }
        .tech-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 16px; padding: 1.9rem 1.5rem; text-align: center;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .tech-card:hover { transform: translateY(-6px); border-color: rgba(232,90,32,0.45); }
        .tech-icon { font-size: 2.4rem; margin-bottom: 0.9rem; }
        .tech-card h3 { font-size: 1.05rem; margin-bottom: 0.5rem; }
        .tech-card p { color: var(--text-gray); font-size: 0.87rem; }

        /* ===== KARAKTER ===== */
        .karakter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; max-width: 1200px; margin: 0 auto; }
        .karakter-card {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 16px; padding: 1.7rem 1.25rem; text-align: center;
            transition: all 0.3s ease;
        }
        .karakter-card:hover { transform: translateY(-6px); }
        .karakter-card .ico { font-size: 2.9rem; margin-bottom: 0.75rem; }
        .karakter-card h3 { font-size: 1.1rem; margin-bottom: 0.4rem; }
        .karakter-card p { color: var(--text-gray); font-size: 0.83rem; line-height: 1.6; }
        .karakter-card .tag {
            display: inline-block; margin-top: 0.85rem;
            padding: 0.22rem 0.7rem; border-radius: 999px;
            font-size: 0.7rem; font-weight: 600;
            background: rgba(255,255,255,0.07); color: var(--text-gray);
        }

        /* ===== STEPS ===== */
        .steps-container { display: flex; align-items: center; justify-content: center; gap: 1.5rem; flex-wrap: wrap; max-width: 1000px; margin: 0 auto; }
        .step { text-align: center; flex: 1; min-width: 190px; }
        .step-icon {
            width: 78px; height: 78px; margin: 0 auto 1rem;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center; font-size: 2.1rem;
        }
        .step h4 { font-size: 0.98rem; margin-bottom: 0.35rem; }
        .step p { color: var(--text-gray); font-size: 0.82rem; }
        .step-arrow { font-size: 1.6rem; color: var(--primary-orange); }

        /* ===== ASPEK ===== */
        .aspek-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; max-width: 1100px; margin: 0 auto; }
        .aspek-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 1.8rem; }
        .aspek-card h3 { font-size: 1.15rem; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.5rem; }
        .aspek-card p { color: var(--text-gray); font-size: 0.88rem; line-height: 1.75; }
        .aspek-card .bar { height: 4px; border-radius: 2px; margin-top: 1.1rem; }

        /* ===== CTA ===== */
        .cta-section {
            margin: 4rem 5%;
            padding: 3.5rem 2rem;
            background: linear-gradient(135deg, var(--primary-orange), #FF8C42);
            border-radius: 24px; text-align: center;
        }
        .cta-section h2 { font-size: clamp(1.5rem, 3.5vw, 2.1rem); font-weight: 700; margin-bottom: 0.9rem; }
        .cta-section p { margin-bottom: 1.8rem; opacity: 0.92; max-width: 580px; margin-left: auto; margin-right: auto; }

        /* ===== FOOTER ===== */
        .footer { padding: 2.5rem 5%; text-align: center; border-top: 1px solid var(--border); }
        .footer-copyright { color: var(--text-gray); font-size: 0.85rem; margin-bottom: 0.9rem; }
        .footer-links { display: flex; justify-content: center; gap: 1.75rem; flex-wrap: wrap; }
        .footer-links a { color: var(--primary-orange); font-size: 0.87rem; }
        .footer-links a:hover { text-decoration: underline; }

        /* ===== ANIMATION ===== */
        .fade-in { opacity: 0; transform: translateY(26px); animation: fadeInUp 0.75s ease forwards; }
        @keyframes fadeInUp { to { opacity: 1; transform: none; } }
        .delay-1 { animation-delay: 0.1s; } .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; } .delay-4 { animation-delay: 0.4s; }

        @media (max-width: 900px) {
            .nav-links { display: none; }
            .mobile-menu-btn { display: block; }
            .hero { flex-direction: column; text-align: center; padding-top: 7rem; }
            .hero-description { margin-left: auto; margin-right: auto; }
            .hero-buttons, .hero-stats { justify-content: center; }
            .dancer-image { width: min(300px, 70vw); }
            .step-arrow { transform: rotate(90deg); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
            .fade-in { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="{{ url('/') }}" class="logo">
        <div class="logo-icon">C</div>
        <span>CITRA</span>
    </a>

    <ul class="nav-links">
        <li><a href="#beranda">Beranda</a></li>
        <li><a href="#teknologi">Teknologi</a></li>
        <li><a href="#karakter">Karakter</a></li>
        <li><a href="#cara-kerja">Cara Kerja</a></li>
        <li><a href="{{ route('about') }}">Tentang</a></li>
    </ul>

    <div class="nav-buttons">
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline">Masuk</a>
            <a href="{{ route('register') }}" class="btn btn-primary">Daftar</a>
        @endauth
    </div>

    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">☰</button>
</nav>

{{-- ===== HERO ===== --}}
<section class="hero" id="beranda">
    <div class="hero-content fade-in">
        <h1>Lestarikan Tari Topeng dengan <span>Kecerdasan Buatan.</span></h1>
        <p class="hero-description">
            Belajar Tari Topeng Cirebon secara otentik melalui analisis gerakan
            real-time berbasis deep learning. Dapatkan penilaian Wiraga, Wirama,
            dan Wirasa langsung dari kamera Anda.
        </p>
        <div class="hero-buttons">
            @auth
                <a href="{{ route('practice') }}" class="btn btn-primary">🎭 Mulai Latihan</a>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary">🎭 Mulai Belajar Gratis</a>
            @endauth
            <a href="#cara-kerja" class="btn btn-outline">Lihat Cara Kerja →</a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <div class="num">{{ $platformStats['karakters'] }}</div>
                <div class="lbl">Karakter Topeng</div>
            </div>
            <div class="hero-stat">
                <div class="num">33</div>
                <div class="lbl">Titik Sendi Dilacak</div>
            </div>
            <div class="hero-stat">
                <div class="num">{{ number_format($platformStats['sessions']) }}</div>
                <div class="lbl">Sesi Latihan</div>
            </div>
            <div class="hero-stat">
                <div class="num">{{ number_format($platformStats['minutes']) }}</div>
                <div class="lbl">Menit Dianalisis</div>
            </div>
        </div>
    </div>

    <div class="hero-image fade-in delay-2">
        <img src="{{ asset('img/Orang_nari.png') }}" alt="Penari Topeng Cirebon" class="dancer-image">
    </div>
</section>

{{-- ===== TEKNOLOGI ===== --}}
<section class="section" id="teknologi">
    <h2 class="section-title">Teknologi di Balik CITRA</h2>
    <p class="section-sub">
        Pipeline computer vision dan deep learning yang bekerja langsung di browser
        dan server, tanpa perangkat sensor tambahan.
    </p>

    <div class="tech-grid">
        <div class="tech-card fade-in delay-1">
            <div class="tech-icon">🦴</div>
            <h3>Deteksi Pose Presisi</h3>
            <p>MediaPipe Holistic melacak 33 titik sendi tubuh dan 42 titik tangan pada setiap frame.</p>
        </div>
        <div class="tech-card fade-in delay-2">
            <div class="tech-icon">📐</div>
            <h3>Analisis 12 Sudut Sendi</h3>
            <p>Setiap sudut persendian dibandingkan dengan referensi maestro dengan toleransi terukur.</p>
        </div>
        <div class="tech-card fade-in delay-3">
            <div class="tech-icon">🧠</div>
            <h3>Deep Learning</h3>
            <p>Bi-LSTM mengenali fase gerakan, autoencoder menilai kemiripan dengan gaya maestro.</p>
        </div>
        <div class="tech-card fade-in delay-4">
            <div class="tech-icon">🎵</div>
            <h3>Sinkronisasi Gamelan</h3>
            <p>Deteksi ketukan gong dan kenong untuk menilai ketepatan irama tarian.</p>
        </div>
    </div>
</section>

{{-- ===== ASPEK PENILAIAN ===== --}}
<section class="section" style="background: rgba(255,255,255,0.015);">
    <h2 class="section-title">Tiga Pilar Penilaian Tari</h2>
    <p class="section-sub">
        CITRA menilai dengan kerangka yang sama seperti penilai tari tradisional.
    </p>

    <div class="aspek-grid">
        <div class="aspek-card fade-in delay-1">
            <h3>🦵 Wiraga</h3>
            <p>
                Ketepatan gerak dan bentuk tubuh. Dinilai dari sudut persendian,
                proporsi postur, dan kesesuaian urutan gerakan dengan pakem
                yang diperagakan maestro.
            </p>
            <div class="bar" style="background: linear-gradient(90deg,#22C55E,#4ADE80);"></div>
        </div>
        <div class="aspek-card fade-in delay-2">
            <h3>🥁 Wirama</h3>
            <p>
                Keselarasan gerak dengan irama gamelan. Dinilai dari ketepatan
                aksen gerakan terhadap ketukan dan kesesuaian tempo dengan
                karakter yang dibawakan.
            </p>
            <div class="bar" style="background: linear-gradient(90deg,#3B82F6,#60A5FA);"></div>
        </div>
        <div class="aspek-card fade-in delay-3">
            <h3>🎭 Wirasa</h3>
            <p>
                Penghayatan dan pembawaan karakter. Dinilai dari sikap kepala,
                bukaan badan, dan kestabilan - karena topeng menutup wajah,
                penjiwaan tersampaikan lewat tubuh.
            </p>
            <div class="bar" style="background: linear-gradient(90deg,#A855F7,#C084FC);"></div>
        </div>
    </div>
</section>

{{-- ===== KARAKTER ===== --}}
<section class="section" id="karakter">
    <h2 class="section-title">Lima Karakter Topeng Cirebon</h2>
    <p class="section-sub">
        Panca Wanda - lima wajah yang menggambarkan perjalanan hidup manusia,
        dari kesucian bayi hingga puncak angkara murka.
    </p>

    <div class="karakter-grid">
        @foreach ($karakters as $slug => $meta)
            <div class="karakter-card fade-in delay-{{ min($loop->iteration, 4) }}"
                 style="border-color: {{ $meta['color'] }}33;">
                <div class="ico">{{ $meta['icon'] }}</div>
                <h3 style="color: {{ $meta['color'] }};">{{ $meta['name'] }}</h3>
                <p>{{ $meta['description'] }}</p>
                <span class="tag">{{ $meta['difficulty'] }} · {{ count($meta['gerakan']) }} gerakan</span>
            </div>
        @endforeach
    </div>
</section>

{{-- ===== CARA KERJA ===== --}}
<section class="section" id="cara-kerja" style="background: rgba(255,255,255,0.015);">
    <h2 class="section-title">Cara Kerja CITRA</h2>
    <p class="section-sub">Empat langkah dari kamera hingga umpan balik yang bisa langsung Anda praktikkan.</p>

    <div class="steps-container">
        <div class="step fade-in delay-1">
            <div class="step-icon">📹</div>
            <h4>1. Nyalakan Kamera</h4>
            <p>Berdiri di depan kamera, pastikan seluruh badan terlihat.</p>
        </div>
        <span class="step-arrow">→</span>
        <div class="step fade-in delay-2">
            <div class="step-icon">🦴</div>
            <h4>2. AI Melacak Sendi</h4>
            <p>33 titik sendi dilacak real-time, 12 sudut dihitung tiap frame.</p>
        </div>
        <span class="step-arrow">→</span>
        <div class="step fade-in delay-3">
            <div class="step-icon">⚖️</div>
            <h4>3. Dibandingkan Maestro</h4>
            <p>Pose Anda diadu dengan dataset gerakan maestro asli.</p>
        </div>
        <span class="step-arrow">→</span>
        <div class="step fade-in delay-4">
            <div class="step-icon">💡</div>
            <h4>4. Feedback Instan</h4>
            <p>Koreksi spesifik per sendi, langsung saat Anda menari.</p>
        </div>
    </div>
</section>

{{-- ===== CTA ===== --}}
<section class="cta-section">
    <h2>Siap Melestarikan Warisan Budaya Cirebon?</h2>
    <p>
        Bergabunglah dengan CITRA dan pelajari Tari Topeng dengan panduan AI
        yang menilai setiap gerakan Anda secara objektif.
    </p>
    @auth
        <a href="{{ route('practice') }}" class="btn btn-dark">Lanjutkan Latihan</a>
    @else
        <a href="{{ route('register') }}" class="btn btn-dark">Daftar Sekarang — Gratis</a>
    @endauth
</section>

<footer class="footer">
    <p class="footer-copyright">
        © {{ date('Y') }} CITRA — Pengembangan Platform Berbasis Deep Learning dan
        Motion Analysis untuk Pelestarian Tari Topeng Cirebon
    </p>
    <div class="footer-links">
        <a href="{{ route('about') }}">Tentang</a>
        <a href="#teknologi">Teknologi</a>
        <a href="#karakter">Karakter</a>
        @guest<a href="{{ route('login') }}">Masuk</a>@endguest
    </div>
</footer>

<script>
    // Mobile nav: reveal the links inline rather than leaving the button dead.
    document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
        const links = document.querySelector('.nav-links');
        const shown = links.style.display === 'flex';
        Object.assign(links.style, shown ? { display: '' } : {
            display: 'flex', position: 'absolute', top: '100%', left: 0, right: 0,
            flexDirection: 'column', gap: '0.25rem', padding: '1rem 5%',
            background: 'rgba(13,13,13,0.98)', borderBottom: '1px solid rgba(255,255,255,0.07)',
        });
    });
</script>
</body>
</html>
