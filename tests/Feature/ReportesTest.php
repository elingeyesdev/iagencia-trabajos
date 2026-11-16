<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bloque 4 — Reportes imprimibles. Arquitectura «API → Consultas → JSON → Vista imprimible»:
 * los datos/totales/filtros se verifican contra GET /api/admin/reportes/{tipo} (JSON) y las páginas
 * web /reportes/* se verifican como plantillas (200 admin, 403/redirect resto, meta api-token,
 * botón «Descargar PDF», @media print, banda de simulados). Los PDF se prueban en ReportesPdfTest. Datos sembrados y totales calculados a mano:
 *
 * Usuarios: admin(activo, reg. 2026-01-05), contratador(activo), trabajador1(activo), trabajador2(inactivo) → 4.
 * Categorías: A(activa) con p1,p2; B(inactiva) con p3.
 * Publicaciones: p1 A 100.50 activa 2026-01-10; p2 A 200.25 inactiva 2026-02-10; p3 B 50.00 activa 2026-03-10 → suma 350.75.
 * Postulaciones: t1→p1 aceptada (01-11), t2→p1 pendiente (01-12), t1→p3 rechazada (03-11).
 * Contratación: post1 activa 100.50 (inicio 01-15).
 * Pagos: 30.10 confirmado 10% (3.01 / 27.09) 01-20; 20.20 pendiente 01-25; 10.00 anulado 02-05; 15.05 confirmado 0% 02-10.
 *   Confirmados: 45.15 / comisión 3.01 / neto 42.14.
 */
