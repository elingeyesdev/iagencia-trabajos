<?php

namespace Tests\Feature\Api;

use App\Models\RolAsignacionHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Historial de roles desde Administrar Usuario (PUT /api/admin/usuarios/{id}, AdminApiController::updateUsuario):
 * registra asignación / cambio / retirada en la misma transacción que la actualización, no registra si el rol
 * no cambia, protege al último administrador activo y revierte TODO si falla el historial.
 */
class HistorialUpdateUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private int $siguienteCedula = 8100;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
        $this->admin = $this->crearUsuario('administrador', ['nombre' => 'Admin Responsable']);
    }

    private function crearUsuario(?string $rol = null, array $datos = []): User
    {
        $cedula = $this->siguienteCedula++;
        $user = User::create(array_merge([
            'cedula'     => $cedula,
            'nombre'     => "Usuario {$cedula}",
            'email'      => "usuario{$cedula}@redwork.test",
            'contrasena' => Hash::make('Clave-Prueba-123'),
            'ciudad'     => 'La Paz',
            'estado'     => 1,
        ], $datos));
        if ($rol) {
            $user->assignRole($rol);
        }
        return $user;
    }

    /** Payload completo de updateUsuario con los datos actuales del usuario, sobrescribibles. */
    private function payload(User $u, array $cambios = []): array
    {
        return array_merge([
            'nombre' => $u->nombre,
            'email'  => $u->email,
            'cedula' => $u->cedula,
            'ciudad' => $u->ciudad,
            'estado' => (string) $u->estado,
            'rol'    => $u->roles()->pluck('name')->first(),
        ], $cambios);
    }

    private function actualizar(User $u, array $cambios = [])
    {
        Sanctum::actingAs($this->admin);
        return $this->putJson("/api/admin/usuarios/{$u->cedula}", $this->payload($u, $cambios));
    }

    private function rolesDe(int $cedula): array
    {
        return User::findOrFail($cedula)->roles()->pluck('name')->all();
    }

    public function test_cambio_de_rol_desde_administrar_usuario_registra_cambio(): void
    {
        $u = $this->crearUsuario('trabajador');

        $this->actualizar($u, ['rol' => 'contratador'])
            ->assertOk()
            ->assertJsonPath('message', 'Usuario actualizado.')
            ->assertJsonPath('data.roles.0.name', 'contratador');

        $this->assertSame(['contratador'], $this->rolesDe($u->cedula));
        $this->assertSame(1, RolAsignacionHistorial::count());
        $h = RolAsignacionHistorial::first();
        $this->assertSame(RolAsignacionHistorial::ACCION_CAMBIO, $h->accion);
        $this->assertSame('trabajador', $h->rol_anterior);
        $this->assertSame('contratador', $h->rol_nuevo);
        $this->assertSame($u->cedula, (int) $h->usuario_cedula);
        $this->assertSame($this->admin->cedula, (int) $h->admin_cedula);
        $this->assertSame('Admin Responsable', $h->admin_nombre);
        $this->assertSame('127.0.0.1', $h->ip);
        $this->assertNotNull($h->created_at);
    }

    public function test_asignacion_inicial_y_retirada_desde_administrar_usuario(): void
    {
        $u = $this->crearUsuario(null);

        $this->actualizar($u, ['rol' => 'trabajador'])->assertOk();
        $this->assertSame(['trabajador'], $this->rolesDe($u->cedula));

        $u->refresh();
        $this->actualizar($u, ['rol' => null])->assertOk();
        $this->assertSame([], $this->rolesDe($u->cedula));

        $acciones = RolAsignacionHistorial::orderBy('id')->get(['accion', 'rol_anterior', 'rol_nuevo'])->toArray();
        $this->assertSame([
            ['accion' => 'asignacion', 'rol_anterior' => null, 'rol_nuevo' => 'trabajador'],
            ['accion' => 'retirada', 'rol_anterior' => 'trabajador', 'rol_nuevo' => null],
        ], $acciones);
    }

    public function test_actualizar_datos_personales_sin_cambiar_rol_no_registra(): void
    {
        $u = $this->crearUsuario('trabajador');

        $this->actualizar($u, ['nombre' => 'Nombre Nuevo', 'email' => 'nuevo@redwork.test', 'ciudad' => 'Oruro'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre Nuevo');

        $this->assertSame(['trabajador'], $this->rolesDe($u->cedula));
        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_mismo_rol_no_registra_cambio(): void
    {
        $u = $this->crearUsuario('contratador');

        $this->actualizar($u, ['rol' => 'contratador'])->assertOk();

        $this->assertSame(['contratador'], $this->rolesDe($u->cedula));
        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_sin_rol_que_sigue_sin_rol_no_registra(): void
    {
        $u = $this->crearUsuario(null);

        $this->actualizar($u, ['rol' => null, 'nombre' => 'Otro'])->assertOk();

        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_ultimo_administrador_activo_no_puede_perder_el_rol_ni_cambiarse_datos_en_esa_peticion(): void
    {
        // $this->admin es el único administrador activo.
        foreach (['trabajador', null] as $rolNuevo) {
            $this->actualizar($this->admin, ['rol' => $rolNuevo, 'nombre' => 'No debe guardarse'])
                ->assertStatus(422)
                ->assertJsonValidationErrors('rol');
        }

        $this->assertSame(['administrador'], $this->rolesDe($this->admin->cedula));
        $this->assertSame('Admin Responsable', $this->admin->fresh()->nombre);
        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_con_dos_administradores_si_se_puede_cambiar_el_rol_de_uno(): void
    {
        $otroAdmin = $this->crearUsuario('administrador');

        $this->actualizar($otroAdmin, ['rol' => 'trabajador'])->assertOk();

        $this->assertSame(['trabajador'], $this->rolesDe($otroAdmin->cedula));
        $this->assertSame(1, RolAsignacionHistorial::where('accion', 'cambio')->count());
    }

    public function test_si_falla_el_historial_se_revierte_rol_y_datos(): void
    {
        $u = $this->crearUsuario('trabajador', ['nombre' => 'Nombre Original']);
        Event::listen('eloquent.creating: ' . RolAsignacionHistorial::class, function () {
            throw new \RuntimeException('Fallo simulado del historial');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actualizar($u, ['rol' => 'contratador', 'nombre' => 'Nombre Cambiado']);
            $this->fail('Se esperaba la excepción simulada.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado del historial', $e->getMessage());
        }

        $this->assertSame(['trabajador'], $this->rolesDe($u->cedula));
        $this->assertSame('Nombre Original', $u->fresh()->nombre);
        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_cambio_de_cedula_y_rol_registra_con_la_cedula_nueva_y_conserva_el_rol(): void
    {
        $u = $this->crearUsuario('trabajador');
        $nueva = 8999;

        $this->actualizar($u, ['cedula' => $nueva, 'rol' => 'contratador'])->assertOk();

        $this->assertNull(User::find($u->cedula));
        $this->assertSame(['contratador'], $this->rolesDe($nueva));
        $h = RolAsignacionHistorial::sole();
        $this->assertSame($nueva, (int) $h->usuario_cedula);
        $this->assertSame('cambio', $h->accion);
    }

    public function test_validaciones_existentes_se_conservan_y_no_registran(): void
    {
        $u = $this->crearUsuario('trabajador');

        $this->actualizar($u, ['rol' => 'rol-inexistente'])->assertStatus(422)->assertJsonValidationErrors('rol');
        $this->actualizar($u, ['email' => 'no-es-correo'])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame(['trabajador'], $this->rolesDe($u->cedula));
        $this->assertSame(0, RolAsignacionHistorial::count());
    }

    public function test_solo_administradores_pueden_actualizar_usuarios(): void
    {
        $u = $this->crearUsuario('trabajador');
        $payload = $this->payload($u, ['rol' => 'contratador']);

        $this->putJson("/api/admin/usuarios/{$u->cedula}", $payload)->assertUnauthorized();

        foreach (['trabajador', 'contratador'] as $rol) {
            Sanctum::actingAs($this->crearUsuario($rol));
            $this->putJson("/api/admin/usuarios/{$u->cedula}", $payload)->assertForbidden();
        }

        $this->assertSame(['trabajador'], $this->rolesDe($u->cedula));
        $this->assertSame(0, RolAsignacionHistorial::count());
    }
}
