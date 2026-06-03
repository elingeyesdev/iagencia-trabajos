<x-guest-layout>
<style>
    body { background: #060d14 !important; }

    .login-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(ellipse at 20% 20%, rgba(255,90,95,.07) 0%, transparent 50%),
            radial-gradient(ellipse at 80% 80%, rgba(45,212,191,.05) 0%, transparent 50%),
            #060d14;
        font-family: 'DM Sans', sans-serif;
        padding: 2rem;
    }

    .login-card {
        width: 100%;
        max-width: 420px;
        background: rgba(11,18,30,.92);
        border: 1px solid rgba(255,255,255,.07);
        border-radius: 24px;
        padding: 2.5rem 2rem;
        backdrop-filter: blur(20px);
        box-shadow: 0 25px 60px rgba(0,0,0,.5);
    }

    .login-logo {
        text-align: center;
        margin-bottom: 2rem;
    }
    .login-logo img {
        height: 56px;
        width: auto;
        filter: drop-shadow(0 0 12px rgba(255,90,95,.3));
    }
    .login-logo-text {
        font-family: 'Bebas Neue', cursive;
        font-size: 2rem;
        letter-spacing: .1em;
        background: linear-gradient(90deg, #ff4d6d, #ff5a5f, #ff7a7f);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-top: .3rem;
    }
    .login-subtitle {
        font-size: .82rem;
        color: #64748B;
        font-family: 'Space Mono', monospace;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-top: .2rem;
    }

    .form-group { margin-bottom: 1.2rem; }

    .form-label-custom {
        display: block;
        font-family: 'Space Mono', monospace;
        font-size: .62rem;
        letter-spacing: .15em;
        text-transform: uppercase;
        color: #64748B;
        margin-bottom: .5rem;
    }

    .form-input-custom {
        width: 100%;
        padding: .75rem 1rem;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.08);
        border-radius: 12px;
        color: #F8FAFC;
        font-family: 'DM Sans', sans-serif;
        font-size: .92rem;
        outline: none;
        transition: .2s;
    }
    .form-input-custom:focus {
        border-color: rgba(255,90,95,.4);
        background: rgba(255,255,255,.06);
        box-shadow: 0 0 0 3px rgba(255,90,95,.08);
    }
    .form-input-custom::placeholder { color: #475569; }

    .input-wrap { position: relative; }
    .input-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #475569;
        font-size: .9rem;
        pointer-events: none;
    }

    .remember-wrap {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: 1.5rem;
    }
    .remember-wrap input[type="checkbox"] {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        accent-color: #FF5A5F;
        cursor: pointer;
    }
    .remember-wrap label {
        font-size: .82rem;
        color: #64748B;
        cursor: pointer;
    }

    .btn-submit {
        width: 100%;
        padding: .85rem;
        background: linear-gradient(135deg, #FF5A5F, #ff7878);
        color: white;
        font-family: 'DM Sans', sans-serif;
        font-size: .95rem;
        font-weight: 700;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        transition: .25s;
        letter-spacing: .03em;
        box-shadow: 0 8px 25px rgba(255,90,95,.25);
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(255,90,95,.4);
    }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit.loading { opacity: .7; pointer-events: none; }

    .divider {
        display: flex;
        align-items: center;
        gap: .8rem;
        margin: 1.5rem 0;
    }
    .divider-line { flex: 1; height: 1px; background: rgba(255,255,255,.06); }
    .divider-text {
        font-family: 'Space Mono', monospace;
        font-size: .6rem;
        letter-spacing: .15em;
        text-transform: uppercase;
        color: #475569;
    }

    .login-footer {
        text-align: center;
        margin-top: 1.2rem;
    }
    .login-footer a {
        font-size: .84rem;
        color: #64748B;
        text-decoration: none;
        transition: .2s;
    }
    .login-footer a:hover { color: #FF5A5F; }
    .login-footer .register-link {
        color: #2DD4BF;
        font-weight: 600;
    }
    .login-footer .register-link:hover { color: #5eead4; }

    .forgot-link {
        display: block;
        text-align: right;
        font-size: .78rem;
        color: #475569;
        text-decoration: none;
        margin-top: .4rem;
        transition: .2s;
        font-family: 'Space Mono', monospace;
        letter-spacing: .05em;
    }
    .forgot-link:hover { color: #FF5A5F; }

    .alert-error {
        background: rgba(239,68,68,.1);
        border: 1px solid rgba(239,68,68,.2);
        border-radius: 10px;
        padding: .75rem 1rem;
        margin-bottom: 1.2rem;
        font-size: .84rem;
        color: #ff7070;
    }

    .alert-success {
        background: rgba(74,222,128,.1);
        border: 1px solid rgba(74,222,128,.2);
        border-radius: 10px;
        padding: .75rem 1rem;
        margin-bottom: 1.2rem;
        font-size: .84rem;
        color: #4ADE80;
    }

    /* spinner */
    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin .7s linear infinite;
        vertical-align: middle;
        margin-right: .4rem;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&family=Space+Mono&display=swap" rel="stylesheet">

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-logo">
            @if(file_exists(public_path('images/redwork-logo.png')))
                <img src="{{ asset('images/redwork-logo.png') }}" alt="RedWork">
            @else
                <div class="login-logo-text">🔧 RED-WORK</div>
            @endif
            <div class="login-subtitle">Panel de acceso</div>
        </div>

        {{-- Session Status --}}
        @if(session('status'))
            <div class="alert-success">✅ {{ session('status') }}</div>
        @endif

        {{-- Errores --}}
        @if($errors->any())
            <div class="alert-error">
                ❌ {{ $errors->first() }}
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('login') }}" id="loginForm">
            @csrf

            {{-- Email --}}
            <div class="form-group">
                <label for="email" class="form-label-custom">Correo electrónico</label>
                <div class="input-wrap">
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-input-custom"
                        placeholder="tu@correo.com"
                        required
                        autofocus
                        autocomplete="username"
                    >
                    <span class="input-icon">✉️</span>
                </div>
            </div>

            {{-- Password --}}
            <div class="form-group">
                <label for="password" class="form-label-custom">Contraseña</label>
                <div class="input-wrap">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        class="form-input-custom"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                    >
                    <span class="input-icon" id="toggle-pass" onclick="togglePassword()" style="cursor:pointer;pointer-events:all;">👁️</span>
                </div>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="forgot-link">¿Olvidaste tu contraseña?</a>
                @endif
            </div>

            {{-- Remember Me --}}
            <div class="remember-wrap">
                <input id="remember_me" type="checkbox" name="remember">
                <label for="remember_me">Mantener sesión iniciada</label>
            </div>

            {{-- Submit --}}
            <button type="submit" class="btn-submit" id="btn-submit">
                Iniciar Sesión →
            </button>

            {{-- Divider --}}
            @if (Route::has('register'))
            <div class="divider">
                <div class="divider-line"></div>
                <span class="divider-text">¿Sin cuenta?</span>
                <div class="divider-line"></div>
            </div>

            <div class="login-footer">
                <a href="{{ route('register') }}" class="register-link">Crear cuenta gratis</a>
            </div>
            @endif

        </form>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('toggle-pass');
    if (input.type === 'password') {
        input.type = 'text';
        icon.textContent = '🙈';
    } else {
        input.type = 'password';
        icon.textContent = '👁️';
    }
}

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn  = document.getElementById('btn-submit');

    btn.innerHTML = '<span class="spinner"></span> Entrando...';
    btn.classList.add('loading');

    const email    = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email, password })
        });

        const data = await response.json();

        if (response.ok) {
            localStorage.setItem('token', data.token);
            form.submit();
        } else {
            btn.innerHTML = 'Iniciar Sesión →';
            btn.classList.remove('loading');
            showError(data.message || 'Credenciales incorrectas');
        }
    } catch (error) {
        console.error(error);
        btn.innerHTML = 'Iniciar Sesión →';
        btn.classList.remove('loading');
        showError('Error de conexión. Intenta de nuevo.');
    }
});

function showError(msg) {
    let el = document.querySelector('.alert-error');
    if (!el) {
        el = document.createElement('div');
        el.className = 'alert-error';
        document.getElementById('loginForm').insertBefore(el, document.getElementById('loginForm').firstChild);
    }
    el.textContent = '❌ ' + msg;
}
</script>

</x-guest-layout>