<?php

namespace Tests\Feature\Api;

use App\Models\Calificacion;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Matriz de permisos por actor (casos de uso de contratador y trabajador, con pagos simulados y KPIs).
 * Nivel 2 (API: autenticación y rol), nivel 3 (propiedad de registros) y nivel 1 (acciones visibles).
 *
 * Escenario: contratadores A y B, trabajadores T y T2, administrador.
 *  - pubA (de A) con postulación pendiente de T2 y contratación activa de T.
 *  - pubB (de B) con postulación pendiente de T y contratación activa de T2.
 */
class PermisosActoresTest extends TestCase
{
    use RefreshDatabase;

    private User $a, $b, $t, $t2, $admin;
    private Publicacion $pubA, $pubB;
    private Postulacion $postPendA, $postPendB;
    private Contratacion $contrA, $contrB;
    private int $ref = 1;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
        DB::table('categorias')->insert(['id_categoria' => 1, 'nombre_categoria' => 'Limpieza', 'estado' => 1]);

        $this->a     = $this->usuario(7001, 'contratador');
        $this->b     = $this->usuario(7002, 'contratador');
        $this->t     = $this->usuario(7003, 'trabajador');
        $this->t2    = $this->usuario(7004, 'trabajador');
        $this->admin = $this->usuario(7005, 'administrador');

        $this->pubA = $this->publicacion($this->a, 'Oferta de A');
        $this->pubB = $this->publicacion($this->b, 'Oferta de B');

