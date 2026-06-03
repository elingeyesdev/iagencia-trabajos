<x-guest-layout>

<style>
body{
    background:#060d14 !important;
}

.register-wrapper{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px 20px;

    background:
    radial-gradient(circle at top left, rgba(255,90,95,.08), transparent 40%),
    radial-gradient(circle at bottom right, rgba(45,212,191,.06), transparent 40%),
    #060d14;
}

.register-card{
    width:100%;
    max-width:900px;

    background:rgba(11,18,30,.92);

    border:1px solid rgba(255,255,255,.06);

    border-radius:26px;

    padding:40px;

    backdrop-filter:blur(18px);

    box-shadow:
    0 25px 60px rgba(0,0,0,.45);
}

.logo{
    text-align:center;
    margin-bottom:35px;
}

.logo img{
    height:58px;
}

.logo-title{
    font-family:'Bebas Neue';
    font-size:42px;

    background:linear-gradient(
    90deg,
    #ff4d6d,
    #ff7a7f);

    -webkit-background-clip:text;

    -webkit-text-fill-color:transparent;
}

.subtitle{
    color:#64748B;
    font-size:13px;
    letter-spacing:2px;
}

.grid{
    display:grid;

    grid-template-columns:
    repeat(
    auto-fit,
    minmax(260px,1fr));

    gap:18px;
}

.form-group{
    margin-bottom:18px;
}

.form-label{
    display:block;

    color:#64748B;

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:.14em;

    margin-bottom:8px;
}

.form-input{
    width:100%;

    padding:14px 18px;

    background:
    rgba(255,255,255,.04);

    color:white;

    border:1px solid rgba(255,255,255,.08);

    border-radius:14px;

    outline:none;

    transition:.25s;
}

.form-input:focus{
    border-color:#ff5a5f;

    box-shadow:
    0 0 0 3px rgba(255,90,95,.12);
}

.error{
    color:#ff7a7a;
    font-size:13px;
    margin-top:6px;
}

.role-container{
    display:grid;

    grid-template-columns:1fr 1fr;

    gap:16px;
}

.role-radio{
    display:none;
}

.role-card{

    background:
    rgba(255,255,255,.03);

    border:
    2px solid rgba(255,255,255,.08);

    border-radius:18px;

    padding:25px;

    text-align:center;

    cursor:pointer;

    transition:.25s;
}

.role-card:hover{
    transform:translateY(-3px);
}

.role-radio:checked + .role-card{
    border-color:#FF5A5F;

    background:
    rgba(255,90,95,.06);

    box-shadow:
    0 0 25px rgba(255,90,95,.15);
}

.role-icon{
    font-size:40px;
}

.role-title{
    color:white;

    font-weight:700;
}

.role-desc{
    color:#64748B;

    font-size:13px;
}

.password-wrap{
    position:relative;
}

.eye{
    position:absolute;

    right:18px;

    top:50%;

    transform:translateY(-50%);

    cursor:pointer;
}

.btn{
    width:100%;

    padding:16px;

    border:none;

    border-radius:14px;

    background:
    linear-gradient(
    135deg,
    #FF5A5F,
    #ff7878);

    color:white;

    font-weight:700;

    cursor:pointer;

    transition:.25s;

    margin-top:25px;
}

.btn:hover{
    transform:translateY(-2px);
}

.footer{
    text-align:center;
    margin-top:20px;
}

.footer a{
    color:#2DD4BF;
}

@media(max-width:700px){

.role-container{
grid-template-columns:1fr;
}

.register-card{
padding:30px;
}

}
select.form-input{
appearance:none;
-webkit-appearance:none;

background:
rgba(255,255,255,.04)
url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='white'%3E%3Cpath d='M4 7l5 5 5-5'/%3E%3C/svg%3E")
no-repeat right 16px center;

padding-right:50px;

cursor:pointer;
}

select.form-input option{
background:#0b121e;
color:#fff;
}
</style>

<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;700&display=swap" rel="stylesheet">

<div class="register-wrapper">

<div class="register-card">

<div class="logo">

@if(file_exists(public_path('images/redwork-logo.png')))
<img src="{{ asset('images/redwork-logo.png') }}">
@else
<div class="logo-title">🔧 RED-WORK</div>
@endif

<div class="subtitle">
CREA TU PERFIL
</div>

</div>

<form method="POST" action="{{ route('register') }}">

@csrf

<div class="grid">

<div class="form-group">
<label class="form-label">
Cédula
</label>

<input
class="form-input"
type="number"
name="cedula"
value="{{ old('cedula') }}"
required>

<x-input-error :messages="$errors->get('cedula')" class="error"/>

</div>

<div class="form-group">
<label class="form-label">
Nombre completo
</label>

<input
class="form-input"
name="nombre"
value="{{ old('nombre') }}"
required>

<x-input-error :messages="$errors->get('nombre')" class="error"/>

</div>

<div class="form-group">
<label class="form-label">
Correo
</label>

<input
class="form-input"
type="email"
name="email"
value="{{ old('email') }}"
required>

</div>

<div class="form-group">
<label class="form-label">
Teléfono
</label>

<input
class="form-input"
name="telefono"
value="{{ old('telefono') }}">

</div>

<div class="form-group">
<label class="form-label">
Dirección
</label>

<input
class="form-input"
name="direccion">

</div>

<div class="form-group">

<label class="form-label">
Departamento
</label>

<select
name="ciudad"
class="form-input"
required>

<option value="">
Selecciona tu departamento
</option>

<option value="Santa Cruz"
{{ old('ciudad')=='Santa Cruz' ? 'selected' : '' }}>
Santa Cruz
</option>

<option value="La Paz"
{{ old('ciudad')=='La Paz' ? 'selected' : '' }}>
La Paz
</option>

<option value="Cochabamba"
{{ old('ciudad')=='Cochabamba' ? 'selected' : '' }}>
Cochabamba
</option>

<option value="Tarija"
{{ old('ciudad')=='Tarija' ? 'selected' : '' }}>
Tarija
</option>

<option value="Chuquisaca"
{{ old('ciudad')=='Chuquisaca' ? 'selected' : '' }}>
Chuquisaca
</option>

<option value="Potosí"
{{ old('ciudad')=='Potosí' ? 'selected' : '' }}>
Potosí
</option>

<option value="Oruro"
{{ old('ciudad')=='Oruro' ? 'selected' : '' }}>
Oruro
</option>

<option value="Beni"
{{ old('ciudad')=='Beni' ? 'selected' : '' }}>
Beni
</option>

<option value="Pando"
{{ old('ciudad')=='Pando' ? 'selected' : '' }}>
Pando
</option>

</select>

<x-input-error
:messages="$errors->get('ciudad')"
class="error"/>

</div>

<div class="form-group">
<label class="form-label">
Nacimiento
</label>

<input
class="form-input"
type="date"
name="fechanac">

</div>

</div>

<br>

<label class="form-label">
¿Cómo quieres unirte?
</label>

<div class="role-container">

<label>

<input
class="role-radio"
type="radio"
name="rol"
value="trabajador">

<div class="role-card">

<div class="role-icon">
🔧
</div>

<div class="role-title">
Trabajador
</div>

<div class="role-desc">
Ofrece tus servicios
</div>

</div>

</label>

<label>

<input
class="role-radio"
type="radio"
name="rol"
value="contratador">

<div class="role-card">

<div class="role-icon">
💼
</div>

<div class="role-title">
Contratador
</div>

<div class="role-desc">
Publica empleos
</div>

</div>

</label>

</div>

<br>

<div class="grid">

<div class="password-wrap">

<label class="form-label">
Contraseña
</label>

<input
id="password"
class="form-input"
type="password"
name="password"
required>

<span class="eye"
onclick="togglePassword()">
👁️
</span>

</div>

<div class="password-wrap">

<label class="form-label">
Confirmar contraseña
</label>

<input
id="password2"
class="form-input"
type="password"
name="password_confirmation"
required>

</div>

</div>

<button class="btn">
Crear cuenta →
</button>

<div class="footer">

¿Ya tienes cuenta?

<a href="{{ route('login') }}">
Iniciar sesión
</a>

</div>

</form>

</div>

</div>

<script>

function togglePassword(){

let p=document.getElementById("password");

p.type=
p.type==="password"
?
"text"
:
"password";

}

</script>

</x-guest-layout>