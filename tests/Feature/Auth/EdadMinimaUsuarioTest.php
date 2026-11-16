<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Tarea 1.7: validación reutilizable de mayoría de edad (User::reglasFechaNacimiento y User::mensajesFechaNacimiento).
 * Pruebas sin base de datos; la fecha actual se fija con travelTo().
 */
class EdadMinimaUsuarioTest extends TestCase
{
    private function valida(string $fecha, ?string $mensajeEdad = null): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['fechanac' => $fecha],
            ['fechanac' => User::reglasFechaNacimiento()],
            User::mensajesFechaNacimiento('fechanac', $mensajeEdad),
        );
    }

    public function test_reglas_exactas_para_una_fecha_fija(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 10:00:00'));

        $this->assertSame([
            'bail',
            'required',
            'date_format:Y-m-d',
            'before:2026-11-08',
            'before_or_equal:2008-11-07',
        ], User::reglasFechaNacimiento());
    }

    public function test_la_fecha_de_referencia_se_calcula_en_cada_llamada(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07'));
        $primera = User::reglasFechaNacimiento();

        $this->travelTo(Carbon::parse('2030-01-15'));
        $segunda = User::reglasFechaNacimiento();

        $this->assertContains('before_or_equal:2008-11-07', $primera);
        $this->assertContains('before_or_equal:2012-01-15', $segunda);
        $this->assertContains('before:2030-01-16', $segunda);
    }

    public function test_mensajes_por_defecto_del_registro(): void
    {
        $this->assertSame([
            'fechanac.required'        => 'La fecha de nacimiento es obligatoria.',
            'fechanac.date_format'     => 'La fecha de nacimiento no es una fecha válida.',
            'fechanac.before'          => 'La fecha de nacimiento no puede ser una fecha futura.',
            'fechanac.before_or_equal' => 'Debes tener al menos 18 años para registrarte.',
        ], User::mensajesFechaNacimiento());
    }

    public function test_mensaje_de_edad_alternativo_y_atributo_personalizado(): void
    {
        $mensajes = User::mensajesFechaNacimiento('fecha', User::MENSAJE_EDAD_ADMIN);

        $this->assertSame('El usuario debe tener al menos 18 años.', $mensajes['fecha.before_or_equal']);
        $this->assertSame('La fecha de nacimiento es obligatoria.', $mensajes['fecha.required']);
        $this->assertArrayNotHasKey('fechanac.required', $mensajes);
    }

    public function test_menor_de_edad_recibe_el_mensaje_alternativo(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07'));

        $v = $this->valida('2008-11-08', User::MENSAJE_EDAD_ADMIN);

        $this->assertTrue($v->fails());
        $this->assertSame(['El usuario debe tener al menos 18 años.'], $v->errors()->get('fechanac'));
    }

    public function test_cumple_18_hoy_es_aceptado(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07'));

        $this->assertFalse($this->valida('2008-11-07')->fails());
    }

    public function test_hoy_29_feb_el_limite_es_28_feb_sin_desbordar(): void
    {
        // 2028 es bisiesto; 2010 no. Con desbordamiento el límite sería 2010-03-01.
        $this->travelTo(Carbon::parse('2028-02-29'));

        $this->assertContains('before_or_equal:2010-02-28', User::reglasFechaNacimiento());
        $this->assertFalse($this->valida('2010-02-28')->fails());
        $this->assertTrue($this->valida('2010-03-01')->fails());
    }

    public function test_nacido_29_feb_es_mayor_desde_el_1_de_marzo_en_anio_no_bisiesto(): void
    {
        // Nacido 2008-02-29; 2026 no es bisiesto.
        $this->travelTo(Carbon::parse('2026-02-28'));
        $this->assertTrue($this->valida('2008-02-29')->fails());

        $this->travelTo(Carbon::parse('2026-03-01'));
        $this->assertFalse($this->valida('2008-02-29')->fails());
    }

    public function test_fecha_futura_e_invalida(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07'));

        $this->assertSame(
            ['La fecha de nacimiento no puede ser una fecha futura.'],
            $this->valida('2026-11-08')->errors()->get('fechanac'),
        );
        $this->assertSame(
            ['La fecha de nacimiento no es una fecha válida.'],
            $this->valida('07/11/2000')->errors()->get('fechanac'),
        );
        $this->assertSame(
            ['La fecha de nacimiento es obligatoria.'],
            $this->valida('')->errors()->get('fechanac'),
        );
    }
}
