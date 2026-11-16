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
 * Tarea 1.5 — Política de contraseñas nuevas o cambiadas:
 * mínimo 12 caracteres, mayúscula (y minúscula), número y símbolo,
 * con mensajes en español por campo. Las contraseñas existentes siguen siendo válidas.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private const VALIDA = 'Clave-Segura-2026!';

    private int $siguienteCedula = 5000;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    /**
     * Contraseñas débiles y el mensaje en español esperado.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function contrasenasDebiles(): array
    {
        return [
            'menos de 12 caracteres' => ['Clave-2026!', 'La contraseña debe tener al menos 12 caracteres.'],
            'sin mayúscula'          => ['clave-segura-2026!', 'La contraseña debe contener al menos una letra mayúscula y una minúscula.'],
            'sin número'             => ['Clave-Segura-Sin!', 'La contraseña debe contener al menos un número.'],
            'sin símbolo'            => ['ClaveSegura2026', 'La contraseña debe contener al menos un símbolo.'],
            'vacía'                  => ['', 'La contraseña es obligatoria.'],
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
            'cedula' => $cedula,
            'nombre' => "Usuario {$cedula}",
            'email'  => "usuario{$cedula}@redwork.test",
            'fechanac' => '1990-01-01',
            'password' => $password,
            'rol' => 'trabajador',
        ];
    }

    // ── Registro web ──

    #[DataProvider('contrasenasDebiles')]
    public function test_registro_web_rechaza_contrasena_debil(string $password, string $mensaje): void
    {
        $datos = $this->datosNuevoUsuario($password) + ['password_confirmation' => $password];

        $this->from('/register')->post('/register', $datos)
            ->assertRedirect('/register')
            ->assertSessionHasErrors('password');

        $this->assertContains($mensaje, session('errors')->get('password'));
        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
        $this->assertGuest();
    }

    public function test_registro_web_rechaza_confirmacion_distinta(): void
    {
        $datos = $this->datosNuevoUsuario(self::VALIDA) + ['password_confirmation' => 'Clave-Segura-2027!'];

        $this->post('/register', $datos)->assertSessionHasErrors('password');

        $this->assertContains('La confirmación de la contraseña no coincide.', session('errors')->get('password'));
        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
    }

    public function test_registro_web_acepta_contrasena_valida_y_guarda_hash(): void
    {
        $datos = $this->datosNuevoUsuario(self::VALIDA) + ['password_confirmation' => self::VALIDA];

        $this->post('/register', $datos)->assertSessionHasNoErrors();

        $user = User::find($datos['cedula']);
        $this->assertNotNull($user);
        $this->assertNotSame(self::VALIDA, $user->contrasena);
        $this->assertTrue(Hash::check(self::VALIDA, $user->contrasena));
    }

    // ── Creación administrativa (API) ──

    /** @return array<string, array{0: string}> */
    public static function endpointsCreacionAdmin(): array
    {
        return [
            'admin/usuarios' => ['/api/admin/usuarios'],
            'usuarios'       => ['/api/usuarios'],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function debilesPorEndpointAdmin(): array
    {
        $casos = [];
        foreach (self::endpointsCreacionAdmin() as $nombreEndpoint => [$url]) {
            foreach (self::contrasenasDebiles() as $nombreCaso => [$password, $mensaje]) {
                $casos["{$nombreEndpoint} / {$nombreCaso}"] = [$url, $password, $mensaje];
            }
        }

        return $casos;
    }

    #[DataProvider('debilesPorEndpointAdmin')]
    public function test_creacion_admin_rechaza_contrasena_debil(string $url, string $password, string $mensaje): void
    {
        Sanctum::actingAs($this->admin());
        $datos = $this->datosNuevoUsuario($password);

        $this->postJson($url, $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password', fn (array $errores) => in_array($mensaje, $errores, true));

        $this->assertDatabaseMissing('usuarios', ['cedula' => $datos['cedula']]);
    }

    #[DataProvider('endpointsCreacionAdmin')]
    public function test_creacion_admin_acepta_contrasena_valida_y_guarda_hash(string $url): void
    {
        Sanctum::actingAs($this->admin());
        $datos = $this->datosNuevoUsuario(self::VALIDA);

        $this->postJson($url, $datos)->assertSuccessful()->assertJsonMissingPath('data.contrasena');

        $user = User::find($datos['cedula']);
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check(self::VALIDA, $user->contrasena));
    }

    #[DataProvider('endpointsCreacionAdmin')]
    public function test_creacion_admin_sigue_exigiendo_rol_administrador(string $url): void
    {
        $trabajador = User::factory()->create();
        $trabajador->assignRole('trabajador');
        Sanctum::actingAs($trabajador);

        $this->postJson($url, $this->datosNuevoUsuario(self::VALIDA))->assertForbidden();
    }

    public function test_creacion_admin_sin_token_devuelve_401(): void
    {
        $this->postJson('/api/admin/usuarios', $this->datosNuevoUsuario(self::VALIDA))->assertUnauthorized();
    }

    // ── Cambio de contraseña (perfil) ──

    #[DataProvider('contrasenasDebiles')]
    public function test_cambio_de_contrasena_rechaza_contrasena_debil(string $password, string $mensaje): void
    {
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect('/profile')->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertContains($mensaje, session('errors')->getBag('updatePassword')->get('password'));
        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    public function test_cambio_de_contrasena_rechaza_confirmacion_distinta(): void
    {
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => self::VALIDA,
            'password_confirmation' => 'Clave-Segura-2027!',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertContains(
            'La confirmación de la contraseña no coincide.',
            session('errors')->getBag('updatePassword')->get('password')
        );
        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    public function test_cambio_de_contrasena_acepta_contrasena_valida(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')->put('/password', [
            'current_password' => 'password',
            'password' => self::VALIDA,
            'password_confirmation' => self::VALIDA,
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::VALIDA, $user->refresh()->contrasena));
    }

    // ── Recuperación (restablecimiento) ──

    #[DataProvider('contrasenasDebiles')]
    public function test_restablecimiento_rechaza_contrasena_debil(string $password, string $mensaje): void
    {
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        $this->assertContains($mensaje, session('errors')->get('password'));
        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    public function test_restablecimiento_rechaza_confirmacion_distinta(): void
    {
        $user = User::factory()->create();
        $hashAnterior = $user->contrasena;
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::VALIDA,
            'password_confirmation' => 'Clave-Segura-2027!',
        ])->assertSessionHasErrors('password');

        $this->assertContains('La confirmación de la contraseña no coincide.', session('errors')->get('password'));
        $this->assertSame($hashAnterior, $user->refresh()->contrasena);
    }

    public function test_restablecimiento_acepta_contrasena_valida(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::VALIDA,
            'password_confirmation' => self::VALIDA,
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check(self::VALIDA, $user->refresh()->contrasena));
    }

    // ── Contraseñas existentes no se invalidan ──

    public function test_usuario_existente_con_contrasena_antigua_debil_inicia_sesion_web(): void
    {
        $user = User::factory()->create(); // contraseña de fábrica: 'password' (no cumple la política)

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_usuario_existente_con_contrasena_antigua_debil_inicia_sesion_api(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario']);
    }
}
