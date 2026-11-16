<?php

namespace Tests\Feature\Api;

use App\Models\Calificacion;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sistema de KPIs rediseñado (KpiApiController@indicadores/contratador/trabajador).
 * Fecha fija: 2026-06-30. Período por defecto (30d): 2026-06-01 … 2026-06-30;
 * anterior: 2026-05-02 … 2026-05-31. Valores esperados calculados a mano en cada prueba.
 */
class KpiIndicadoresTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = '/api/admin/kpis/indicadores';
    private const CONTR = '/api/contratador/kpis';
    private const TRAB  = '/api/trabajador/kpis';

    private int $cedula = 9100;
    private int $ref = 1;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:00:00'));

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
        DB::table('categorias')->insert([
            ['id_categoria' => 1, 'nombre_categoria' => 'Limpieza', 'estado' => 1],
            ['id_categoria' => 2, 'nombre_categoria' => 'Construcción', 'estado' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Fábricas mínimas ──

    private function usuario(string $rol): User
    {
        $u = User::factory()->create(['cedula' => $this->cedula++]);
        $u->assignRole($rol);

        return $u;
    }

    private function pub(User $c, string $fecha, int $cat = 1, string $titulo = 'Labor'): Publicacion
    {
        return Publicacion::create([
            'tipo' => 'temporal', 'titulo' => $titulo, 'descripcion' => 'D', 'pago' => '100.00', 'lugar' => 'L',
            'fecha_publicacion' => $fecha, 'cedula' => $c->cedula, 'id_categoria' => $cat, 'estado' => 1,
        ]);
    }

    private function postular(Publicacion $p, User $t, string $fecha, string $estado = 'pendiente'): Postulacion
    {
        return Postulacion::create([
            'id_labor' => $p->id_labor, 'cedula' => $t->cedula, 'fecha_postulacion' => $fecha, 'estado' => $estado,
        ]);
    }

    private function contr(Postulacion $po, string $estado = 'activa', ?string $fin = null, string $inicio = '2026-06-01'): Contratacion
    {
        return Contratacion::create([
            'id_postulacion' => $po->id_postulacion, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
            'monto_acordado' => '1000.00', 'estado' => $estado,
        ]);
    }

    private function pago(Contratacion $c, string $monto, string $estado, string $registro, ?string $confirmacion = null): void
    {
        (new Pago)->forceFill([
            'id_contratacion' => $c->id_contratacion, 'monto' => $monto, 'porcentaje_comision' => '0.00',
            'monto_comision' => '0.00', 'monto_neto' => $monto, 'fecha_pago' => substr($registro, 0, 10),
            'metodo' => 'efectivo', 'estado' => $estado, 'referencia_externa' => 'SIM-KPI2-' . $this->ref++,
            'es_simulado' => true, 'proveedor' => 'simulado', 'cedula_registra' => 1,
            'fecha_registro' => $registro, 'fecha_confirmacion' => $confirmacion,
            'cedula_confirma' => $confirmacion ? 1 : null,
        ])->save();
    }

    private function calif(Contratacion $c, int $de, int $para, int $puntos, string $fecha): void
    {
        Calificacion::create([
            'id_contratacion' => $c->id_contratacion, 'cedula_calificador' => $de, 'cedula_calificado' => $para,
            'puntuacion' => $puntos, 'fecha' => $fecha,
        ]);
    }

    /** Postulación aceptada con contratación (helper de escenarios). */
    private function contratado(Publicacion $p, string $fechaPost, string $estado = 'activa', ?string $fin = null): Contratacion
    {
        return $this->contr($this->postular($p, $this->usuario('trabajador'), $fechaPost, 'aceptada'), $estado, $fin);
    }

    /** Postulación con decisión: aceptada (con su contratación) o rechazada. */
    private function decidida(Publicacion $p, string $fecha, bool $aceptada): void
    {
        $po = $this->postular($p, $this->usuario('trabajador'), $fecha, $aceptada ? 'aceptada' : 'rechazada');
        if ($aceptada) {
            $this->contr($po);
        }
    }

    private function admin(array $q = []): array
    {
        Sanctum::actingAs($this->usuario('administrador'));

        return $this->getJson(self::ADMIN . ($q ? '?' . http_build_query($q) : ''))->assertOk()->json();
    }

    private function ind(array $r, string $clave): array
    {
        foreach ($r['indicadores'] as $i) {
            if ($i['clave'] === $clave) {
                return $i;
            }
        }
        $this->fail("No existe el indicador {$clave}");
    }

    // ═════════════════════════ Fórmulas ═════════════════════════

    public function test_efectividad_de_publicaciones_usa_cohorte_madura_y_ventana_de_14_dias(): void
    {
        $c = $this->usuario('contratador');
        $p1 = $this->pub($c, '2026-06-01');                 // postulación al día 4 → éxito
        $this->postular($p1, $this->usuario('trabajador'), '2026-06-05');
        $p2 = $this->pub($c, '2026-06-10');                 // postulación al día 18 → fuera de ventana
        $this->postular($p2, $this->usuario('trabajador'), '2026-06-28');
        $this->pub($c, '2026-06-12');                       // sin postulaciones
        $this->pub($c, '2026-06-20');                       // inmadura (corte 2026-06-16) → excluida

        $i = $this->ind($this->admin(), 'efectividad_publicaciones');

        $this->assertSame(33.3, $i['valor']);
        $this->assertSame([1, 3], [$i['numerador'], $i['denominador']]);
        $this->assertSame('2026-06-16', $i['cohorte']['hasta']);
        $this->assertTrue($i['cohorte']['en_maduracion']);
        $this->assertSame('A', $i['objetivo']);
    }

    public function test_gestion_y_conversion_excluyen_canceladas_e_inmaduras_y_cuentan_contratacion_una_vez(): void
    {
        $c = $this->usuario('contratador');
        $p = $this->pub($c, '2026-05-20');
        $fin = $this->contratado($p, '2026-06-02', 'finalizada', '2026-06-20');
        $act = $this->contratado($p, '2026-06-03');
        $this->postular($p, $this->usuario('trabajador'), '2026-06-04', 'rechazada');
        $this->postular($p, $this->usuario('trabajador'), '2026-06-05', 'pendiente');
        $this->postular($p, $this->usuario('trabajador'), '2026-06-25', 'pendiente');   // inmadura (corte 06-23)
        $this->postular($p, $this->usuario('trabajador'), '2026-06-06', 'cancelada');   // retirada: no evaluable

        // Varios pagos y calificaciones sobre la misma contratación no duplican la conversión.
        $this->pago($act, '10.00', 'pendiente', '2026-06-10 10:00:00');
        $this->pago($act, '20.00', 'confirmado', '2026-06-11 10:00:00', '2026-06-11 12:00:00');
        $this->calif($fin, $c->cedula, 1, 5, '2026-06-21');

        $r = $this->admin();
        $g = $this->ind($r, 'gestion_postulaciones');
        $v = $this->ind($r, 'conversion_contratacion');

        $this->assertSame([75.0, 3, 4], [$g['valor'], $g['numerador'], $g['denominador']]);
        $this->assertSame([66.7, 2, 3], [$v['valor'], $v['numerador'], $v['denominador']]);
        $this->assertSame('2026-06-23', $g['cohorte']['hasta']);

        $etapas = array_column($r['embudo']['etapas'], 'valor', 'clave');
        $this->assertSame(['recibidas' => 4, 'evaluadas' => 3, 'con_contratacion' => 2, 'finalizadas' => 1], $etapas);
        $this->assertSame(25.0, $r['embudo']['etapas'][3]['porcentaje']);
    }

    public function test_finalizacion_no_cuenta_activas_como_fracasos(): void
    {
        $p = $this->pub($this->usuario('contratador'), '2026-04-01');
        foreach (['2026-06-05', '2026-06-10', '2026-06-15'] as $f) {
            $this->contratado($p, '2026-04-02', 'finalizada', $f);
        }
        $this->contratado($p, '2026-04-03', 'cancelada', '2026-06-20');
        $this->contratado($p, '2026-04-04', 'activa');
        $this->contratado($p, '2026-04-05', 'finalizada', '2026-05-10'); // período anterior

        $r = $this->admin();
        $i = $this->ind($r, 'finalizacion_contrataciones');

        $this->assertSame([75.0, 3, 4], [$i['valor'], $i['numerador'], $i['denominador']]);
        $this->assertSame([100.0, 1, 1], array_values($i['anterior']));
        $this->assertSame(25.0, $r['contrataciones']['tasa_cancelacion']);
        $this->assertSame(['activa' => 1, 'finalizada' => 4, 'cancelada' => 1], $r['contrataciones']['estado_actual']);
    }

    public function test_pagos_simulados_confirmacion_anulados_pendientes_y_tiempo(): void
    {
        $c = $this->contratado($this->pub($this->usuario('contratador'), '2026-05-01'), '2026-05-02');
        $this->pago($c, '100.00', 'confirmado', '2026-06-10 08:00:00', '2026-06-10 20:00:00'); // 12 h
        $this->pago($c, '50.50', 'confirmado', '2026-06-11 00:00:00', '2026-06-12 00:00:00');  // 24 h
        $this->pago($c, '30.00', 'pendiente', '2026-06-15 09:00:00');                          // 15 días
        $this->pago($c, '10.00', 'anulado', '2026-06-16 09:00:00');

        $r = $this->admin();
        $i = $this->ind($r, 'confirmacion_pagos');

        $this->assertSame([66.7, 2, 3], [$i['valor'], $i['numerador'], $i['denominador']]);
        $this->assertTrue($i['simulado']);
        $this->assertSame(25.0, $r['pagos']['tasa_anulacion']);
        $this->assertSame('150.50', $r['pagos']['confirmado']['monto_bs']);
        $this->assertSame(18.0, $r['pagos']['tiempo_confirmacion_horas']['mediana']);
        $this->assertSame(
            ['simulado' => true, 'cantidad' => 1, 'monto_bs' => '30.00', 'mediana_dias' => 15.0, 'max_dias' => 15, 'sobre_umbral' => 1],
            $r['pendientes']['pagos']
        );
        $this->assertTrue($r['simulado']);
        $this->assertStringContainsString('SIMULADOS', $r['aviso']);
        $this->assertContains('confirmacion_pagos', array_column($r['alertas'], 'indicador'));
    }

    public function test_calificaciones_promedio_con_n_distribucion_y_rol(): void
    {
        $c = $this->usuario('contratador');
        $p = $this->pub($c, '2026-05-01');
        foreach ([5, 4, 3] as $pts) {
            $t = $this->usuario('trabajador');
            $k = $this->contr($this->postular($p, $t, '2026-05-02', 'aceptada'), 'finalizada', '2026-06-01');
            $this->calif($k, $c->cedula, $t->cedula, $pts, '2026-06-02');
        }
        $this->calif($k, $t->cedula, $c->cedula, 2, '2026-06-03'); // el trabajador califica al contratador

        $r = $this->admin();
        $i = $this->ind($r, 'calidad_calificaciones');

        $this->assertSame([3.5, 4], [$i['valor'], $i['denominador']]);
        $this->assertStringContainsString('4 calificación(es)', $i['interpretacion']);
        $this->assertSame(['1' => 0, '2' => 1, '3' => 1, '4' => 1, '5' => 1], $r['calidad']['distribucion']);
        $this->assertEquals(['promedio' => 4.0, 'n' => 3], $r['calidad']['por_rol']['trabajador']);
        $this->assertEquals(['promedio' => 2.0, 'n' => 1], $r['calidad']['por_rol']['contratador']);
        $this->assertSame(['finalizadas' => 3, 'calificadas' => 3, 'porcentaje' => 100.0], $r['calidad']['cobertura']);
    }

    // ═════════════════════════ Vacíos y división por cero ═════════════════════════

    public function test_sin_datos_devuelve_null_y_nunca_0_ni_100(): void
    {
        $r = $this->admin();

        foreach ($r['indicadores'] as $i) {
            $this->assertNull($i['valor'], $i['clave']);
            $this->assertSame('insuficiente', $i['variacion']['evaluacion'], $i['clave']);
            $this->assertNull($i['variacion']['puntos'], $i['clave']);
            $this->assertStringContainsString('Sin', $i['interpretacion']);
        }
        $this->assertCount(12, $r['evolucion']);
        foreach ($r['evolucion'] as $m) {
            $this->assertSame(0, $m['postulaciones']);
            $this->assertNull($m['tasa_conversion']);
            $this->assertNull($m['promedio_calificacion']);
        }
        $this->assertNull($r['pendientes']['pagos']['mediana_dias']);
        $this->assertSame([], $r['categorias']);
        $this->assertSame([], $r['alertas']);
    }

    // ═════════════════════════ Comparación estadística ═════════════════════════

    public function test_variacion_significativa_se_marca_como_mejora_o_deterioro(): void
    {
        $c = $this->usuario('contratador');
        $p = $this->pub($c, '2026-04-01');
        // Anterior (mayo): 12 decididas, 2 contratadas. Actual (junio): 12 decididas, 10 contratadas.
        for ($k = 0; $k < 12; $k++) {
            $this->decidida($p, '2026-05-10', $k < 2);
            $this->decidida($p, '2026-06-10', $k < 10);
        }

        $i = $this->ind($this->admin(), 'conversion_contratacion');

        $this->assertSame(83.3, $i['valor']);
        $this->assertSame(16.7, $i['anterior']['valor']);
        $this->assertSame(66.6, $i['variacion']['puntos']);
        $this->assertSame('mejora', $i['variacion']['evaluacion']);
    }

    public function test_variacion_pequena_no_se_declara_mejora(): void
    {
        $p = $this->pub($this->usuario('contratador'), '2026-04-01');
        for ($k = 0; $k < 10; $k++) {
            $this->decidida($p, '2026-05-10', $k < 5);
            $this->decidida($p, '2026-06-10', $k < 6);
        }

        $i = $this->ind($this->admin(), 'conversion_contratacion');

        $this->assertSame([60.0, 50.0], [$i['valor'], $i['anterior']['valor']]);
        $this->assertSame('sin_cambio_significativo', $i['variacion']['evaluacion']);
    }

    public function test_cancelaciones_crecientes_son_deterioro_y_generan_alerta(): void
    {
        $p = $this->pub($this->usuario('contratador'), '2026-03-01');
        for ($k = 0; $k < 10; $k++) {
            $this->contratado($p, '2026-03-02', 'finalizada', '2026-05-15');
            $this->contratado($p, '2026-03-02', $k < 8 ? 'cancelada' : 'finalizada', '2026-06-15');
        }

        $r = $this->admin();
        $i = $this->ind($r, 'finalizacion_contrataciones');

        $this->assertSame([20.0, 100.0], [$i['valor'], $i['anterior']['valor']]);
        $this->assertSame('deterioro', $i['variacion']['evaluacion']);
        $this->assertSame('alta', collect($r['alertas'])->firstWhere('indicador', 'finalizacion_contrataciones')['nivel']);
    }

    // ═════════════════════════ Períodos y filtros ═════════════════════════

    public function test_periodos_y_periodo_anterior_comparable(): void
    {
        $r = $this->admin(['periodo' => '90d']);
        $this->assertSame(['2026-04-02', '2026-06-30', 90], [$r['periodo']['desde'], $r['periodo']['hasta'], $r['periodo']['dias']]);
        $this->assertSame(['2026-01-02', '2026-04-01'], [$r['periodo']['anterior']['desde'], $r['periodo']['anterior']['hasta']]);

        $r = $this->getJson(self::ADMIN . '?periodo=anio')->assertOk()->json();
        $this->assertSame(['2026-01-01', true], [$r['periodo']['desde'], $r['periodo']['parcial']]);
        $this->assertSame(['2025-01-01', '2025-06-30'], [$r['periodo']['anterior']['desde'], $r['periodo']['anterior']['hasta']]);

        $r = $this->getJson(self::ADMIN . '?periodo=personalizado&desde=2026-03-01&hasta=2026-03-10')->assertOk()->json();
        $this->assertSame(['2026-02-19', '2026-02-28'], [$r['periodo']['anterior']['desde'], $r['periodo']['anterior']['hasta']]);
        $this->assertSame('2026-03', end($r['evolucion'])['mes']);
    }

    public function test_filtros_invalidos_devuelven_422_en_espanol(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));

        $this->getJson(self::ADMIN . '?periodo=semana')->assertStatus(422)->assertJsonValidationErrors('periodo');
        $this->getJson(self::ADMIN . '?periodo=personalizado')->assertStatus(422)->assertJsonValidationErrors(['desde', 'hasta']);
        $this->getJson(self::ADMIN . '?periodo=personalizado&desde=2026-06-10&hasta=2026-06-01')
            ->assertStatus(422)->assertJsonValidationErrors('hasta');
        $this->getJson(self::ADMIN . '?periodo=personalizado&desde=2026-06-01&hasta=2026-07-01')
            ->assertStatus(422)->assertJsonPath('errors.hasta.0', 'La fecha final no puede ser futura.');
        $this->getJson(self::ADMIN . '?periodo=personalizado&desde=2024-01-01&hasta=2026-06-01')
            ->assertStatus(422)->assertJsonValidationErrors('hasta');
        $this->getJson(self::ADMIN . '?periodo=personalizado&desde=01/06/2026&hasta=2026-06-10')
            ->assertStatus(422)->assertJsonValidationErrors('desde');
        $this->getJson(self::ADMIN . '?categoria=999')->assertStatus(422)
            ->assertJsonPath('errors.categoria.0', 'La categoría seleccionada no existe.');
        $this->getJson(self::ADMIN . '?categoria=abc')->assertStatus(422)->assertJsonValidationErrors('categoria');
    }

    public function test_filtro_por_categoria_y_desglose_por_categoria(): void
    {
        $c = $this->usuario('contratador');
        $limp = $this->pub($c, '2026-05-01', 1);
        $cons = $this->pub($c, '2026-05-01', 2);
        $this->contratado($limp, '2026-06-02');
        $this->postular($limp, $this->usuario('trabajador'), '2026-06-03', 'rechazada');
        $this->postular($cons, $this->usuario('trabajador'), '2026-06-04', 'rechazada');

        $todo = $this->admin();
        $this->assertSame([1, 3], [$this->ind($todo, 'conversion_contratacion')['numerador'], $this->ind($todo, 'conversion_contratacion')['denominador']]);
        $cats = collect($todo['categorias'])->keyBy('id_categoria');
        $this->assertSame(['Limpieza', 2, 50.0], [$cats[1]['nombre'], $cats[1]['postulaciones'], $cats[1]['conversion']]);
        $this->assertSame(['Construcción', 1, 0.0], [$cats[2]['nombre'], $cats[2]['postulaciones'], $cats[2]['conversion']]);

        $soloCons = $this->getJson(self::ADMIN . '?categoria=2')->assertOk()->json();
        $i = $this->ind($soloCons, 'conversion_contratacion');
        $this->assertSame([0.0, 0, 1], [$i['valor'], $i['numerador'], $i['denominador']]);
        $this->assertSame(2, $soloCons['filtros']['categoria']);
    }

    public function test_evolucion_mensual_con_tasas_y_marcas_de_maduracion(): void
    {
        $p = $this->pub($this->usuario('contratador'), '2026-01-01');
        $this->contratado($p, '2026-04-10', 'finalizada', '2026-05-05');
        $this->postular($p, $this->usuario('trabajador'), '2026-04-11', 'rechazada');
        $this->postular($p, $this->usuario('trabajador'), '2026-06-29', 'pendiente');

        $serie = collect($this->admin()['evolucion'])->keyBy('mes');

        $this->assertSame('2025-07', array_key_first($serie->all()));
        $this->assertSame([2, 2, 1, 50.0], [$serie['2026-04']['postulaciones'], $serie['2026-04']['evaluadas'], $serie['2026-04']['contratadas'], $serie['2026-04']['tasa_conversion']]);
        $this->assertSame([1, 100.0], [$serie['2026-05']['finalizadas'], $serie['2026-05']['tasa_finalizacion']]);
        $this->assertSame([1, 0.0], [$serie['2026-06']['postulaciones'], $serie['2026-06']['tasa_gestion']]);
        $this->assertTrue($serie['2026-06']['en_maduracion']);
        $this->assertFalse($serie['2026-04']['en_maduracion']);
    }

    // ═════════════════════════ Seguridad, aislamiento y privacidad ═════════════════════════

    public function test_autenticacion_y_roles(): void
    {
        foreach ([self::ADMIN, self::CONTR, self::TRAB] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }

        $matriz = [
            [self::ADMIN, 'contratador', 403], [self::ADMIN, 'trabajador', 403], [self::ADMIN, 'administrador', 200],
            [self::CONTR, 'trabajador', 403], [self::CONTR, 'administrador', 403], [self::CONTR, 'contratador', 200],
            [self::TRAB, 'contratador', 403], [self::TRAB, 'administrador', 403], [self::TRAB, 'trabajador', 200],
        ];
        foreach ($matriz as [$url, $rol, $esperado]) {
            Sanctum::actingAs($this->usuario($rol));
            $this->getJson($url)->assertStatus($esperado);
        }
    }

    public function test_contratador_solo_ve_sus_propios_datos(): void
    {
        $yo = $this->usuario('contratador');
        $otro = $this->usuario('contratador');
        $mia = $this->pub($yo, '2026-05-01', 1, 'Mi oferta');
        $ajena = $this->pub($otro, '2026-05-01', 1, 'Oferta ajena');

        $this->contratado($mia, '2026-06-02');
        $this->postular($mia, $this->usuario('trabajador'), '2026-06-03', 'pendiente');
        foreach (range(1, 5) as $k) {
            $this->postular($ajena, $this->usuario('trabajador'), '2026-06-03', 'pendiente');
        }
        $kAjena = $this->contratado($ajena, '2026-06-02');
        $this->pago($kAjena, '99.00', 'pendiente', '2026-06-05 10:00:00');

        Sanctum::actingAs($yo);
        $resp = $this->getJson(self::CONTR . '?categoria=2')->assertOk(); // categoria se ignora para el contratador
        $r = $resp->json();

        $g = $this->ind($r, 'gestion_postulaciones');
        $this->assertSame([1, 2], [$g['numerador'], $g['denominador']]);
        $this->assertSame(1, $r['pendientes']['postulaciones']['cantidad']);
        $this->assertSame(['Mi oferta'], array_column($r['pendientes']['postulaciones']['mas_antiguas'], 'titulo'));
        $this->assertSame(0, $r['pendientes']['pagos']['cantidad']);
        $this->assertNull($r['filtros']['categoria']);
        $this->assertArrayNotHasKey('categorias', $r);
        $this->assertStringNotContainsString('Oferta ajena', $resp->getContent());
        $this->assertStringNotContainsString('@', $resp->getContent(), 'No debe exponer correos');
        $this->assertStringNotContainsString('contrasena', $resp->getContent());
    }

    public function test_trabajador_solo_ve_su_seguimiento_personal(): void
    {
        $c = $this->usuario('contratador');
        $p = $this->pub($c, '2026-05-01');
        $yo = $this->usuario('trabajador');
        $otro = $this->usuario('trabajador');

        $k = $this->contr($this->postular($p, $yo, '2026-06-02', 'aceptada'), 'finalizada', '2026-06-20');
        $this->pago($k, '40.00', 'confirmado', '2026-06-21 10:00:00', '2026-06-21 11:00:00');
        $this->pago($k, '15.00', 'pendiente', '2026-06-22 10:00:00');
        $this->calif($k, $c->cedula, $yo->cedula, 4, '2026-06-21');
        $this->postular($this->pub($c, '2026-05-02'), $yo, '2026-06-29', 'pendiente'); // reciente: cuenta (sin maduración)

        $ko = $this->contr($this->postular($p, $otro, '2026-06-02', 'aceptada'), 'finalizada', '2026-06-20');
        $this->pago($ko, '500.00', 'confirmado', '2026-06-21 10:00:00', '2026-06-21 11:00:00');
        $this->calif($ko, $c->cedula, $otro->cedula, 1, '2026-06-21');
        $this->postular($this->pub($c, '2026-05-03'), $otro, '2026-06-10', 'rechazada');

        Sanctum::actingAs($yo);
        $r = $this->getJson(self::TRAB)->assertOk()->json();

        $this->assertSame(['gestion_postulaciones', 'conversion_contratacion', 'finalizacion_contrataciones', 'calidad_calificaciones'],
            array_column($r['indicadores'], 'clave'));
        $g = $this->ind($r, 'gestion_postulaciones');
        $this->assertSame([50.0, 1, 2], [$g['valor'], $g['numerador'], $g['denominador']]);
        $this->assertSame(0, $r['parametros']['maduracion_postulaciones_dias']);
        $this->assertSame([4.0, 1], [$this->ind($r, 'calidad_calificaciones')['valor'], $r['calidad']['n']]);
        $this->assertSame('40.00', $r['pagos']['confirmado']['monto_bs']);
        $this->assertSame('15.00', $r['pendientes']['pagos']['monto_bs']);
        $this->assertNull($r['pendientes']['postulaciones']);
        $this->assertNull($r['calidad']['por_rol']);
        $this->assertSame([], $r['alertas']);
    }

    public function test_contratador_ve_solo_calificaciones_que_recibio(): void
    {
        $yo = $this->usuario('contratador');
        $t = $this->usuario('trabajador');
        $k = $this->contr($this->postular($this->pub($yo, '2026-05-01'), $t, '2026-05-02', 'aceptada'), 'finalizada', '2026-06-01');
        $this->calif($k, $yo->cedula, $t->cedula, 1, '2026-06-02'); // la que emití: no cuenta
        $this->calif($k, $t->cedula, $yo->cedula, 5, '2026-06-02'); // la que recibí

        Sanctum::actingAs($yo);
        $i = $this->ind($this->getJson(self::CONTR)->assertOk()->json(), 'calidad_calificaciones');

        $this->assertSame([5.0, 1], [$i['valor'], $i['denominador']]);
    }

    // ═════════════════════════ Compatibilidad y rendimiento ═════════════════════════

    public function test_endpoint_heredado_conserva_su_contrato(): void
    {
        Sanctum::actingAs($this->usuario('administrador'));

        $this->assertEqualsCanonicalizing(
            ['publicaciones_activas', 'contrataciones_activas', 'pagos_confirmados_bs', 'simulado', 'generado_en'],
            array_keys($this->getJson('/api/admin/kpis')->assertOk()->json())
        );
    }

    public function test_numero_de_consultas_no_crece_con_los_datos(): void
    {
        $consultas = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(self::ADMIN)->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        Sanctum::actingAs($this->usuario('administrador'));
        $c = $this->usuario('contratador');
        $p = $this->pub($c, '2026-05-01');
        $this->pago($this->contratado($p, '2026-06-02'), '1.00', 'pendiente', '2026-06-05 10:00:00');
        $this->getJson(self::ADMIN)->assertOk(); // calentamiento: caché de roles de Spatie
        $pocos = $consultas();

        foreach (range(1, 15) as $k) {
            $q = $this->pub($c, '2026-05-0' . (1 + $k % 9), 1 + $k % 2);
            $kk = $this->contratado($q, '2026-06-0' . (1 + $k % 9), 'finalizada', '2026-06-20');
            $this->pago($kk, '2.00', 'confirmado', '2026-06-21 10:00:00', '2026-06-21 12:00:00');
            $this->calif($kk, $c->cedula, 1, 4, '2026-06-22');
        }
        $muchos = $consultas();

        $this->assertSame($pocos, $muchos, 'La cantidad de consultas debe ser constante (sin N+1).');
        $this->assertLessThanOrEqual(40, $muchos);
    }
}
