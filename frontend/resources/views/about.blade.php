<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tentang CITRA — platform pelestarian Tari Topeng Cirebon berbasis deep learning dan motion analysis.">
    <title>Tentang CITRA — Pelestarian Tari Topeng Cirebon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-orange: #E85A20;
            --bg-dark: #0D0D0D;
            --bg-card: #1A1A1A;
            --border: rgba(255,255,255,0.07);
            --text-white: #FFFFFF;
            --text-gray: #A0A0A0;
        }
        html { scroll-behavior: smooth; }
        body { font-family: 'Poppins', system-ui, sans-serif; background: var(--bg-dark); color: var(--text-white); line-height: 1.7; }
        a { text-decoration: none; color: inherit; }

        .navbar {
            position: sticky; top: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 5%;
            background: rgba(13,13,13,0.9); backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
        }
        .logo { display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 1.2rem; }
        .logo-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, #E85A20, #FF8C42);
            border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800;
        }
        .nav-links { display: flex; gap: 1.75rem; }
        .nav-links a { color: var(--text-gray); font-size: 0.9rem; }
        .nav-links a:hover, .nav-links a.active { color: var(--primary-orange); }

        .container { max-width: 900px; margin: 0 auto; padding: 3rem 1.5rem 5rem; }

        h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); font-weight: 800; margin-bottom: 0.6rem; }
        h1 span { color: var(--primary-orange); }
        .lead { color: var(--text-gray); font-size: 1.02rem; margin-bottom: 2.5rem; }

        h2 { font-size: 1.35rem; font-weight: 700; margin-bottom: 0.85rem; display: flex; align-items: center; gap: 0.5rem; }
        h3 { font-size: 1.02rem; font-weight: 600; margin: 1.5rem 0 0.5rem; color: var(--primary-orange); }
        h3:first-of-type { margin-top: 0.5rem; }
        p { color: var(--text-gray); margin-bottom: 1rem; }
        ul { color: var(--text-gray); margin: 0 0 1rem 1.3rem; }
        li { margin-bottom: 0.4rem; }
        strong { color: var(--text-white); }
        em { color: var(--text-white); font-style: normal; }
        code {
            background: rgba(255,255,255,0.07); padding: 0.12rem 0.4rem;
            border-radius: 4px; font-size: 0.86em; color: #FFB88C;
        }

        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 1.6rem; margin-bottom: 1.25rem; }

        .karakter-item { display: flex; gap: 1rem; align-items: flex-start; padding: 1rem 0; border-bottom: 1px solid var(--border); }
        .karakter-item:last-child { border-bottom: none; }
        .karakter-item .ico { font-size: 2rem; flex-shrink: 0; line-height: 1.2; }
        .karakter-item h4 { font-size: 1rem; margin-bottom: 0.25rem; }
        .karakter-item p { font-size: 0.88rem; margin-bottom: 0; }

        .stack-badge {
            display: inline-block; padding: 0.28rem 0.75rem;
            background: rgba(255,255,255,0.06); border-radius: 999px;
            font-size: 0.78rem; margin: 0.2rem 0.25rem 0.2rem 0; color: var(--text-gray);
        }

        .btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.75rem 1.5rem; border-radius: 10px;
            font-weight: 600; font-size: 0.9rem;
            background: var(--primary-orange); color: #fff;
            transition: all 0.25s ease;
        }
        .btn:hover { background: #FF6B2E; transform: translateY(-2px); }

        .footer { padding: 2.5rem 5%; text-align: center; border-top: 1px solid var(--border); color: var(--text-gray); font-size: 0.85rem; }

        @media (max-width: 700px) { .nav-links { gap: 1rem; font-size: 0.85rem; } }
        @media (prefers-reduced-motion: reduce) { * { transition-duration: 0.01ms !important; } }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="{{ url('/') }}" class="logo">
        <div class="logo-icon">C</div>
        <span>CITRA</span>
    </a>
    <div class="nav-links">
        <a href="{{ url('/') }}">Beranda</a>
        <a href="{{ route('about') }}" class="active">Tentang</a>
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
        @else
            <a href="{{ route('login') }}">Masuk</a>
        @endauth
    </div>
</nav>

<div class="container">

    <h1>Tentang <span>CITRA</span></h1>
    <p class="lead">
        Pengembangan Platform CITRA Berbasis Deep Learning dan Motion Analysis
        untuk Pelestarian Tari Topeng Cirebon.
    </p>

    <div class="card">
        <h2>🎯 Latar Belakang</h2>
        <p>
            Tari Topeng Cirebon adalah warisan budaya takbenda yang pewarisannya
            selama ini bergantung pada kehadiran langsung seorang maestro. Jumlah
            maestro yang semakin sedikit dan keterbatasan geografis membuat proses
            belajar sulit diakses secara luas.
        </p>
        <p>
            CITRA menjembatani hal tersebut: gerakan maestro direkam, diekstraksi
            menjadi data titik sendi yang presisi, lalu dijadikan acuan penilaian
            otomatis. Pelajar di mana pun dapat berlatih dan menerima koreksi
            spesifik seolah didampingi langsung.
        </p>
    </div>

    <div class="card">
        <h2>🎭 Panca Wanda — Lima Karakter</h2>
        <p>
            Tari Topeng Cirebon menampilkan lima karakter yang melambangkan
            perjalanan hidup manusia:
        </p>
        @foreach ($karakters as $slug => $meta)
            <div class="karakter-item">
                <span class="ico">{{ $meta['icon'] }}</span>
                <div>
                    <h4 style="color: {{ $meta['color'] }};">{{ $meta['name'] }}</h4>
                    <p>{{ $meta['filosofi'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h2>⚖️ Kerangka Penilaian</h2>
        <p>
            CITRA menilai memakai kerangka yang sama dengan penilai tari tradisional,
            diterjemahkan ke dalam besaran yang terukur:
        </p>

        <h3>Wiraga — Ketepatan Gerak</h3>
        <ul>
            <li>12 sudut persendian dibandingkan dengan pose maestro pada waktu yang sama</li>
            <li>Kemiripan bentuk postur setelah normalisasi posisi, skala, dan kemiringan</li>
            <li>Kesesuaian urutan gerakan lewat <em>Dynamic Time Warping</em></li>
        </ul>

        <h3>Wirama — Keselarasan Irama</h3>
        <ul>
            <li>Deteksi ketukan gamelan (gong, kenong) dari sinyal audio</li>
            <li>Ketepatan aksen gerakan terhadap ketukan, dengan toleransi terukur</li>
            <li>Kesesuaian tempo dengan rentang khas tiap karakter</li>
        </ul>

        <h3>Wirasa — Penghayatan</h3>
        <ul>
            <li>Sikap kepala dan arah pandang (penting karena topeng menutup wajah)</li>
            <li>Bukaan badan dan bahu sesuai watak karakter</li>
            <li>Kestabilan dan kemantapan pembawaan</li>
        </ul>
    </div>

    <div class="card">
        <h2>🧠 Arsitektur Teknologi</h2>

        <h3>Ekstraksi Pose</h3>
        <p>
            Setiap video maestro diproses <strong>MediaPipe Holistic</strong> pada
            <code>model_complexity=2</code>, menghasilkan 33 titik sendi tubuh,
            42 titik tangan, dan koordinat 3D metrik per frame. Filter One-Euro
            meredam getaran deteksi tanpa menambah jeda.
        </p>

        <h3>Representasi Fitur</h3>
        <p>
            Pose dinormalisasi terhadap titik tengah pinggul dan panjang torso,
            lalu dirotasi agar tidak terpengaruh kemiringan kamera. Hasilnya
            vektor 63 dimensi yang bernilai sama untuk postur yang sama, terlepas
            dari tinggi badan penari maupun posisinya di dalam frame.
        </p>

        <h3>Model Deep Learning</h3>
        <ul>
            <li><strong>Bi-LSTM</strong> — mengenali fase gerakan dari jendela sekuens pose</li>
            <li><strong>LSTM Autoencoder</strong> — dilatih hanya pada gerakan maestro; galat rekonstruksinya menjadi ukuran seberapa mirip gaya maestro</li>
            <li><strong>1D-CNN</strong> — memperkirakan kecepatan gerak untuk umpan balik tempo</li>
        </ul>

        <h3>Tumpukan Teknologi</h3>
        <div>
            <span class="stack-badge">Laravel 12</span>
            <span class="stack-badge">PHP 8.2+</span>
            <span class="stack-badge">MySQL</span>
            <span class="stack-badge">Flask</span>
            <span class="stack-badge">Socket.IO</span>
            <span class="stack-badge">MediaPipe</span>
            <span class="stack-badge">TensorFlow / Keras</span>
            <span class="stack-badge">OpenCV</span>
            <span class="stack-badge">librosa</span>
            <span class="stack-badge">scikit-learn</span>
        </div>
    </div>

    <div class="card">
        <h2>🔒 Privasi</h2>
        <p>
            Deteksi pose berjalan langsung di peramban Anda. Yang dikirim ke server
            hanyalah koordinat titik sendi dan skor — <strong>bukan rekaman video</strong>.
            Rekaman latihan yang Anda buat lewat tombol rekam tersimpan langsung
            ke perangkat Anda sendiri.
        </p>
    </div>

    <div style="text-align:center; margin-top:2.5rem;">
        @auth
            <a href="{{ route('practice') }}" class="btn">🎭 Mulai Latihan</a>
        @else
            <a href="{{ route('register') }}" class="btn">🎭 Mulai Belajar Gratis</a>
        @endauth
    </div>
</div>

<footer class="footer">
    © {{ date('Y') }} CITRA — Pelestarian Tari Topeng Cirebon
</footer>

</body>
</html>
