<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 1.6 (cobertura complementaria de QA): el mensaje de fechanac se
 * renderiza JUNTO al input (no en otra parte de la página), la fecha se
 * conserva y los formatos no Y-m-d estrictos se rechazan.
 */
class RegistrationAgeFormTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_VALIDA = 'Clave-Segura-2026!';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00'));
    }

    private function datos(string $fechanac): array
    {
        return [
            'cedula'                => 4450001,
            'nombre'                => 'Persona Formulario',
            'email'                 => 'persona.formulario@example.com',
            'rol'                   => 'trabajador',
            'fechanac'              => $fechanac,
            'password'              => self::CLAVE_VALIDA,
            'password_confirmation' => self::CLAVE_VALIDA,
        ];
    }

    /** Fragmento HTML desde el input fechanac hasta el cierre de su form-group. */
    private function bloqueFechanac(string $html): string
    {
        $ok = preg_match('/<input\b[^>]*\bname="fechanac"[^>]*>.*?<\/div>/s', $html, $m);
        $this->assertSame(1, $ok, 'No se encontró el bloque del input fechanac.');

        return $m[0];
    }

    public static function casosRechazados(): array
    {
        return [
            'menor de edad' => ['2010-05-05', 'Debes tener al menos 18 años para registrarte.'],
            'fecha futura'  => ['2027-01-15', 'La fecha de nacimiento no puede ser una fecha futura.'],
            'hoy'           => ['2026-11-07', 'Debes tener al menos 18 años para registrarte.'],
            'cumple mañana' => ['2008-11-08', 'Debes tener al menos 18 años para registrarte.'],
        ];
    }

    #[DataProvider('casosRechazados')]
    public function test_mensaje_aparece_bajo_el_input_y_se_conserva_la_fecha(string $fecha, string $mensaje): void
    {
        $response = $this->from('/register')->followingRedirects()
            ->post('/register', $this->datos($fecha));

        $response->assertOk();
        $bloque = $this->bloqueFechanac($response->getContent());

        $this->assertStringContainsString('value="'.$fecha.'"', $bloque);
        $this->assertStringContainsString('<li>'.e($mensaje).'</li>', $bloque);
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_mensaje_de_fecha_invalida_aparece_bajo_el_input(): void
    {
        $response = $this->from('/register')->followingRedirects()
            ->post('/register', $this->datos('2008-2-5'));

        $response->assertOk();
        $this->assertStringContainsString(
            '<li>'.e('La fecha de nacimiento no es una fecha válida.').'</li>',
            $this->bloqueFechanac($response->getContent())
        );
        $this->assertSame(0, User::count());
    }

    public static function formatosNoEstrictos(): array
    {
        return [
            'mes y dia sin cero' => ['2008-2-5'],
            'dia sin cero'       => ['1990-05-5'],
            'formato ISO con T'  => ['1990-01-01T00:00'],
            'anio de 5 cifras'   => ['10000-01-01'],
            'anio negativo'      => ['-0001-01-01'],
        ];
    }

    #[DataProvider('formatosNoEstrictos')]
    public function test_formatos_no_estrictos_son_rechazados(string $fecha): void
    {
        $this->from('/register')->post('/register', $this->datos($fecha))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('fechanac');

        $this->assertSame(
            ['La fecha de nacimiento no es una fecha válida.'],
            session('errors')->get('fechanac')
        );
        $this->assertSame(0, User::count());
    }

    public function test_registro_valido_no_muestra_error_de_fechanac(): void
    {
        $this->post('/register', $this->datos('1990-01-01'))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertSame('1990-01-01', Carbon::parse(User::find(4450001)->fechanac)->toDateString());
    }
}
