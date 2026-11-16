<?php

namespace Tests\Feature\Api;

use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 3.0 — Pruebas mínimas de integridad (la batería completa la escribe redwork-qa).
 */
class IntegridadPostulacionContratacionTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 5000;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function crearUsuario(string $rol): User
    {
        $cedula = $this->siguienteCedula++;

        $user = User::create([
            'cedula'     => $cedula,
            'nombre'     => "Usuario {$cedula}",
            'email'      => "usuario{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'estado'     => 1,
        ]);
        $user->assignRole($rol);

        return $user;
    }

    private function crearPostulacion(User $contratador, User $trabajador, $pago = 100, string $estado = 'pendiente'): Postulacion
    {
        $publicacion = Publicacion::create([
            'tipo'              => 'oferta',
            'titulo'            => 'Trabajo',
            'descripcion'       => 'Descripción',
            'pago'              => $pago,
            'lugar'             => 'Ciudad',
            'fecha_publicacion' => now()->toDateString(),
            'cedula'            => $contratador->cedula,
            'id_categoria'      => 1,
            'estado'            => 1,
        ]);

        return Postulacion::create([
            'id_labor'          => $publicacion->id_labor,
            'cedula'            => $trabajador->cedula,
            'fecha_postulacion' => now()->toDateString(),
            'estado'            => $estado,
        ]);
    }

    public function test_aceptar_con_pago_cero_devuelve_422_y_no_cambia_estado(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, $this->crearUsuario('trabajador'), 0);

        Sanctum::actingAs($contratador);
        $this->putJson("/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado", ['estado' => 'aceptada'])
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede aceptar la postulación: el pago de la publicación debe ser mayor que cero.']);

        $this->assertSame('pendiente', $postulacion->fresh()->estado);
        $this->assertSame(0, Contratacion::count());
    }

    public function test_aceptar_crea_una_unica_contratacion_y_segunda_vez_devuelve_422(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, $this->crearUsuario('trabajador'), 150.5);
        $url = "/api/contratador/postulaciones/{$postulacion->id_postulacion}/estado";

        Sanctum::actingAs($contratador);
        $this->putJson($url, ['estado' => 'aceptada'])->assertOk()->assertJsonPath('message', 'Postulación aceptada.');
        $this->putJson($url, ['estado' => 'rechazada'])->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya fue procesada.']);

        $this->assertSame('aceptada', $postulacion->fresh()->estado);
        $this->assertSame(1, Contratacion::where('id_postulacion', $postulacion->id_postulacion)->count());
        $this->assertEquals(150.5, (float) Contratacion::first()->monto_acordado);
    }

    public function test_store_rechaza_monto_cero_con_mensaje_en_espanol(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, $this->crearUsuario('trabajador'), 100, 'aceptada');

        Sanctum::actingAs($contratador);
        $this->postJson('/api/contratador/contrataciones', [
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => now()->toDateString(),
            'monto_acordado' => 0,
        ])->assertStatus(422)
          ->assertJsonPath('errors.monto_acordado.0', 'El monto acordado debe ser mayor que cero.');

        $this->assertSame(0, Contratacion::count());
    }

    public function test_store_duplicado_devuelve_422(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, $this->crearUsuario('trabajador'), 100, 'aceptada');
        $payload = [
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => now()->toDateString(),
            'monto_acordado' => 80,
        ];

        Sanctum::actingAs($contratador);
        $this->postJson('/api/contratador/contrataciones', $payload)->assertCreated();
        $this->postJson('/api/contratador/contrataciones', $payload)->assertStatus(422)
            ->assertExactJson(['message' => 'Esta postulación ya tiene una contratación registrada.']);

        $this->assertSame(1, Contratacion::count());
    }

    public function test_admin_no_puede_borrar_postulacion_con_contratacion(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, $this->crearUsuario('trabajador'), 100, 'aceptada');
        Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => now()->toDateString(),
            'monto_acordado' => 100,
            'estado'         => 'activa',
        ]);

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/postulaciones/{$postulacion->id_postulacion}")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede eliminar una postulación vinculada a una contratación.']);

        $this->assertNotNull($postulacion->fresh());
    }

    public function test_admin_borra_postulacion_sin_contratacion(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $this->crearUsuario('trabajador'));

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/postulaciones/{$postulacion->id_postulacion}")
            ->assertOk()->assertExactJson(['message' => 'Postulación eliminada.']);

        $this->assertNull($postulacion->fresh());
    }

    public function test_admin_no_puede_borrar_contratacion_con_pagos_si_existe_la_tabla(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $this->crearUsuario('trabajador'), 100, 'aceptada');
        $contratacion = Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => now()->toDateString(),
            'monto_acordado' => 100,
            'estado'         => 'activa',
        ]);
        $admin = $this->crearUsuario('administrador');
        Sanctum::actingAs($admin);

        // Sin pagos: comportamiento actual (se elimina) se prueba con otra contratación.
        $this->assertFalse($contratacion->tieneHistorialFinanciero());

        // Pago válido en la tabla real `pagos` (Tarea 3.1).
        (new Pago())->forceFill([
            'id_contratacion'    => $contratacion->id_contratacion,
            'monto'              => 10,
            'monto_neto'         => 10,
            'fecha_pago'         => now()->toDateString(),
            'metodo'             => 'efectivo',
            'referencia_externa' => 'SIM-TEST-0001',
            'cedula_registra'    => $postulacion->publicacion->cedula,
            'fecha_registro'     => now(),
        ])->save();

        $this->deleteJson("/api/admin/contrataciones/{$contratacion->id_contratacion}")
            ->assertStatus(422)
            ->assertExactJson(['message' => 'No se puede eliminar una contratación con pagos registrados.']);

        $this->assertNotNull($contratacion->fresh());
    }

    public function test_admin_borra_contratacion_sin_pagos(): void
    {
        $postulacion = $this->crearPostulacion($this->crearUsuario('contratador'), $this->crearUsuario('trabajador'), 100, 'aceptada');
        $contratacion = Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => now()->toDateString(),
            'monto_acordado' => 100,
            'estado'         => 'activa',
        ]);

        Sanctum::actingAs($this->crearUsuario('administrador'));
        $this->deleteJson("/api/admin/contrataciones/{$contratacion->id_contratacion}")
            ->assertOk()->assertExactJson(['message' => 'Contratación eliminada.']);
    }

    public function test_actualizar_estado_contratacion_solo_desde_activa(): void
    {
        $contratador = $this->crearUsuario('contratador');
        $postulacion = $this->crearPostulacion($contratador, $this->crearUsuario('trabajador'), 100, 'aceptada');
        $contratacion = Contratacion::create([
            'id_postulacion' => $postulacion->id_postulacion,
            'fecha_inicio'   => now()->toDateString(),
            'monto_acordado' => 100,
            'estado'         => 'activa',
        ]);
        $url = "/api/contratador/contrataciones/{$contratacion->id_contratacion}/estado";

        Sanctum::actingAs($this->crearUsuario('contratador'));
        $this->putJson($url, ['estado' => 'finalizada'])->assertStatus(403)->assertExactJson(['message' => 'No autorizado.']);

        Sanctum::actingAs($contratador);
        $this->putJson($url, ['estado' => 'finalizada'])->assertOk()->assertJsonPath('data.estado', 'finalizada');
        $this->putJson($url, ['estado' => 'cancelada'])->assertStatus(422)
            ->assertExactJson(['message' => 'Solo una contratación activa puede cambiar de estado.']);
    }
}
