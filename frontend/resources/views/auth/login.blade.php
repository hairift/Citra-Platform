<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CITRA - Masuk</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-orange: #E85A20;
            --bg-dark: #0D0D0D;
            --bg-card: #1A1A1A;
            --text-white: #FFFFFF;
            --text-gray: #A0A0A0;
            --error-red: #EF4444;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #1a0a05 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .auth-container {
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border-radius: 24px;
            padding: 2.5rem;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: var(--text-white);
            text-decoration: none;
        }
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #E85A20 0%, #FF8C42 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
        }
        .auth-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-white);
        }
        .auth-subtitle {
            text-align: center;
            color: var(--text-gray);
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-white);
        }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: var(--text-white);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            background: rgba(255,255,255,0.08);
        }
        .form-input::placeholder {
            color: var(--text-gray);
        }
        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-orange) 0%, #FF8C42 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(232, 90, 32, 0.4);
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--text-gray);
            font-size: 0.8rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.1);
        }
        .divider span {
            padding: 0 1rem;
        }
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-gray);
            font-size: 0.9rem;
        }
        .auth-footer a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: 500;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-red);
            color: var(--error-red);
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        .forgot-link {
            display: block;
            text-align: right;
            color: var(--primary-orange);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            text-decoration: none;
        }
        .forgot-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <a href="{{ url('/') }}" class="logo">
            <div class="logo-icon">C</div>
            <span>CITRA</span>
        </a>

        <h1 class="auth-title">Selamat Datang Kembali</h1>
        <p class="auth-subtitle">Masuk untuk melanjutkan belajar tari topeng</p>

        @if ($errors->any())
            <div class="error-message">
                @foreach ($errors->all() as $error)
                    {{ $error }}<br>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" placeholder="nama@email.com" value="{{ old('email') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" placeholder="Masukkan password" required>
                <a href="#" class="forgot-link">Lupa password?</a>
            </div>
            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>

        <div class="divider"><span>atau</span></div>

        <div class="auth-footer">
            Belum punya akun? <a href="{{ route('register') }}">Daftar Sekarang</a>
        </div>
    </div>
</body>
</html>