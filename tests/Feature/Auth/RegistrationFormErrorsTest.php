<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 1.5.1: errores por campo, conservación de valores (old) y no reenvío
 * de contraseñas en el formulario Blade de registro (/register).
 */
class RegistrationFormErrorsTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_VALIDA = 'Clave-Segura-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'contratador', 'guard_name' => 'web']);
        Notification::fake();
    }

    private function datosValidos(array $cambios = []): array
    {
        return array_merge([
            'cedula'                => 7654321,
            'nombre'                => 'Maria Quispe',
            'email'                 => 'maria.quispe@example.com',
            'telefono'              => '71234567',
            'direccion'             => 'Calle Sucre 123',
            'ciudad'                => 'La Paz',
            'fechanac'              => '1990-05-15',
            'rol'                   => 'contratador',
            'password'              => self::CLAVE_VALIDA,
            'password_confirmation' => self::CLAVE_VALIDA,
        ], $cambios);
    }

    /**
     * 1) Envía el formulario sin seguir redirecciones para leer el texto real
     *    del validador en el bag de errores (sin suponerlo).
     * 2) Reenvía los mismos datos siguiendo la redirección y devuelve la
     *    página /register renderizada con ese bag.
     *
     * @return array{0: string, 1: \Illuminate\Testing\TestResponse}
     */
    private function enviarYRenderizar(array $datos, string $campo): array
    {
        $post = $this->from('/register')->post('/register', $datos);
        $post->assertRedirect('/register');
        $post->assertSessionHasErrors($campo);

        $mensaje = session('errors')->first($campo);
        $this->assertNotSame('', $mensaje);

        $pagina = $this->from('/register')->followingRedirects()->post('/register', $datos);
        $pagina->assertOk();

        return [$mensaje, $pagina];
    }

    /** Devuelve la etiqueta <input ...> completa cuyo name es el indicado. */
    private function etiquetaInput(string $html, string $nombre): string
    {
        $ok = preg_match('/<input\b[^>]*\bname="'.preg_quote($nombre, '/').'"[^>]*>/s', $html, $m);
        $this->assertSame(1, $ok, "No se encontró el input name=\"{$nombre}\".");

        return $m[0];
    }

    // ---------------------------------------------------------------
    // Política de contraseñas: mensajes exactos en español
    // ---------------------------------------------------------------

    public static function contrasenasInvalidas(): array
    {
        return [
            'demasiado corta' => ['Ab1!corta', 'min'],
            'sin mayuscula'   => ['clave-segura-2026!', 'password.mixed'],
            'sin numero'      => ['Clave-Segura-Sin!', 'password.numbers'],
            'sin simbolo'     => ['ClaveSegura2026X', 'password.symbols'],
        ];
    }

    #[DataProvider('contrasenasInvalidas')]
    public function test_contrasena_invalida_muestra_mensaje_exacto_en_espanol(string $clave, string $regla): void
    {
        $esperado = str_replace(':min', '12', User::mensajesContrasena()["password.{$regla}"]);

        $response = $this->from('/register')->followingRedirects()->post('/register', $this->datosValidos([
            'password'              => $clave,
            'password_confirmation' => $clave,
        ]));

        $response->assertOk();
        // assertSee escapa el texto con e(): equivale a lo que imprime {{ $message }}.
        $response->assertSee($esperado);
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_confirmacion_distinta_muestra_mensaje_en_espanol(): void
    {
        $response = $this->from('/register')->followingRedirects()->post('/register', $this->datosValidos([
            'password_confirmation' => 'Otra-Clave-2026!',
        ]));

        $response->assertOk();
        $response->assertSee('La confirmación de la contraseña no coincide.');
        $this->assertSame(0, User::count());
    }

    // ---------------------------------------------------------------
    // Errores de otros campos: el mensaje real del validador se renderiza
    // ---------------------------------------------------------------

    public static function camposInvalidos(): array
    {
        return [
            'email invalido'    => ['email', ['email' => 'no-es-un-correo']],
            'email mayusculas'  => ['email', ['email' => 'Maria@Example.com']],
            'cedula no entera'  => ['cedula', ['cedula' => 'abc']],
            'rol ausente'       => ['rol', ['rol' => null]],
            'rol invalido'      => ['rol', ['rol' => 'administrador']],
            'nombre vacio'      => ['nombre', ['nombre' => '']],
            'fechanac invalida' => ['fechanac', ['fechanac' => 'no-es-fecha']],
            'telefono largo'    => ['telefono', ['telefono' => str_repeat('9', 21)]],
            'direccion larga'   => ['direccion', ['direccion' => str_repeat('d', 256)]],
        ];
    }

    #[DataProvider('camposInvalidos')]
    public function test_error_de_campo_se_muestra_en_el_formulario(string $campo, array $cambios): void
    {
        $datos = $this->datosValidos($cambios);
        $datos = array_filter($datos, fn ($v) => $v !== null);

        [$mensaje, $pagina] = $this->enviarYRenderizar($datos, $campo);

        $pagina->assertSee($mensaje);
        $this->assertSame(0, User::count());
    }

    public function test_email_duplicado_muestra_error(): void
    {
        User::factory()->create(['email' => 'maria.quispe@example.com']);

        [$mensaje, $pagina] = $this->enviarYRenderizar($this->datosValidos(), 'email');

        $pagina->assertSee($mensaje);
        $this->assertSame(1, User::count());
    }

    public function test_cedula_duplicada_muestra_error(): void
    {
        User::factory()->create(['cedula' => 7654321]);

        [$mensaje, $pagina] = $this->enviarYRenderizar($this->datosValidos(), 'cedula');

        $pagina->assertSee($mensaje);
        $this->assertSame(1, User::count());
    }

    // ---------------------------------------------------------------
    // Conservación de valores y no reenvío de contraseñas
    // ---------------------------------------------------------------

    public function test_se_conservan_los_valores_introducidos_tras_un_error(): void
    {
        // Falla solo por la contraseña; el resto de campos son válidos.
        $response = $this->from('/register')->followingRedirects()->post('/register', $this->datosValidos([
            'password'              => 'corta',
            'password_confirmation' => 'corta',
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('value="7654321"', $this->etiquetaInput($html, 'cedula'));
        $this->assertStringContainsString('value="Maria Quispe"', $this->etiquetaInput($html, 'nombre'));
        $this->assertStringContainsString('value="maria.quispe@example.com"', $this->etiquetaInput($html, 'email'));
        $this->assertStringContainsString('value="71234567"', $this->etiquetaInput($html, 'telefono'));
        $this->assertStringContainsString('value="Calle Sucre 123"', $this->etiquetaInput($html, 'direccion'));
        $this->assertStringContainsString('value="1990-05-15"', $this->etiquetaInput($html, 'fechanac'));
        $this->assertMatchesRegularExpression('/<option value="La Paz"\s+selected>/', $html);

        // Rol: solo el elegido queda marcado.
        $this->assertMatchesRegularExpression('/name="rol"\s+value="contratador"\s+checked\s*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="rol"\s+value="trabajador"\s+checked/', $html);
    }

    public function test_rol_trabajador_queda_marcado_tras_un_error(): void
    {
        $response = $this->from('/register')->followingRedirects()->post('/register', $this->datosValidos([
            'rol'   => 'trabajador',
            'email' => 'no-es-un-correo',
        ]));

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/name="rol"\s+value="trabajador"\s+checked\s*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="rol"\s+value="contratador"\s+checked/', $html);
    }

    public function test_las_contrasenas_nunca_se_reenvian_al_formulario(): void
    {
        $secreto = 'Secreto-Unico-99!';

        // Contraseña válida, pero el registro falla por el email.
        $response = $this->from('/register')->followingRedirects()->post('/register', $this->datosValidos([
            'email'                 => 'no-es-un-correo',
            'password'              => $secreto,
            'password_confirmation' => $secreto,
        ]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString($secreto, $html);
        $this->assertStringNotContainsString(e($secreto), $html);

        foreach (['password', 'password_confirmation'] as $nombre) {
            $etiqueta = $this->etiquetaInput($html, $nombre);
            $this->assertDoesNotMatchRegularExpression('/\bvalue="[^"]+"/', $etiqueta, "El input {$nombre} no debe reenviar valor.");
        }

        // Laravel no flashea contraseñas al old input.
        $this->assertNull(session()->getOldInput('password'));
        $this->assertNull(session()->getOldInput('password_confirmation'));
        $this->assertSame(0, User::count());
    }

    // ---------------------------------------------------------------
    // Registro válido
    // ---------------------------------------------------------------

    public function test_registro_valido_crea_usuario_y_lleva_a_verificacion(): void
    {
        $post = $this->from('/register')->post('/register', $this->datosValidos());

        $post->assertSessionHasNoErrors();
        $post->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        // dashboard exige 'verified': redirige al aviso de verificación.
        $this->get(route('dashboard', absolute: false))->assertRedirect(route('verification.notice', absolute: false));

        $user = User::find(7654321);
        $this->assertNotNull($user);
        $this->assertSame('Maria Quispe', $user->nombre);
        $this->assertSame('maria.quispe@example.com', $user->email);
        $this->assertNotSame(self::CLAVE_VALIDA, $user->contrasena);
        $this->assertTrue(Hash::check(self::CLAVE_VALIDA, $user->contrasena));
        $this->assertTrue($user->hasRole('contratador'));
        $this->assertNull($user->email_verified_at);
    }
}
