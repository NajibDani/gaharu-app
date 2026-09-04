<x-guest-layout>

    {{-- Session Status --}}
    @if(session('status'))
        <div class="alert-status">{{ session('status') }}</div>
    @endif

    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-subtitle">Sign in to your account to continue</p>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Username --}}
        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input
                id="username"
                class="form-input"
                type="text"
                name="username"
                value="{{ old('username') }}"
                placeholder="Enter your username"
                required
                autofocus
                autocomplete="username"
            >
            @error('username')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <div style="position: relative;">
                <input
                    id="password"
                    class="form-input"
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                    style="padding-right: 40px;"
                >
                <button type="button" id="togglePassword" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: rgba(255, 255, 255, 0.7); display: flex; align-items: center; justify-content: center; padding: 0;">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                        <line x1="2" y1="2" x2="22" y2="22"></line>
                    </svg>
                </button>
            </div>
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        {{-- Remember & Forgot --}}
        <div class="check-row">
            <label class="check-label">
                <input type="checkbox" name="remember">
                Remember me
            </label>
            <!-- @if(Route::has('password.request'))
                <a class="forgot-link" href="{{ route('password.request') }}">
                    Forgot password?
                </a>
            @endif -->
        </div>

        {{-- Submit --}}
        <button type="submit" class="btn-primary">
            Log in
        </button>

    </form>

    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        });
    </script>
</x-guest-layout>