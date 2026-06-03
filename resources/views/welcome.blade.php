<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RedWork — Conecta talento y oportunidades</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        :root{
            --bg:#070707;
            --bg-soft:#111111;
            --card:#141414;
            --line:rgba(255,255,255,.08);
            --text:#ffffff;
            --muted:#b5b5b5;
            --red:#d90429;
            --red-soft:#ef233c;
            --white:#ffffff;
        }

        html{
            scroll-behavior:smooth;
        }

        body{
            background:var(--bg);
            color:var(--text);
            font-family:'Inter',sans-serif;
            overflow-x:hidden;
            position:relative;
        }

        body::before{
            content:'';
            position:fixed;
            inset:0;
            background:
                radial-gradient(circle at top left, rgba(217,4,41,.18), transparent 30%),
                radial-gradient(circle at bottom right, rgba(255,255,255,.04), transparent 25%);
            pointer-events:none;
            z-index:-2;
        }

        .noise{
            position:fixed;
            inset:0;
            opacity:.03;
            background-image:url('https://grainy-gradients.vercel.app/noise.svg');
            pointer-events:none;
            z-index:-1;
        }

        nav{
            position:fixed;
            top:0;
            left:0;
            right:0;
            z-index:999;
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:1.2rem 4rem;
            backdrop-filter:blur(18px);
            background:rgba(7,7,7,.55);
            border-bottom:1px solid rgba(255,255,255,.05);
        }

        .logo{
        display:flex;
        align-items:center;
        text-decoration:none;
        }
        .logo-img{
        height:60px;
        width:auto;
        object-fit:contain;
        box-shadow: 0px 4px 10px white;
        transition:transform .3s ease, filter .3s ease;
        filter:drop-shadow(0 0 12px rgba(255,0,0,.25));
        }

        .logo:hover .logo-img{
        transform:scale(1.05);
        filter:drop-shadow(0 0 18px rgba(255,0,0,.45));
        }
        

        .nav-actions{
            display:flex;
            align-items:center;
            gap:.8rem;
        }

        .login-btn,
        .register-btn{
            text-decoration:none;
            border-radius:999px;
            transition:.3s ease;
            font-weight:600;
        }

        .login-btn{
            padding:.85rem 1.4rem;
            color:#fff;
            border:1px solid rgba(255,255,255,.1);
            background:rgba(255,255,255,.03);
        }

        .login-btn:hover{
            background:rgba(255,255,255,.08);
            transform:translateY(-2px);
        }

        .register-btn{
            padding:.9rem 1.7rem;
            color:#fff;
            background:linear-gradient(135deg,var(--red),#ef233c);
            box-shadow:0 12px 35px rgba(217,4,41,.35);
        }

        .register-btn:hover{
            transform:translateY(-3px) scale(1.02);
            box-shadow:0 18px 45px rgba(217,4,41,.45);
        }

        .hero{
            min-height:100vh;
            display:grid;
            grid-template-columns:1.1fr .9fr;
            align-items:center;
            padding:9rem 6rem 4rem;
            gap:3rem;
            position:relative;
        }

        .hero-left{
            position:relative;
            z-index:3;
        }

        .badge{
            display:inline-flex;
            align-items:center;
            gap:.6rem;
            padding:.6rem 1rem;
            border-radius:999px;
            background:rgba(255,255,255,.04);
            border:1px solid rgba(255,255,255,.07);
            margin-bottom:2rem;
            color:#d8d8d8;
            font-size:.9rem;
        }

        .dot{
            width:10px;
            height:10px;
            border-radius:50%;
            background:#ff304f;
            box-shadow:0 0 18px #ff304f;
            animation:pulse 2s infinite;
        }

        @keyframes pulse{
            0%,100%{transform:scale(1);opacity:1}
            50%{transform:scale(1.6);opacity:.4}
        }

        h1{
            font-family:'Outfit',sans-serif;
            font-size:clamp(3.7rem,7vw,7rem);
            line-height:.95;
            letter-spacing:-.06em;
            margin-bottom:2rem;
        }

        h1 span{
            display:block;
            background:linear-gradient(180deg,#fff,#cfcfcf);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }

        .accent{
            color:#ff304f;
            -webkit-text-fill-color:#ff304f;
        }

        .hero p{
            color:var(--muted);
            max-width:640px;
            line-height:1.9;
            font-size:1.08rem;
            margin-bottom:2.5rem;
        }

        .hero-buttons{
            display:flex;
            gap:1rem;
            flex-wrap:wrap;
            margin-bottom:3rem;
        }

        .primary-btn,
        .secondary-btn{
            padding:1rem 1.8rem;
            border-radius:18px;
            text-decoration:none;
            font-weight:600;
            transition:.3s ease;
        }

        .primary-btn{
            background:linear-gradient(135deg,var(--red),#ff4d6d);
            color:white;
            box-shadow:0 14px 40px rgba(217,4,41,.35);
        }

        .primary-btn:hover{
            transform:translateY(-4px);
        }

        .secondary-btn{
            border:1px solid rgba(255,255,255,.1);
            color:white;
            background:rgba(255,255,255,.03);
        }

        .secondary-btn:hover{
            background:rgba(255,255,255,.08);
        }

        .hero-stats{
            display:flex;
            gap:2rem;
            flex-wrap:wrap;
        }

        .stat h3{
            font-size:2.2rem;
            font-family:'Outfit',sans-serif;
            margin-bottom:.3rem;
        }

        .stat span{
            color:#8f8f8f;
            font-size:.95rem;
        }

        .hero-right{
            position:relative;
            height:720px;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .sphere{
            position:absolute;
            width:520px;
            height:520px;
            border-radius:50%;
            background:radial-gradient(circle at 30% 30%, rgba(255,255,255,.18), rgba(217,4,41,.08), transparent 70%);
            filter:blur(8px);
            animation:float 7s ease-in-out infinite;
        }

        @keyframes float{
            0%,100%{transform:translateY(0px)}
            50%{transform:translateY(-25px)}
        }

        .dashboard{
            width:100%;
            max-width:580px;
            border-radius:32px;
            background:linear-gradient(180deg, rgba(20,20,20,.95), rgba(10,10,10,.95));
            border:1px solid rgba(255,255,255,.08);
            padding:1.4rem;
            position:relative;
            z-index:5;
            box-shadow:0 40px 120px rgba(0,0,0,.65);
            overflow:hidden;
            animation:dashboardFloat 6s ease-in-out infinite;
        }

        @keyframes dashboardFloat{
            0%,100%{transform:rotate(-2deg) translateY(0)}
            50%{transform:rotate(1deg) translateY(-12px)}
        }

        .dashboard::before{
            content:'';
            position:absolute;
            inset:0;
            background:linear-gradient(120deg, transparent, rgba(255,255,255,.05), transparent);
            transform:translateX(-100%);
            animation:shine 6s linear infinite;
        }

        @keyframes shine{
            to{transform:translateX(100%)}
        }

        .dash-top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:1.5rem;
        }

        .dash-title{
            font-weight:700;
            font-size:1.1rem;
        }

        .live{
            color:#7ef29a;
            font-size:.9rem;
        }

        .grid{
            display:grid;
            grid-template-columns:repeat(2,1fr);
            gap:1rem;
            margin-bottom:1rem;
        }

        .card{
            background:rgba(255,255,255,.03);
            border:1px solid rgba(255,255,255,.05);
            border-radius:22px;
            padding:1.4rem;
            position:relative;
            overflow:hidden;
        }

        .card::after{
            content:'';
            position:absolute;
            inset:auto -20% -40% auto;
            width:160px;
            height:160px;
            background:radial-gradient(circle, rgba(217,4,41,.25), transparent 70%);
        }

        .card small{
            color:#9b9b9b;
            display:block;
            margin-bottom:1rem;
        }

        .card h2{
            font-size:2.4rem;
            font-family:'Outfit',sans-serif;
        }

        .mini-list{
            display:flex;
            flex-direction:column;
            gap:.8rem;
            margin-top:1rem;
        }

        .mini-item{
            padding:1rem;
            border-radius:16px;
            background:rgba(255,255,255,.03);
            border:1px solid rgba(255,255,255,.05);
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .mini-item span{
            color:#a8a8a8;
            font-size:.92rem;
        }

        .tag{
            padding:.4rem .8rem;
            border-radius:999px;
            background:rgba(217,4,41,.14);
            color:#ff4d6d;
            font-size:.75rem;
        }

        .section{
            padding:7rem 6rem;
            position:relative;
        }

        .section-title{
            text-align:center;
            margin-bottom:4rem;
        }

        .section-title h2{
            font-family:'Outfit',sans-serif;
            font-size:clamp(2.7rem,5vw,5rem);
            line-height:1;
            letter-spacing:-.05em;
            margin-bottom:1rem;
        }

        .section-title p{
            color:#9f9f9f;
            max-width:700px;
            margin:auto;
            line-height:1.8;
        }

        .features{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:1.4rem;
        }

        .feature-card{
            background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.01));
            border:1px solid rgba(255,255,255,.06);
            border-radius:28px;
            padding:2rem;
            transition:.4s ease;
            position:relative;
            overflow:hidden;
        }

        .feature-card:hover{
            transform:translateY(-10px);
            border-color:rgba(217,4,41,.3);
        }

        .feature-icon{
            width:60px;
            height:60px;
            border-radius:18px;
            background:linear-gradient(135deg,var(--red),#ff4d6d);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:1.4rem;
            margin-bottom:1.5rem;
            box-shadow:0 10px 30px rgba(217,4,41,.3);
        }

        .feature-card h3{
            font-size:1.4rem;
            margin-bottom:1rem;
        }

        .feature-card p{
            color:#9f9f9f;
            line-height:1.8;
        }

        .cta-section{
            padding:8rem 2rem;
            text-align:center;
        }

        .cta-box{
            max-width:1100px;
            margin:auto;
            background:linear-gradient(180deg, rgba(217,4,41,.12), rgba(255,255,255,.02));
            border:1px solid rgba(255,255,255,.08);
            border-radius:40px;
            padding:5rem 3rem;
            position:relative;
            overflow:hidden;
        }

        .cta-box::before{
            content:'';
            position:absolute;
            width:500px;
            height:500px;
            border-radius:50%;
            background:radial-gradient(circle, rgba(217,4,41,.25), transparent 70%);
            top:-200px;
            right:-100px;
        }

        .cta-box h2{
            font-family:'Outfit',sans-serif;
            font-size:clamp(3rem,6vw,5.5rem);
            line-height:.95;
            letter-spacing:-.05em;
            margin-bottom:1.4rem;
            position:relative;
            z-index:2;
        }

        .cta-box p{
            max-width:700px;
            margin:auto;
            color:#c4c4c4;
            line-height:1.8;
            margin-bottom:2.4rem;
            position:relative;
            z-index:2;
        }

        footer{
            padding:2rem 4rem;
            border-top:1px solid rgba(255,255,255,.05);
            display:flex;
            justify-content:space-between;
            color:#8d8d8d;
            flex-wrap:wrap;
            gap:1rem;
        }

        .reveal{
            opacity:0;
            transform:translateY(40px);
            transition:1s ease;
        }

        .reveal.active{
            opacity:1;
            transform:translateY(0);
        }

        @media(max-width:1100px){
            .hero{
                grid-template-columns:1fr;
                padding:8rem 2rem 4rem;
            }

            .hero-right{
                height:auto;
            }

            .features{
                grid-template-columns:1fr;
            }

            .section{
                padding:5rem 2rem;
            }

            nav{
                padding:1rem 1.5rem;
            }

            footer{
                padding:2rem;
            }
        }

        @media(max-width:700px){
            h1{
                font-size:3.3rem;
            }

            .hero-buttons,
            .hero-stats{
                flex-direction:column;
                align-items:flex-start;
            }

            .grid{
                grid-template-columns:1fr;
            }

            .dashboard{
                padding:1rem;
            }
        }
    </style>
</head>
<body>

<div class="noise"></div>

<nav>
    <a href="/" class="logo">
    <img src="{{ asset('images/redwork-logo.png') }}" alt="RedWork Logo" class="logo-img">
</a>

    @if (Route::has('login'))
        <div class="nav-actions">
            @auth
                <a href="{{ url('/dashboard') }}" class="register-btn">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="login-btn">Login</a>

                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="register-btn">Register</a>
                @endif
            @endauth
        </div>
    @endif
</nav>

<section class="hero">
    <div class="hero-left">
        <div class="badge reveal">
            <div class="dot"></div>
            Plataforma profesional para trabajadores y contratadores
        </div>

        <h1 class="reveal">
            <span>Conecta talento</span>
            <span class="accent">más rápido.</span>
        </h1>

        <p class="reveal">
            RedWork reúne trabajadores, empresas y oportunidades en una sola plataforma moderna. Publica empleos, encuentra profesionales, organiza procesos y conecta con las personas correctas en tiempo real.
        </p>

        <div class="hero-buttons reveal">
            <a href="#" class="primary-btn">Explorar oportunidades</a>
            <a href="#features" class="secondary-btn">Ver funcionalidades</a>
        </div>

        <div class="hero-stats reveal">
            <div class="stat">
                <h3>+12K</h3>
                <span>Profesionales activos</span>
            </div>

            <div class="stat">
                <h3>+4K</h3>
                <span>Contrataciones realizadas</span>
            </div>

            <div class="stat">
                <h3>24/7</h3>
                <span>Conexiones en tiempo real</span>
            </div>
        </div>
    </div>

    <div class="hero-right reveal">
        <div class="sphere"></div>

        <div class="dashboard">
            <div class="dash-top">
                <div class="dash-title">Panel RedWork</div>
                <div class="live">● En línea</div>
            </div>

            <div class="grid">
                <div class="card">
                    <small>Vacantes publicadas</small>
                    <h2>128</h2>
                </div>

                <div class="card">
                    <small>Postulaciones hoy</small>
                    <h2>+342</h2>
                </div>
            </div>

            <div class="mini-list">
                <div class="mini-item">
                    <span>Diseñador UI Senior</span>
                    <div class="tag">Urgente</div>
                </div>

                <div class="mini-item">
                    <span>Desarrollador Laravel</span>
                    <div class="tag">Remoto</div>
                </div>

                <div class="mini-item">
                    <span>Especialista Marketing</span>
                    <div class="tag">Tiempo completo</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="features">
    <div class="section-title reveal">
        <h2>Todo lo necesario para conectar trabajo y talento</h2>
        <p>
            Una experiencia moderna diseñada para acelerar procesos, reducir tiempos de búsqueda y facilitar la comunicación entre trabajadores y contratadores.
        </p>
    </div>

    <div class="features">
        <div class="feature-card reveal">
            <div class="feature-icon">⚡</div>
            <h3>Conexiones rápidas</h3>
            <p>
                Encuentra oportunidades laborales y perfiles profesionales en segundos con filtros inteligentes y navegación optimizada.
            </p>
        </div>

        <div class="feature-card reveal">
            <div class="feature-icon">📄</div>
            <h3>Perfiles profesionales</h3>
            <p>
                Trabajadores y empresas pueden mostrar experiencia, habilidades y proyectos de manera clara y profesional.
            </p>
        </div>

        <div class="feature-card reveal">
            <div class="feature-icon">🔴</div>
            <h3>Interacción en tiempo real</h3>
            <p>
                Comunicación instantánea entre contratadores y postulantes para acelerar decisiones y oportunidades.
            </p>
        </div>
    </div>
</section>

<section class="cta-section">
    <div class="cta-box reveal">
        <h2>Impulsa tu próximo trabajo con RedWork</h2>

        <p>
            Una plataforma elegante, rápida y profesional para crear conexiones laborales reales entre trabajadores y contratadores.
        </p>

        @if (Route::has('register'))
            <a href="{{ route('register') }}" class="register-btn">Comenzar ahora</a>
        @endif
    </div>
</section>

<footer>
    <div>RedWork</div>
    <div>© {{ date('Y') }} · Plataforma profesional de conexión laboral</div>
</footer>

<script>
    const reveals = document.querySelectorAll('.reveal');

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if(entry.isIntersecting){
                entry.target.classList.add('active');
            }
        });
    },{ threshold:.15 });

    reveals.forEach(el => observer.observe(el));

    document.addEventListener('mousemove', (e) => {
        const sphere = document.querySelector('.sphere');

        const x = (window.innerWidth / 2 - e.pageX) / 40;
        const y = (window.innerHeight / 2 - e.pageY) / 40;

        sphere.style.transform = `translate(${x}px, ${y}px)`;
    });
</script>
</body>
</html>
