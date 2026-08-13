{{-- Session flash messages and validation errors. --}}

@if (session('success'))
    <div class="alert alert-success" role="status">
        <span>✅</span>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-error" role="alert">
        <span>⚠️</span>
        <div>{{ session('error') }}</div>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">
        <span>⚠️</span>
        <div>{{ session('warning') }}</div>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info" role="status">
        <span>ℹ️</span>
        <div>{{ session('info') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-error" role="alert">
        <span>⚠️</span>
        <div>
            <strong>Periksa kembali isian berikut:</strong>
            <ul style="margin: 0.4rem 0 0 1.1rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