        $this->postPendA = $this->postulacion($this->pubA, $this->t2, 'pendiente');
        $this->postPendB = $this->postulacion($this->pubB, $this->t, 'pendiente');
        $this->contrA    = $this->contratacion($this->postulacion($this->pubA, $this->t, 'aceptada'));
        $this->contrB    = $this->contratacion($this->postulacion($this->pubB, $this->t2, 'aceptada'));
    }

    private function usuario(int $cedula, string $rol): User
    {
        $u = User::factory()->create(['cedula' => $cedula]);
        $u->assignRole($rol);

        return $u;
    }

    private function publicacion(User $c, string $titulo): Publicacion
    {
        return Publicacion::create([
            'tipo' => 'temporal', 'titulo' => $titulo, 'descripcion' => 'D', 'pago' => '100.00', 'lugar' => 'La Paz',
            'fecha_publicacion' => '2026-11-01', 'cedula' => $c->cedula, 'id_categoria' => 1, 'estado' => 1,
        ]);
    }

    private function postulacion(Publicacion $p, User $t, string $estado): Postulacion
    {
        return Postulacion::create(['id_labor' => $p->id_labor, 'cedula' => $t->cedula, 'fecha_postulacion' => '2026-11-02', 'estado' => $estado]);
    }

    private function contratacion(Postulacion $po, string $estado = 'activa'): Contratacion
    {
        return Contratacion::create(['id_postulacion' => $po->id_postulacion, 'fecha_inicio' => '2026-11-03', 'monto_acordado' => '100.00', 'estado' => $estado]);
    }

    private function pagoPendiente(Contratacion $c): Pago
    {
        $pago = (new Pago)->forceFill([
            'id_contratacion' => $c->id_contratacion, 'monto' => '10.00', 'porcentaje_comision' => '0.00', 'monto_comision' => '0.00',
            'monto_neto' => '10.00', 'fecha_pago' => '2026-11-04', 'metodo' => 'efectivo', 'estado' => 'pendiente',
            'referencia_externa' => 'SIM-PERM-' . $this->ref++, 'es_simulado' => true, 'proveedor' => 'simulado',
            'cedula_registra' => 1, 'fecha_registro' => now(),
        ]);
        $pago->save();

        return $pago;
    }

    private function pago(): array
    {
        return ['monto' => '25.00', 'fecha_pago' => now()->toDateString(), 'metodo' => 'efectivo'];
    }

    // ═════════════════════════ Autenticación ═════════════════════════

    public function test_sin_autenticacion_todo_responde_401(): void
    {
        foreach ([
            ['GET', '/api/contratador/postulaciones'], ['POST', '/api/contratador/publicaciones'],
            ['POST', "/api/contratador/contrataciones/{$this->contrA->id_contratacion}/pagos"], ['GET', '/api/contratador/kpis'],
            ['GET', '/api/trabajador/dashboard'], ['GET', '/api/trabajador/pagos'], ['POST', '/api/trabajador/postulaciones'],
            ['PUT', '/api/perfil'],
        ] as [$m, $url]) {
            $this->json($m, $url)->assertUnauthorized();
        }
    }

    // ═════════════════════════ CONTRATADOR ═════════════════════════

    public function test_contratador_acciones_permitidas_sobre_sus_registros(): void
    {
        Sanctum::actingAs($this->a);

        // Publicaciones: crear, editar y desactivar las propias.
        $nueva = $this->postJson('/api/contratador/publicaciones', [
            'titulo' => 'Nueva', 'tipo' => 'temporal', 'descripcion' => 'D', 'pago' => '50', 'lugar' => 'La Paz', 'id_categoria' => 1,
        ])->assertCreated()->json('data');
        $this->assertSame($this->a->cedula, (int) $nueva['cedula']);
        $this->putJson("/api/contratador/publicaciones/{$nueva['id_labor']}", [
            'titulo' => 'Editada', 'tipo' => 'temporal', 'descripcion' => 'D', 'pago' => '60', 'lugar' => 'La Paz', 'id_categoria' => 1,
        ])->assertOk();
        $this->deleteJson("/api/contratador/publicaciones/{$nueva['id_labor']}")->assertOk();
        $this->assertSame(0, (int) Publicacion::find($nueva['id_labor'])->estado, 'Desactivar no borra: estado = 0');

        // Postulaciones recibidas: solo las de sus publicaciones.
        $ids = collect($this->getJson('/api/contratador/postulaciones')->assertOk()->json())->pluck('id_labor')->unique()->all();
        $this->assertSame([$this->pubA->id_labor], array_values($ids));

        // Aceptar la postulación pendiente propia (crea la contratación).
        $this->putJson("/api/contratador/postulaciones/{$this->postPendA->id_postulacion}/estado", ['estado' => 'aceptada'])->assertOk();
        $this->assertTrue($this->postPendA->fresh()->tieneContratacion());

        // Contrataciones: solo las propias; registrar pago parcial y consultar su historial.
        $contr = collect($this->getJson('/api/contratador/contrataciones')->assertOk()->json());
        $this->assertNotContains($this->contrB->id_contratacion, $contr->pluck('id_contratacion')->all());
        $this->postJson("/api/contratador/contrataciones/{$this->contrA->id_contratacion}/pagos", $this->pago())
            ->assertCreated()->assertJsonPath('data.estado', 'pendiente')->assertJsonPath('simulado', true);
        $this->getJson("/api/contratador/contrataciones/{$this->contrA->id_contratacion}/pagos")->assertOk();

        // Finalizar y calificar la contratación propia.
        $this->putJson("/api/contratador/contrataciones/{$this->contrA->id_contratacion}/estado", ['estado' => 'finalizada'])->assertOk();
        $this->postJson('/api/contratador/calificaciones', ['id_contratacion' => $this->contrA->id_contratacion, 'puntuacion' => 5])->assertCreated();
        $this->assertSame($this->t->cedula, (int) Calificacion::first()->cedula_calificado);

        // KPIs propios y perfil propio.
        $this->getJson('/api/contratador/kpis')->assertOk()->assertJsonPath('alcance', 'contratador');
        $this->putJson('/api/perfil', ['nombre' => 'Contratador A', 'email' => 'a@redwork.test'])->assertOk();
        $this->assertSame('Contratador A', $this->a->fresh()->nombre);
    }

    public function test_contratador_no_accede_a_funciones_administrativas_ni_del_trabajador(): void
    {
        $pago = $this->pagoPendiente($this->contrA);
        Sanctum::actingAs($this->a);

        foreach ([
            ['GET', '/api/usuarios'], ['POST', '/api/admin/roles'], ['POST', '/api/admin/categorias'], ['PUT', '/api/admin/categorias/1'],
            ['GET', '/api/admin/usuarios'], ['GET', '/api/admin/pagos'], ['GET', '/api/admin/kpis'], ['GET', '/api/admin/kpis/indicadores'],
            ['GET', '/api/admin/reportes/usuarios'], ['GET', '/api/admin/postulaciones'], ['DELETE', "/api/admin/contrataciones/{$this->contrA->id_contratacion}"],
            ['PUT', "/api/admin/pagos/{$pago->id_pago}/confirmar"], ['PUT', "/api/admin/pagos/{$pago->id_pago}/anular"],
            ['POST', '/api/trabajador/postulaciones'], ['GET', '/api/trabajador/pagos'], ['GET', '/api/trabajador/kpis'],
        ] as [$m, $url]) {
            $this->assertSame(403, $this->json($m, $url, ['motivo_anulacion' => 'x'])->status(), "{$m} {$url}");
        }
        $this->assertSame('pendiente', $pago->fresh()->estado, 'El contratador no confirma ni anula pagos');
    }

    public function test_contratador_no_modifica_registros_de_otro_contratador(): void
    {
        Sanctum::actingAs($this->a);
        $datos = ['titulo' => 'X', 'tipo' => 'temporal', 'descripcion' => 'D', 'pago' => '1', 'lugar' => 'L', 'id_categoria' => 1];

        $this->putJson("/api/contratador/publicaciones/{$this->pubB->id_labor}", $datos)->assertForbidden();
        $this->deleteJson("/api/contratador/publicaciones/{$this->pubB->id_labor}")->assertForbidden();
        $this->putJson("/api/contratador/postulaciones/{$this->postPendB->id_postulacion}/estado", ['estado' => 'aceptada'])->assertForbidden();
        $this->putJson("/api/contratador/contrataciones/{$this->contrB->id_contratacion}/estado", ['estado' => 'cancelada'])->assertForbidden();
        $this->postJson("/api/contratador/contrataciones/{$this->contrB->id_contratacion}/pagos", $this->pago())->assertForbidden();
        $this->getJson("/api/contratador/contrataciones/{$this->contrB->id_contratacion}/pagos")->assertForbidden();

        $this->assertSame('Oferta de B', $this->pubB->fresh()->titulo);
        $this->assertSame(1, (int) $this->pubB->fresh()->estado);
        $this->assertSame('pendiente', $this->postPendB->fresh()->estado);
        $this->assertSame('activa', $this->contrB->fresh()->estado);
        $this->assertSame(0, Pago::where('id_contratacion', $this->contrB->id_contratacion)->count());
    }

    // ═════════════════════════ TRABAJADOR ═════════════════════════

    public function test_trabajador_acciones_permitidas_sobre_sus_registros(): void
    {
        $this->pagoPendiente($this->contrA);   // pago de T
        $this->pagoPendiente($this->contrB);   // pago de T2
        $otra = $this->publicacion($this->b, 'Otra oferta');
        Sanctum::actingAs($this->t);

        $this->getJson('/api/trabajador/dashboard')->assertOk()->assertJsonPath('success', true);
        $nueva = $this->postJson('/api/trabajador/postulaciones', ['id_labor' => $otra->id_labor, 'mensaje' => 'Hola'])->assertCreated()->json('data');

        $mias = collect($this->getJson('/api/trabajador/postulaciones')->assertOk()->json('data'));
        $this->assertSame([$this->t->cedula], $mias->pluck('cedula')->map(fn ($c) => (int) $c)->unique()->values()->all());

        $this->deleteJson("/api/trabajador/postulaciones/{$nueva['id_postulacion']}")->assertOk();

        $pagos = $this->getJson('/api/trabajador/pagos')->assertOk()->assertJsonPath('simulado', true)->json('data');
        $this->assertSame([$this->contrA->id_contratacion], array_values(array_unique(array_column($pagos, 'id_contratacion'))));

        $this->getJson('/api/trabajador/kpis')->assertOk()->assertJsonPath('alcance', 'trabajador');
        $this->putJson('/api/trabajador/perfil-postulante', ['habilidades' => 'Limpieza'])->assertOk();
        $this->putJson('/api/perfil', ['nombre' => 'Trabajador T', 'email' => 't@redwork.test'])->assertOk();

        // Calificar al contratador de su contratación finalizada.
        $this->contrA->update(['estado' => 'finalizada', 'fecha_fin' => '2026-11-05']);
        $this->postJson('/api/trabajador/calificaciones', ['id_contratacion' => $this->contrA->id_contratacion, 'puntuacion' => 4])->assertCreated();
        $this->assertSame($this->a->cedula, (int) Calificacion::first()->cedula_calificado);
    }

    public function test_trabajador_no_ejecuta_acciones_del_contratador_ni_administrativas(): void
    {
        $pago = $this->pagoPendiente($this->contrA);
        Sanctum::actingAs($this->t);

        foreach ([
            ['POST', '/api/contratador/publicaciones'], ['PUT', "/api/contratador/publicaciones/{$this->pubA->id_labor}"],
            ['DELETE', "/api/contratador/publicaciones/{$this->pubA->id_labor}"], ['GET', '/api/contratador/postulaciones'],
            ['PUT', "/api/contratador/postulaciones/{$this->postPendA->id_postulacion}/estado"], ['POST', '/api/contratador/contrataciones'],
            ['PUT', "/api/contratador/contrataciones/{$this->contrA->id_contratacion}/estado"],
            ['POST', "/api/contratador/contrataciones/{$this->contrA->id_contratacion}/pagos"],
            ['GET', "/api/contratador/contrataciones/{$this->contrA->id_contratacion}/pagos"], ['GET', '/api/contratador/kpis'],
            ['PUT', "/api/admin/pagos/{$pago->id_pago}/confirmar"], ['PUT', "/api/admin/pagos/{$pago->id_pago}/anular"],
            ['GET', '/api/usuarios'], ['POST', '/api/admin/categorias'], ['GET', '/api/admin/reportes/pagos'], ['GET', '/api/admin/kpis/indicadores'],
        ] as [$m, $url]) {
            $this->assertSame(403, $this->json($m, $url, ['estado' => 'aceptada', 'motivo_anulacion' => 'x'] + $this->pago())->status(), "{$m} {$url}");
        }

        $this->assertSame(1, (int) $this->pubA->fresh()->estado);
        $this->assertSame('pendiente', $this->postPendA->fresh()->estado);
        $this->assertSame('activa', $this->contrA->fresh()->estado);
        $this->assertSame('pendiente', $pago->fresh()->estado);
        $this->assertSame(1, Pago::count(), 'El trabajador no registra pagos');
    }

    public function test_trabajador_no_cancela_ni_califica_registros_ajenos(): void
    {
        Sanctum::actingAs($this->t);

        // Postulación de T2: la política existente responde 404 (no revela recursos ajenos).
        $this->deleteJson("/api/trabajador/postulaciones/{$this->postPendA->id_postulacion}")->assertNotFound();
        $this->assertNotNull($this->postPendA->fresh());

        // Contratación finalizada de T2: no participa → 403.
        $this->contrB->update(['estado' => 'finalizada', 'fecha_fin' => '2026-11-05']);
        $this->postJson('/api/trabajador/calificaciones', ['id_contratacion' => $this->contrB->id_contratacion, 'puntuacion' => 1])->assertForbidden();
        $this->assertSame(0, Calificacion::count());
    }

    // ═════════════════════════ Nivel 1: interfaz ═════════════════════════

    private function vista(User $u): array
    {
        $this->withoutVite();
        $html = $this->actingAs($u)->get('/dashboard')->assertOk()->getContent();
        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return [$html, new DOMXPath($dom)];
    }

    public function test_dashboard_contratador_organizado_y_sin_acciones_ajenas(): void
    {
        Publicacion::where('id_labor', $this->pubA->id_labor)->update(['estado' => 1]);
        [$html, $xp] = $this->vista($this->a);

        $menu = array_map(fn ($n) => trim(preg_replace('/\s+/u', ' ', $n->textContent)), iterator_to_array($xp->query("//aside[@id='sidebar']/a[contains(@class,'nav-item')]")));
        $this->assertSame(['🏠 Inicio', '📋 Mis Publicaciones', '📩 Postulaciones Recibidas', '🤝 Mis Contrataciones', '⭐ Calificaciones', '📊 Mis Indicadores', '👤 Mi Perfil'], $menu);

        // Solo sus publicaciones, con Editar y Desactivar (la API desactiva, no borra).
        $this->assertStringContainsString('Oferta de A', $html);
        $this->assertStringNotContainsString('Oferta de B', $html);
        $acciones = array_map(fn ($b) => trim($b->textContent), iterator_to_array($xp->query("//div[@id='panel-mis-publicaciones']//tbody//button")));
        $this->assertSame(['✏️ Editar', '🚫 Desactivar'], $acciones);

        // Tabla de postulaciones: encabezado alineado con las 5 celdas que genera el JS.
        $th = array_map(fn ($n) => trim($n->textContent), iterator_to_array($xp->query("//div[@id='panel-postulaciones']//thead//th")));
        $this->assertSame(['Trabajador', 'Labor / Oferta', 'Mensaje', 'Estado', 'Acciones'], $th);
        $js = file_get_contents(resource_path('js/contratador-dashboard.js'));
        $this->assertMatchesRegularExpression('#return `<tr>\s*(<td[^\n]*\n\s*){5}</tr>`;#', substr($js, strpos($js, 'tbody.innerHTML = data.map'), 2500));

        // Sin funciones administrativas ni de confirmación/anulación de pagos.
        foreach (['/api/admin/', '/confirmar', '/anular', 'route(\'reportes', 'Administrar'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html . $js, "No debe aparecer {$prohibido}");
        }
        $this->assertSame(0, $xp->query("//a[contains(@href,'/admin') or contains(@href,'reportes')]")->length);
    }

    public function test_dashboard_trabajador_organizado_y_en_solo_lectura_para_pagos(): void
    {
        [$html, $xp] = $this->vista($this->t);

        $menu = array_map(fn ($n) => $n->getAttribute('data-panel') . ($n->getAttribute('data-filtro') ? ':' . $n->getAttribute('data-filtro') : ''),
            iterator_to_array($xp->query("//aside[@id='sidebar']//a[contains(@class,'nav-item')]")));
        $this->assertSame(['home', 'buscar:todos', 'postulaciones', 'pagos', 'indicadores', 'calificaciones', 'perfil'], $menu);

        $js = file_get_contents(resource_path('js/trabajador-dashboard.js'));
        foreach (['/api/contratador/', '/api/admin/', "'POST'" . ')', '/pagos\'' . ", 'POST'"] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $js);
        }
        // Las únicas escrituras del trabajador: postularse, cancelar, calificar y su perfil.
        preg_match_all("#api\('([^']+)'[^)]*'(POST|PUT|DELETE)'#", $js, $m);
        $escrituras = array_unique(array_map(fn ($u, $v) => "{$v} {$u}", $m[1], $m[2]));
        sort($escrituras);
        $this->assertSame(['DELETE /api/trabajador/postulaciones/', 'POST /api/trabajador/calificaciones', 'POST /api/trabajador/postulaciones', 'PUT /api/perfil', 'PUT /api/trabajador/perfil-postulante'], array_values($escrituras));

        // Sin botones para crear/editar publicaciones ni registrar/confirmar pagos.
        $this->assertSame(0, $xp->query("//*[@id='panel-pagos']//button | //*[@id='panel-pagos']//form")->length);
        foreach (['Crear Publicación', 'Registrar pago', 'Confirmar pago', 'Anular pago', 'Aceptar</button>'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html);
        }
    }
}