class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $contratador;
    private User $trabajador;
    private Categoria $catA;
    private int $ref = 1;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->admin       = $this->usuario(8001, 'administrador', 1, '2026-01-05 10:00:00', 'Ana Admin');
        $this->contratador = $this->usuario(8002, 'contratador', 1, '2026-03-01 09:00:00', 'Carlos Contratador');
        $this->trabajador  = $this->usuario(8003, 'trabajador', 1, '2026-03-01 09:00:00', 'Tania Trabajadora');
        $t2                = $this->usuario(8004, 'trabajador', 0, '2026-03-02 09:00:00', 'Pedro Inactivo');

        $this->catA = Categoria::create(['nombre_categoria' => 'Carpintería', 'estado' => 1]);
        $catB = Categoria::create(['nombre_categoria' => 'Jardinería', 'estado' => 0]);

        $p1 = $this->publicacion('Mesa de roble', $this->catA, '100.50', 1, '2026-01-10');
        $this->publicacion('Puerta antigua', $this->catA, '200.25', 0, '2026-02-10');
        $p3 = $this->publicacion('Podar jardín', $catB, '50.00', 1, '2026-03-10');

        $post1 = Postulacion::create(['id_labor' => $p1->id_labor, 'cedula' => 8003, 'fecha_postulacion' => '2026-01-11', 'estado' => 'aceptada']);
        Postulacion::create(['id_labor' => $p1->id_labor, 'cedula' => $t2->cedula, 'fecha_postulacion' => '2026-01-12', 'estado' => 'pendiente']);
        Postulacion::create(['id_labor' => $p3->id_labor, 'cedula' => 8003, 'fecha_postulacion' => '2026-03-11', 'estado' => 'rechazada']);

        $c = Contratacion::create(['id_postulacion' => $post1->id_postulacion, 'fecha_inicio' => '2026-01-15', 'monto_acordado' => '100.50', 'estado' => 'activa']);

        $this->pago($c, '30.10', '10.00', '3.01', 'confirmado', '2026-01-20');
        $this->pago($c, '20.20', '0.00', '0.00', 'pendiente', '2026-01-25');
        $this->pago($c, '10.00', '0.00', '0.00', 'anulado', '2026-02-05');
        $this->pago($c, '15.05', '0.00', '0.00', 'confirmado', '2026-02-10');
    }

    private function usuario(int $cedula, string $rol, int $estado, string $registro, string $nombre): User
    {
        $u = User::factory()->create([
            'cedula' => $cedula, 'nombre' => $nombre, 'estado' => $estado,
            'fecha_registro' => $registro, 'ciudad' => 'La Paz',
        ]);
        $u->assignRole($rol);

        return $u;
    }

    private function publicacion(string $titulo, Categoria $cat, string $pago, int $estado, string $fecha): Publicacion
    {
        return Publicacion::create([
            'tipo' => 'oferta', 'titulo' => $titulo, 'descripcion' => 'Desc', 'pago' => $pago, 'lugar' => 'La Paz',
            'fecha_publicacion' => $fecha, 'cedula' => $this->contratador->cedula,
            'id_categoria' => $cat->id_categoria, 'estado' => $estado,
        ]);
    }

    private function pago(Contratacion $c, string $monto, string $pct, string $comision, string $estado, string $fecha): void
    {
        $neto = number_format((float) $monto - (float) $comision, 2, '.', '');
        (new Pago)->forceFill([
            'id_contratacion' => $c->id_contratacion, 'monto' => $monto, 'porcentaje_comision' => $pct,
            'monto_comision' => $comision, 'monto_neto' => $neto, 'fecha_pago' => $fecha, 'metodo' => 'transferencia',
            'estado' => $estado, 'referencia_externa' => 'SIM-REP-' . $this->ref++,
            'cedula_registra' => $this->contratador->cedula, 'fecha_registro' => now(),
        ])->save();
    }

    /** GET /api/admin/reportes/{tipo} como administrador (Sanctum). */
    private function api(string $tipo, array $filtros = []): TestResponse
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson('/api/admin/reportes/' . $tipo . ($filtros ? '?' . http_build_query($filtros) : ''));
    }

    /** @return list<mixed> */
    private function columna(TestResponse $r, string $lista, string $campo): array
    {
        return array_column($r->json($lista), $campo);
    }

    // ── Acceso a las páginas web (plantillas imprimibles) ──

    public static function rutas(): array
    {
        return array_map(fn ($r) => [$r], ['reportes.index', 'reportes.usuarios', 'reportes.publicaciones', 'reportes.categorias', 'reportes.actividad', 'reportes.pagos']);
    }

    /** [tipo de API] */
    public static function tiposApi(): array
    {
        return array_map(fn ($t) => [$t], ['usuarios', 'publicaciones', 'categorias', 'actividad', 'pagos']);
    }

    #[DataProvider('rutas')]
    public function test_admin_accede_y_ve_boton_imprimir(string $ruta): void
    {
        $this->withoutVite();
        $r = $this->actingAs($this->admin)->get(route($ruta))->assertOk();

        if ($ruta !== 'reportes.index') {
            // El botón «Imprimir / Guardar como PDF» (window.print) se sustituyó por «Descargar PDF», que
            // descarga GET /api/admin/reportes/{tipo}/pdf desde reportes.js; se conserva la intención
            // (la vista ofrece obtener el PDF) y el CSS @media print sigue disponible para Ctrl+P.
            $r->assertSee('Descargar PDF')->assertSee('id="btn-pdf"', false)
                ->assertDontSee('window.print()', false)->assertDontSee('Imprimir / Guardar como PDF')
                ->assertSee('@media print', false)
                ->assertSee('<meta name="api-token" content="', false)
                ->assertSee('<meta name="reporte-tipo" content="' . substr($ruta, strlen('reportes.')) . '">', false)
                ->assertSee('id="reporte-estado"', false)
                ->assertSee('id="reporte-errores"', false);
        } else {
            // El índice no consume la API: no crea token ni carga reportes.js.
            $r->assertDontSee('name="api-token"', false);
        }
    }

    #[DataProvider('rutas')]
    public function test_contratador_y_trabajador_reciben_403(string $ruta): void
    {
        $this->actingAs($this->contratador)->get(route($ruta))->assertForbidden();
        $this->actingAs($this->trabajador)->get(route($ruta))->assertForbidden();
    }

    #[DataProvider('rutas')]
    public function test_invitado_redirige_a_login(string $ruta): void
    {
        $this->get(route($ruta))->assertRedirect(route('login'));
    }

    public function test_pagina_crea_token_sanctum_de_corta_duracion_que_sirve_para_la_api(): void
    {
        $this->withoutVite();
        $html = $this->actingAs($this->admin)->get(route('reportes.pagos'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<meta name="api-token" content="([^"]+)">/', $html, $m));

        $token = PersonalAccessToken::findToken($m[1]);
        $this->assertNotNull($token);
        $this->assertSame('reportes', $token->name);
        $this->assertSame($this->admin->cedula, (int) $token->tokenable_id);
        $this->assertNotNull($token->expires_at, 'El token de reportes debe expirar');
        $this->assertTrue($token->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));

        // El token entregado a la vista autentica la API de reportes.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $m[1])->getJson('/api/admin/reportes/pagos')
            ->assertOk()->assertJsonPath('simulado', true);
    }

    public function test_pagina_de_pagos_muestra_banda_de_simulados_y_paginas_no_incrustan_datos(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin)->get(route('reportes.pagos'))->assertOk()
            ->assertSee('OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL')
            ->assertSee('banda-simulado', false);

        // Las plantillas no contienen datos de dominio: los rellena reportes.js desde la API.
        $this->actingAs($this->admin)->get(route('reportes.usuarios'))->assertOk()->assertDontSee('Pedro Inactivo');
        $this->actingAs($this->admin)->get(route('reportes.publicaciones'))->assertOk()->assertDontSee('Mesa de roble');
    }

    // ── Autorización de la API de reportes ──

    #[DataProvider('tiposApi')]
    public function test_api_reportes_sin_token_recibe_401(string $tipo): void
    {
        $this->getJson('/api/admin/reportes/' . $tipo)->assertUnauthorized();
    }

    #[DataProvider('tiposApi')]
    public function test_api_reportes_contratador_y_trabajador_reciben_403(string $tipo): void
    {
        Sanctum::actingAs($this->contratador);
        $this->getJson('/api/admin/reportes/' . $tipo)->assertForbidden();

        Sanctum::actingAs($this->trabajador);
        $this->getJson('/api/admin/reportes/' . $tipo)->assertForbidden();
    }

    #[DataProvider('tiposApi')]
    public function test_api_reportes_incluye_metadatos_de_generacion(string $tipo): void
    {
        $this->api($tipo)->assertOk()
            ->assertJsonPath('reporte', $tipo)
            ->assertJsonPath('generado_por.cedula', 8001)
            ->assertJsonPath('generado_por.nombre', 'Ana Admin')
            ->assertJsonPath('limite_filas', 1000)
            ->assertJsonStructure(['generado_en', 'generado_en_texto', 'filtros']);
    }

    // ── Usuarios ──

    public function test_reporte_usuarios_totales_por_rol_y_estado(): void
    {
        $r = $this->api('usuarios')->assertOk();

        $r->assertJsonPath('total', 4)
            ->assertJsonPath('por_estado', ['activos' => 3, 'inactivos' => 1])
            ->assertJsonPath('por_rol', [
                ['rol' => 'administrador', 'total' => 1],
                ['rol' => 'contratador', 'total' => 1],
                ['rol' => 'trabajador', 'total' => 2],
            ])
            ->assertJsonPath('sin_rol', 0)
            ->assertJsonPath('mostrados', 4)
            ->assertJsonPath('opciones.roles', ['administrador', 'contratador', 'trabajador']);

        $this->assertContains('Ana Admin', $this->columna($r, 'filas', 'nombre'));
        $this->assertContains('Pedro Inactivo', $this->columna($r, 'filas', 'nombre'));
        $this->assertSame(['cedula' => 8004, 'nombre' => 'Pedro Inactivo', 'ciudad' => 'La Paz', 'roles' => ['trabajador'], 'estado' => 0],
            array_intersect_key($r->json('filas.3'), array_flip(['cedula', 'nombre', 'ciudad', 'roles', 'estado'])));
        $this->assertStringNotContainsString('contrasena', $r->getContent());
    }

    public function test_reporte_usuarios_filtra_por_rol_y_estado(): void
    {
        $r = $this->api('usuarios', ['rol' => 'trabajador'])->assertOk()->assertJsonPath('total', 2)->assertJsonPath('filtros', ['rol' => 'trabajador']);
        $this->assertContains('Tania Trabajadora', $this->columna($r, 'filas', 'nombre'));
        $this->assertNotContains('Ana Admin', $this->columna($r, 'filas', 'nombre'));

        $r = $this->api('usuarios', ['estado' => '0'])->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(['Pedro Inactivo'], $this->columna($r, 'filas', 'nombre'));

        $r = $this->api('usuarios', ['rol' => 'trabajador', 'estado' => '1'])->assertOk()->assertJsonPath('total', 1);
        $this->assertSame(['Tania Trabajadora'], $this->columna($r, 'filas', 'nombre'));
    }

    public function test_filtro_invalido_es_rechazado(): void
    {
        $this->api('usuarios', ['rol' => 'inexistente'])
            ->assertStatus(422)->assertJsonValidationErrors(['rol' => 'El rol indicado no existe.']);

        $this->api('pagos', ['desde' => '2026-02-01', 'hasta' => '2026-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['hasta' => 'La fecha «hasta» no puede ser anterior a «desde».']);

        // Sin cabecera Accept JSON también responde 422 JSON (nunca redirige).
        Sanctum::actingAs($this->admin);
        $this->get('/api/admin/reportes/usuarios?rol=inexistente')
            ->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['rol']]);
    }

    // ── Publicaciones ──

    public function test_reporte_publicaciones_totales_y_filtros(): void
    {
        $r = $this->api('publicaciones')->assertOk()
            ->assertJsonPath('suma_pago', '350.75')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('por_estado', ['activos' => 2, 'inactivos' => 1]);
        $this->assertSame(['Mesa de roble', 'Carpintería', 'Carlos Contratador', '100.50'],
            [$r->json('filas.0.titulo'), $r->json('filas.0.categoria'), $r->json('filas.0.contratador'), $r->json('filas.0.pago')]);
        $this->assertSame(['Carpintería', 'Jardinería'], $this->columna($r, 'opciones.categorias', 'nombre_categoria'));

        $r = $this->api('publicaciones', ['estado' => '1'])->assertOk()->assertJsonPath('suma_pago', '150.50');
        $this->assertNotContains('Puerta antigua', $this->columna($r, 'filas', 'titulo'));

        $r = $this->api('publicaciones', ['categoria' => $this->catA->id_categoria])->assertOk()->assertJsonPath('suma_pago', '300.75');
        $this->assertNotContains('Podar jardín', $this->columna($r, 'filas', 'titulo'));

        $r = $this->api('publicaciones', ['desde' => '2026-02-01'])->assertOk()->assertJsonPath('suma_pago', '250.25');
        $this->assertNotContains('Mesa de roble', $this->columna($r, 'filas', 'titulo'));
    }

    // ── Categorías ──

    public function test_reporte_categorias_cuenta_publicaciones(): void
    {
        $this->api('categorias')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('por_estado', ['activos' => 1, 'inactivos' => 1])
            ->assertJsonPath('filas.0.nombre_categoria', 'Carpintería')->assertJsonPath('filas.0.estado', 1)->assertJsonPath('filas.0.publicaciones_count', 2)
            ->assertJsonPath('filas.1.nombre_categoria', 'Jardinería')->assertJsonPath('filas.1.estado', 0)->assertJsonPath('filas.1.publicaciones_count', 1)
            ->assertJsonPath('total_publicaciones', 3);
    }

    // ── Actividad ──

    public function test_reporte_actividad_conteos_y_filtro_de_fechas(): void
    {
        $r = $this->api('actividad')->assertOk()
            ->assertJsonPath('usuarios.total', 4)
            ->assertJsonPath('publicaciones.total', 3)
            ->assertJsonPath('postulaciones.total', 3)
            ->assertJsonPath('contrataciones.total', 1)
            ->assertJsonPath('pagos.total', 4)
            ->assertJsonPath('pagos.simulado', true);
        $this->assertContains(['estado' => 'confirmado', 'total' => 2], $r->json('pagos.por_estado'));

        $this->api('actividad', ['desde' => '2026-01-01', 'hasta' => '2026-01-31'])->assertOk()
            ->assertJsonPath('filtros', ['desde' => '2026-01-01', 'hasta' => '2026-01-31'])
            ->assertJsonPath('usuarios.total', 1)
            ->assertJsonPath('publicaciones.total', 1)
            ->assertJsonPath('postulaciones.total', 2)
            ->assertJsonPath('contrataciones.total', 1)
            ->assertJsonPath('pagos.total', 2);
    }

    public function test_reporte_actividad_sin_datos_muestra_sin_registros(): void
    {
        // «Sin registros» lo pinta reportes.js cuando las listas llegan vacías (tests/js/reportes.test.mjs).
        $r = $this->api('actividad', ['desde' => '2030-01-01'])->assertOk();
        foreach (['usuarios', 'publicaciones', 'postulaciones', 'contrataciones', 'pagos'] as $s) {
            $r->assertJsonPath("{$s}.total", 0)->assertJsonPath("{$s}.por_estado", [])->assertJsonPath("{$s}.recientes", []);
        }

        $this->withoutVite();
        $this->actingAs($this->admin)->get(route('reportes.actividad', ['desde' => '2030-01-01']))
            ->assertOk()->assertSee('Sin registros');
    }

    // ── Pagos ──

    public function test_reporte_pagos_banda_simulados_y_totales_confirmados(): void
    {
        $r = $this->api('pagos')->assertOk();

        $r->assertJsonPath('simulado', true)
            ->assertJsonPath('aviso', 'Operaciones simuladas: no representan dinero real.')
            ->assertJsonPath('total', 4)
            ->assertJsonPath('confirmados', ['cantidad' => 2, 'monto' => '45.15', 'comision' => '3.01', 'neto' => '42.14'])
            ->assertJsonPath('por_estado', [
                ['estado' => 'anulado', 'cantidad' => 1, 'monto' => '10.00', 'comision' => '0.00', 'neto' => '10.00'],
                ['estado' => 'confirmado', 'cantidad' => 2, 'monto' => '45.15', 'comision' => '3.01', 'neto' => '42.14'],
                ['estado' => 'pendiente', 'cantidad' => 1, 'monto' => '20.20', 'comision' => '0.00', 'neto' => '20.20'],
            ])
            ->assertJsonPath('filas.0.contratador', ['cedula' => 8002, 'nombre' => 'Carlos Contratador'])
            ->assertJsonPath('filas.0.trabajador', ['cedula' => 8003, 'nombre' => 'Tania Trabajadora'])
            ->assertJsonPath('filas.0.titulo', 'Mesa de roble')
            ->assertJsonPath('filas.0.porcentaje_comision', '10.00')
            ->assertJsonPath('filas.0.monto_comision', '3.01')
            ->assertJsonPath('filas.0.monto_neto', '27.09')
            ->assertJsonPath('filas.0.es_simulado', true);

        $this->withoutVite();
        $this->actingAs($this->admin)->get(route('reportes.pagos'))->assertOk()
            ->assertSee('OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL');
    }

    public function test_reporte_pagos_filtros_estado_y_fechas(): void
    {
        $r = $this->api('pagos', ['estado' => 'pendiente'])->assertOk()
            ->assertJsonPath('simulado', true)
            ->assertJsonPath('confirmados.monto', '0.00');
        $this->assertSame(['pendiente'], $this->columna($r, 'por_estado', 'estado'));

        $r = $this->api('pagos', ['desde' => '2026-02-01'])->assertOk()
            ->assertJsonPath('confirmados.monto', '15.05')
            ->assertJsonPath('confirmados.comision', '0.00');
        $this->assertNotContains('pendiente', $this->columna($r, 'por_estado', 'estado'));
    }
}
