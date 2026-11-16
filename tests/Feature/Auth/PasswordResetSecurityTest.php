<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Tarea 1.2: el restablecimiento de contraseña debe escribir en `usuarios.contrasena`
 * y conservar las protecciones del broker (token válido, de un solo uso).
 */
class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const NUEVA = 'Nueva-Clave-2026!';

    private function resetear(User $user, string $token, string $password = self::NUEVA, ?string $confirmacion = null)
    {
        return $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $confirmacion ?? $password,
        ]);
    }

    public function test_reset_guarda_hash_en_contrasena_y_permite_login(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->resetear($user, $token)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $user->refresh();
        $this->assertNotSame(self::NUEVA, $user->contrasena);
        $this->assertTrue(Hash::check(self::NUEVA, $user->contrasena));
        $this->assertFalse(Hash::check('password', $user->contrasena));

        $this->post('/login', ['email' => $user->email, 'password' => self::NUEVA]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_token_invalido_no_cambia_la_contrasena(): void
    {
        $user = User::factory()->create();
        Password::createToken($user);
        $hashAnterior = $user->contrasena;

        $this->resetear($user, 'token-falso')
            ->assertSessionHasErrors('email');

        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    public function test_token_no_puede_reutilizarse(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->resetear($user, $token)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->resetear($user, $token, 'Otra-Clave-2026!')
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::NUEVA, $user->refresh()->contrasena));
    }

    public function test_confirmacion_distinta_es_rechazada_sin_cambiar_contrasena(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $hashAnterior = $user->contrasena;

        $this->resetear($user, $token, self::NUEVA, 'Distinta-Clave-2026!')
            ->assertSessionHasErrors('password');

        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }
}
