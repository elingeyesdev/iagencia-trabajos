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
 * Tarea 1.6: mayoría de edad (18 años) en el registro web (/register).
 * La fecha actual se fija con travelTo() para que las pruebas sean deterministas.
 */
class RegistrationAgeTest extends TestCase
{
    use RefreshDatabase;

    private const CLAVE_VALIDA = 'Clave-Segura-2026!';

    private const MSG_OBLIGATORIA = 'La fecha de nacimiento es obligatoria.';
    private const MSG_INVALIDA = 'La fecha de nacimiento no es una fecha válida.';
    private const MSG_FUTURA = 'La fecha de nacimiento no puede ser una fecha futura.';
    private const MSG_MENOR = 'Debes tener al menos 18 años para registrarte.';

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00'));
    }

    private function datos(?string $fechanac): array
    {
        $datos = [
            'cedula'                => 4440001,
            'nombre'                => 'Persona Prueba',
            'email'                 => 'persona.prueba@example.com',
            'rol'                   => 'trabajador',
            'password'              => self::CLAVE_VALIDA,
            'password_confirmation' => self::CLAVE_VALIDA,
        ];

        if ($fechanac !== null) {
            $datos['fechanac'] = $fechanac;
        }

        return $datos;
    }

    private function assertRechazado(?string $fechanac, string $mensaje): void
    {
        $this->from('/register')->post('/register', $this->datos($fechanac))
            ->assertRedirect('/register')
            ->assertSessionHasErrors('fechanac');

        // Un único mensaje (bail), en español y en el campo fechanac.
        $this->assertSame([$mensaje], session('errors')->get('fechanac'));
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    private function assertAceptado(string $fechanac): void
    {
        $this->post('/register', $this->datos($fechanac))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::find(4440001);
        $this->assertNotNull($user);
        $this->assertSame($fechanac, Carbon::parse($user->fechanac)->toDateString());
        $this->assertAuthenticatedAs($user);
    }

    // ── Ausente / inválida / futura ──

    public function test_fechanac_ausente_es_rechazada(): void
    {
        $this->assertRechazado(null, self::MSG_OBLIGATORIA);
    }

    public function test_fechanac_vacia_es_rechazada(): void
    {
        $this->assertRechazado('', self::MSG_OBLIGATORIA);
    }

    public static function fechasInvalidas(): array
    {
        return [
            'texto'              => ['abc'],
            '30 de febrero'      => ['2008-02-30'],
            '29-feb no bisiesto' => ['2007-02-29'],
            'mes 13'             => ['1990-13-01'],
            'formato d/m/Y'      => ['15/05/1990'],
            'con hora'           => ['1990-05-15 10:00:00'],
        ];
    }

    #[DataProvider('fechasInvalidas')]
    public function test_fechanac_invalida_es_rechazada(string $fecha): void
    {
        $this->assertRechazado($fecha, self::MSG_INVALIDA);
    }

    public function test_fechanac_futura_es_rechazada_con_mensaje_propio(): void
    {
        $this->assertRechazado('2026-11-08', self::MSG_FUTURA);
        $this->assertRechazado('2030-01-01', self::MSG_FUTURA);
    }

    public function test_fechanac_igual_a_hoy_es_rechazada_por_edad(): void
    {
        $this->assertRechazado('2026-11-07', self::MSG_MENOR);
    }

    // ── Límite de 18 años (hoy = 2026-11-07) ──

    public function test_17_anos_es_rechazado(): void
    {
        $this->assertRechazado('2009-01-01', self::MSG_MENOR);
    }

    public function test_18_anos_menos_un_dia_es_rechazado(): void
    {
        // Cumple 18 mañana (2026-11-08).
        $this->assertRechazado('2008-11-08', self::MSG_MENOR);
    }

    public function test_cumple_exactamente_18_hoy_es_aceptado(): void
    {
        $this->assertAceptado('2008-11-07');
    }

    public function test_cumplio_18_ayer_es_aceptado(): void
    {
        $this->assertAceptado('2008-11-06');
    }

    public function test_mayor_de_18_es_aceptado(): void
    {
        $this->assertAceptado('1985-03-20');
    }

    public function test_un_dia_antes_y_el_dia_del_cumpleanos_18(): void
    {
        $this->travelTo(Carbon::parse('2026-11-06 23:59:59'));
        $this->assertRechazado('2008-11-07', self::MSG_MENOR);

        $this->travelTo(Carbon::parse('2026-11-07 00:00:00'));
        $this->assertAceptado('2008-11-07');
    }

    // ── 29 de febrero ──

    public function test_nacido_29_feb_cumple_18_el_1_de_marzo_en_ano_no_bisiesto(): void
    {
        $this->travelTo(Carbon::parse('2026-02-28 12:00:00'));
        $this->assertRechazado('2008-02-29', self::MSG_MENOR);

        $this->travelTo(Carbon::parse('2026-03-01 12:00:00'));
        $this->assertAceptado('2008-02-29');
    }

    public function test_hoy_29_feb_bisiesto_no_acepta_a_quien_cumple_18_el_1_de_marzo(): void
    {
        // Con subYears() (con desborde) el límite sería 2010-03-01 y se aceptaría un día antes.
        $this->travelTo(Carbon::parse('2028-02-29 12:00:00'));
        $this->assertRechazado('2010-03-01', self::MSG_MENOR);
    }

    public function test_hoy_29_feb_bisiesto_acepta_a_quien_cumplio_18_el_28_feb(): void
    {
        $this->travelTo(Carbon::parse('2028-02-29 12:00:00'));
        $this->assertAceptado('2010-02-28');
    }

    // ── Formulario ──

    public function test_mensaje_de_edad_se_muestra_en_el_formulario_y_conserva_la_fecha(): void
    {
        $this->from('/register')->followingRedirects()
            ->post('/register', $this->datos('2010-05-05'))
            ->assertOk()
            ->assertSee(self::MSG_MENOR)
            ->assertSee('value="2010-05-05"', false);

        $this->assertSame(0, User::count());
    }
}
