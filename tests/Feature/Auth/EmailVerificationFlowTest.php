<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 1.4: verificación de correo con mecanismos nativos (Opción A:
 * toda cuenta debe verificar su correo para entrar al dashboard).
 * Correo siempre simulado (Notification::fake o mailer `array`).
 */
class EmailVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function enlace(User $user, ?int $id = null, ?string $email = null, int $minutos = 60): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes($minutos),
            ['id' => $id ?? $user->getKey(), 'hash' => sha1($email ?? $user->email)]
        );
    }

    // ── Registro ──

    public function test_registro_deja_usuario_no_verificado_y_envia_verify_email(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);

        $response = $this->post('/register', [
            'cedula' => 7654321,
            'nombre' => 'Usuario Nuevo',
            'email' => 'nuevo@example.com',
            'fechanac' => '1990-01-01',
            'password' => 'Clave-Segura-2026!',
            'password_confirmation' => 'Clave-Segura-2026!',
            'rol' => 'trabajador',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::find(7654321);
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_notificacion_genera_enlace_firmado_con_cedula_y_hash_del_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/verify-email/'.$user->cedula.'/'.sha1($user->email))
                && str_contains($url, 'signature=')
                && str_contains($url, 'expires=');
        });
        $this->assertFalse($user->verificationNotificationFailed);
    }

    // ── Enlace de verificación ──

    public function test_enlace_valido_persiste_email_verified_at_y_dispara_verified(): void
    {
        $user = User::factory()->unverified()->create();
        Event::fake([Verified::class]);

        $this->actingAs($user)->get($this->enlace($user))
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertDispatchedTimes(Verified::class, 1);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_con_hash_invalido_es_rechazado(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->enlace($user, email: 'otro@example.com'))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_con_firma_alterada_es_rechazado(): void
    {
        $user = User::factory()->unverified()->create();
        $url = str_replace('signature=', 'signature=0', $this->enlace($user));

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_de_otro_usuario_es_rechazado_y_no_verifica_a_ninguno(): void
    {
        $user = User::factory()->unverified()->create();
        $otro = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->enlace($otro))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNull($otro->fresh()->email_verified_at);
    }

    public function test_enlace_caducado_es_rechazado(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->enlace($user);

        $this->travel(61)->minutes();

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_requiere_sesion_iniciada(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->enlace($user))->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_enlace_reutilizado_tras_verificar_redirige_sin_redisparar_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->enlace($user);

        $this->actingAs($user)->get($url)->assertRedirect();
        $verificadoEn = $user->fresh()->email_verified_at;
        $this->assertNotNull($verificadoEn);

        Event::fake([Verified::class]);

        $this->actingAs($user->fresh())->get($url)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertNotDispatched(Verified::class);
        $this->assertEquals($verificadoEn, $user->fresh()->email_verified_at);
    }

    // ── Reenvío ──

    public function test_reenvio_envia_notificacion_a_no_verificado(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_reenvio_esta_limitado_a_seis_por_minuto(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
                ->assertRedirect('/verify-email');
        }

        $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
            ->assertStatus(429);

        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
    }

    public function test_usuario_verificado_que_pide_reenvio_es_redirigido_sin_envio(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/email/verification-notification')
            ->assertRedirect(route('dashboard', absolute: false));

        Notification::assertNothingSent();
    }

    public function test_reenvio_requiere_sesion_iniciada(): void
    {
        Notification::fake();

        $this->post('/email/verification-notification')->assertRedirect(route('login'));

        Notification::assertNothingSent();
    }

    // ── Acceso ──

    public function test_dashboard_redirige_a_no_verificados_a_la_pantalla_de_verificacion(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_dashboard_admite_usuarios_verificados(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_usuario_verificado_en_pantalla_de_verificacion_es_redirigido_al_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/verify-email')->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_no_verificado_puede_iniciar_sesion_y_ver_pantalla_de_verificacion_y_perfil(): void
    {
        $user = User::factory()->unverified()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);

        $this->get('/verify-email')->assertOk();
        $this->get('/profile')->assertOk();
    }

    public function test_pantallas_de_login_registro_y_recuperacion_accesibles(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/token-de-prueba')->assertOk();
    }

    public function test_no_verificado_puede_recuperar_y_restablecer_contrasena(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Nueva-Clave-2026!',
            'password_confirmation' => 'Nueva-Clave-2026!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
    }

    // ── Perfil ──

    public function test_cambio_de_email_en_perfil_anula_verificacion_y_envia_notificacion(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->nombre,
            'email' => 'cambiado@example.com',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('cambiado@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_mismo_email_en_perfil_conserva_verificacion_sin_enviar_correo(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $verificadoEn = $user->fresh()->email_verified_at;
        $this->assertNotNull($verificadoEn);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Otro Nombre',
            'email' => $user->email,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertEquals($verificadoEn, $user->refresh()->email_verified_at);
        Notification::assertNothingSent();
    }
}
