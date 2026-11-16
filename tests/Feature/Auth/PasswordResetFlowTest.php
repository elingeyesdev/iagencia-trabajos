<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tarea 1.2 (QA): flujo completo de restablecimiento con correo simulado.
 * El token se obtiene de la notificación ResetPassword capturada por Notification::fake(),
 * por lo que ninguna prueba envía correo real.
 */
class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private const NUEVA = 'Nueva-Clave-2026!';

    private function solicitarToken(User $user): string
    {
        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });
        Notification::assertSentToTimes($user, ResetPassword::class, 1);

        return $token;
    }

    public function test_token_del_correo_simulado_restablece_y_guarda_hash_en_contrasena(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;

        $token = $this->solicitarToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NUEVA,
            'password_confirmation' => self::NUEVA,
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $user->refresh();
        $this->assertNotSame($hashAnterior, $user->contrasena);
        $this->assertNotSame(self::NUEVA, $user->contrasena);
        $this->assertTrue(Hash::check(self::NUEVA, $user->contrasena));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->post('/login', ['email' => $user->email, 'password' => self::NUEVA]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_token_se_almacena_hasheado_y_no_en_texto_plano(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $token = $this->solicitarToken($user);

        $guardado = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotNull($guardado);
        $this->assertNotSame($token, $guardado);
        $this->assertTrue(Hash::check($token, $guardado));
    }

    public function test_token_expirado_es_rechazado_sin_cambiar_contrasena(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;

        $token = $this->solicitarToken($user);

        // config/auth.php: passwords.users.expire = 60 minutos.
        $this->travel(61)->minutes();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NUEVA,
            'password_confirmation' => self::NUEVA,
        ])->assertSessionHasErrors('email');

        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    public function test_reset_exitoso_dispara_evento_password_reset(): void
    {
        Notification::fake();
        Event::fake([PasswordReset::class]);
        $user = User::factory()->create();

        $token = $this->solicitarToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NUEVA,
            'password_confirmation' => self::NUEVA,
        ])->assertSessionHasNoErrors();

        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $e) => $e->user->is($user));
    }
}
