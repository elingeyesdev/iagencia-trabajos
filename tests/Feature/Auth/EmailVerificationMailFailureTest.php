<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Tarea 1.4: un fallo del transporte de correo (p. ej. SMTP bloqueado) no debe
 * provocar 500 en registro, reenvío ni cambio de email; se informa al usuario
 * en español y se registra el fallo sin datos sensibles.
 * El transporte que falla es un mailer de prueba en memoria: no hay red.
 */
class EmailVerificationMailFailureTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAJE = 'No se pudo enviar el correo de verificación';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::extend('fallido', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Conexión rechazada (simulada).');
            }

            public function __toString(): string
            {
                return 'fallido://';
            }
        });

        config([
            'mail.mailers.fallido' => ['transport' => 'fallido'],
            'mail.default' => 'fallido',
        ]);
        Mail::purge();

        Log::spy();
    }

    private function assertFalloRegistradoSinDatosSensibles(int $veces = 1): void
    {
        Log::shouldHaveReceived('warning')->times($veces)->withArgs(function ($mensaje, $contexto = []) {
            return $mensaje === 'No se pudo enviar el correo de verificación.'
                && array_keys($contexto) === ['exception']
                && $contexto['exception'] === TransportException::class;
        });
    }

    public function test_registro_con_fallo_de_correo_no_da_500_y_deja_usuario_autenticado(): void
    {
        Role::firstOrCreate(['name' => 'contratador', 'guard_name' => 'web']);

        $response = $this->post('/register', [
            'cedula' => 5550001,
            'nombre' => 'Usuario Sin Correo',
            'email' => 'sincorreo@example.com',
            'fechanac' => '1990-01-01',
            'password' => 'Clave-Segura-2026!',
            'password_confirmation' => 'Clave-Segura-2026!',
            'rol' => 'contratador',
        ]);

        $response->assertRedirect(route('verification.notice'))
            ->assertSessionHas('status', 'verification-link-failed');

        $user = User::find(5550001);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('contratador'));
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertFalloRegistradoSinDatosSensibles();

        $this->get(route('verification.notice'))->assertOk()->assertSee(self::MENSAJE);
    }

    public function test_reenvio_con_fallo_de_correo_no_da_500_y_muestra_mensaje(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/verify-email')->post('/email/verification-notification')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', 'verification-link-failed');

        $this->assertFalloRegistradoSinDatosSensibles();

        $this->actingAs($user)->get('/verify-email')->assertOk()->assertSee(self::MENSAJE);
    }

    public function test_cambio_de_email_con_fallo_de_correo_guarda_y_muestra_mensaje(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->nombre,
            'email' => 'nuevo-correo@example.com',
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'profile-updated')
            ->assertSessionHas('verification-link-failed', true);

        $user->refresh();
        $this->assertSame('nuevo-correo@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertFalloRegistradoSinDatosSensibles();

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee(self::MENSAJE);
    }

    public function test_reenvio_desde_perfil_con_fallo_muestra_mensaje_en_perfil(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from('/profile')->post('/email/verification-notification')
            ->assertRedirect('/profile')
            ->assertSessionHas('status', 'verification-link-failed');

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee(self::MENSAJE);
    }
}
