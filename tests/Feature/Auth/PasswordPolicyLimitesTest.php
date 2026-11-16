<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 1.5 (verificación QA) — Casos límite complementarios a PasswordPolicyTest:
 * exactamente 12 caracteres válidos, ausencia de minúscula, forma del 422 de la API
 * y uso real de la contraseña creada por el administrador.
 */
class PasswordPolicyLimitesTest extends TestCase
{
    use RefreshDatabase;

    /** Exactamente 12 caracteres: mayúscula, minúsculas, números y símbolo. */
    private const LIMITE_12 = 'Abcdefgh12!x';

    /** 11 caracteres que cumplen todo lo demás. */
    private const LIMITE_11 = 'Abcdefg12!x';

    private const SIN_MINUSCULA = 'CLAVE-SEGURA-2026!';

    private int $siguienteCedula = 8000;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function endpointsCreacionAdmin(): array
    {
        return [
            'admin/usuarios' => ['/api/admin/usuarios'],
            'usuarios'       => ['/api/usuarios'],
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        return $admin;
    }

    private function datosNuevoUsuario(string $password): array
    {
        $cedula = $this->siguienteCedula++;

        return [
            'cedula'   => $cedula,
            'nombre'   => "Usuario {$cedula}",
            'email'    => "limite{$cedula}@redwork.test",
            'fechanac' => '1990-01-01',
            'password' => $password,
            'rol'      => 'trabajador',
        ];
    }

    public function test_las_constantes_tienen_la_longitud_esperada(): void
    {
        $this->assertSame(12, mb_strlen(self::LIMITE_12));
        $this->assertSame(11, mb_strlen(self::LIMITE_11));
    }

    // ── Límite de 12 caracteres ──

    public function test_registro_web_acepta_exactamente_12_caracteres(): void
    {
        $datos = $this->datosNuevoUsuario(self::LIMITE_12) + ['password_confirmation' => self::LIMITE_12];

        $this->post('/register', $datos)->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::LIMITE_12, User::findOrFail($datos['cedula'])->contrasena));
    }

    public function test_registro_web_rechaza_11_caracteres(): void
    {
        $datos = $this->datosNuevoUsuario(self::LIMITE_11) + ['password_confirmation' => self::LIMITE_11];

        $this->post('/register', $datos)->assertSessionHasErrors('password');

        $this->assertContains('La contraseña debe tener al menos 12 caracteres.', session('errors')->get('password'));
        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
        $this->assertGuest();
    }

    #[DataProvider('endpointsCreacionAdmin')]
    public function test_creacion_admin_acepta_exactamente_12_caracteres(string $url): void
    {
        Sanctum::actingAs($this->admin());
        $datos = $this->datosNuevoUsuario(self::LIMITE_12);

        $this->postJson($url, $datos)->assertSuccessful();

        $this->assertTrue(Hash::check(self::LIMITE_12, User::findOrFail($datos['cedula'])->contrasena));
    }

    #[DataProvider('endpointsCreacionAdmin')]
    public function test_creacion_admin_rechaza_11_caracteres(string $url): void
    {
        Sanctum::actingAs($this->admin());
        $datos = $this->datosNuevoUsuario(self::LIMITE_11);

        $this->postJson($url, $datos)
            ->assertStatus(422)
            ->assertJsonPath('errors.password', ['La contraseña debe tener al menos 12 caracteres.']);

        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
    }

    public function test_cambio_de_contrasena_acepta_exactamente_12_caracteres(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password'      => 'password',
            'password'              => self::LIMITE_12,
            'password_confirmation' => self::LIMITE_12,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::LIMITE_12, $user->refresh()->contrasena));
    }

    public function test_restablecimiento_acepta_exactamente_12_caracteres(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token'                 => Password::createToken($user),
            'email'                 => $user->email,
            'password'              => self::LIMITE_12,
            'password_confirmation' => self::LIMITE_12,
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::LIMITE_12, $user->refresh()->contrasena));
    }

    // ── Falta de minúscula (mixedCase exige ambas) ──

    public function test_registro_web_rechaza_contrasena_sin_minuscula(): void
    {
        $datos = $this->datosNuevoUsuario(self::SIN_MINUSCULA) + ['password_confirmation' => self::SIN_MINUSCULA];

        $this->post('/register', $datos)->assertSessionHasErrors('password');

        $this->assertContains(
            'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            session('errors')->get('password')
        );
        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
    }

    public function test_cambio_de_contrasena_rechaza_sin_minuscula_y_no_cambia_hash(): void
    {
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password'      => 'password',
            'password'              => self::SIN_MINUSCULA,
            'password_confirmation' => self::SIN_MINUSCULA,
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    // ── Forma del 422 de la API ──

    #[DataProvider('endpointsCreacionAdmin')]
    public function test_api_422_conserva_message_y_errors_en_espanol(string $url): void
    {
        Sanctum::actingAs($this->admin());
        $datos = $this->datosNuevoUsuario('ClaveSegura2026'); // solo falta el símbolo

        $this->postJson($url, $datos)
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['password']])
            ->assertJsonPath('message', 'La contraseña debe contener al menos un símbolo.')
            ->assertJsonPath('errors.password', ['La contraseña debe contener al menos un símbolo.']);

        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
    }

    // ── La contraseña creada por el administrador es utilizable ──

    public function test_usuario_creado_por_admin_inicia_sesion_api_con_su_contrasena(): void
    {
        Sanctum::actingAs($this->admin());
        $datos = $this->datosNuevoUsuario(self::LIMITE_12);
        $this->postJson('/api/admin/usuarios', $datos)->assertSuccessful();

        $this->app['auth']->forgetGuards();

        $this->postJson('/api/login', ['email' => $datos['email'], 'password' => self::LIMITE_12])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario']);
    }
}
