<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 1.7 (Opción A): mayoría de edad obligatoria en las altas hechas por un
 * administrador, en ambos endpoints:
 *   - POST /api/admin/usuarios  (AdminApiController::storeUsuario, 201)
 *   - POST /api/usuarios        (UsuarioApiController::store, 200)
 *
 * Fecha de referencia fijada en 2026-11-07: el límite de 18 años es 2008-11-07.
 */
class AdminUserAgeTest extends TestCase
{
    use RefreshDatabase;

    private const HOY = '2026-11-07 10:00:00';
    private const CLAVE = 'Clave-Segura-2026!';
    private const MSG_EDAD = 'El usuario debe tener al menos 18 años.';
    private const MSG_REQUERIDA = 'La fecha de nacimiento es obligatoria.';
    private const MSG_INVALIDA = 'La fecha de nacimiento no es una fecha válida.';
    private const MSG_FUTURA = 'La fecha de nacimiento no puede ser una fecha futura.';

    private const NUEVA_CEDULA = 55500111;
    private const NUEVO_EMAIL = 'alta.admin@redwork.test';

    private User $existente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::HOY));

        foreach (['administrador', 'contratador', 'trabajador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        // Usuario previo que no debe verse afectado por ninguna alta.
        $this->existente = User::factory()->create([
            'cedula'   => 44400111,
            'email'    => 'existente@redwork.test',
            'fechanac' => '1985-05-05',
        ]);
        $this->existente->assignRole('trabajador');
    }

    // ───────────────────────── Data providers ─────────────────────────

    /** @return array<string, array{0:string,1:int,2:string}> */
    public static function endpoints(): array
    {
        return [
            'admin/usuarios' => ['/api/admin/usuarios', 201, 'Usuario creado correctamente.'],
            'usuarios'       => ['/api/usuarios', 200, 'Usuario creado'],
        ];
    }

    /**
     * Casos de rechazo: [valor de fechanac (o marcador de ausencia), mensaje esperado].
     *
     * @return array<string, array{0:mixed,1:string}>
     */
    private static function rechazos(): array
    {
        return [
            'ausente'                  => ['__AUSENTE__', self::MSG_REQUERIDA],
            'vacia'                    => ['', self::MSG_REQUERIDA],
            'solo espacios'            => ['   ', self::MSG_REQUERIDA],
            'formato dd/mm/aaaa'       => ['07/11/1990', self::MSG_INVALIDA],
            'texto libre'              => ['no-es-fecha', self::MSG_INVALIDA],
            'fecha imposible 30-feb'   => ['2001-02-30', self::MSG_INVALIDA],
            'mes 13'                   => ['1990-13-01', self::MSG_INVALIDA],
            '29-feb en año no bisiesto'=> ['2001-02-29', self::MSG_INVALIDA],
            'arreglo'                  => [['1990-01-01'], self::MSG_INVALIDA],
            'futura (mañana)'          => ['2026-11-08', self::MSG_FUTURA],
            'futura lejana'            => ['2100-01-01', self::MSG_FUTURA],
            'hoy (0 años)'             => ['2026-11-07', self::MSG_EDAD],
            '17 años'                  => ['2009-11-07', self::MSG_EDAD],
            '18 años menos un dia'     => ['2008-11-08', self::MSG_EDAD],
        ];
    }

    /** @return array<string, array{0:string}> */
    private static function aceptadas(): array
    {
        return [
            'exactamente 18'        => ['2008-11-07'],
            '18 años y un dia'      => ['2008-11-06'],
            'mayor de 18'           => ['1990-01-01'],
            'nacido 29-feb bisiesto'=> ['2004-02-29'],
        ];
    }

    /** @return array<string, array{0:string,1:int,2:string,3:mixed,4:string}> */
    public static function endpointsPorRechazo(): array
    {
        $casos = [];
        foreach (self::endpoints() as $ep => [$url, $codigo, $mensaje]) {
            foreach (self::rechazos() as $nombre => [$valor, $error]) {
                $casos["{$ep} | {$nombre}"] = [$url, $codigo, $mensaje, $valor, $error];
            }
        }

        return $casos;
    }

    /** @return array<string, array{0:string,1:int,2:string,3:string}> */
    public static function endpointsPorAceptada(): array
    {
        $casos = [];
        foreach (self::endpoints() as $ep => [$url, $codigo, $mensaje]) {
            foreach (self::aceptadas() as $nombre => [$valor]) {
                $casos["{$ep} | {$nombre}"] = [$url, $codigo, $mensaje, $valor];
            }
        }

        return $casos;
    }

    // ───────────────────────── Ayudantes ─────────────────────────

    private function comoAdmin(): User
    {
        $admin = User::factory()->create(['cedula' => 10000001, 'email' => 'admin@redwork.test']);
        $admin->assignRole('administrador');
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function payload(mixed $fechanac = '1990-01-01'): array
    {
        $datos = [
            'nombre'   => 'Alta Administrativa',
            'cedula'   => self::NUEVA_CEDULA,
            'email'    => self::NUEVO_EMAIL,
            'password' => self::CLAVE,
            'ciudad'   => 'La Paz',
            'rol'      => 'trabajador',
        ];
        if ($fechanac !== '__AUSENTE__') {
            $datos['fechanac'] = $fechanac;
        }

        return $datos;
    }

    private function filaExistente(): array
    {
        return (array) DB::table('usuarios')->where('cedula', $this->existente->cedula)->first();
    }

    private function rolesExistente(): array
    {
        return DB::table('model_has_roles')
            ->where('model_id', $this->existente->cedula)
            ->orderBy('role_id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    // ───────────────────────── Rechazos ─────────────────────────

    #[DataProvider('endpointsPorRechazo')]
    public function test_fecha_no_valida_o_menor_de_edad_se_rechaza_con_422_y_no_crea_usuario(
        string $url, int $codigoOk, string $mensajeOk, mixed $fechanac, string $error
    ): void {
        $this->comoAdmin();
        $antesFila = $this->filaExistente();
        $antesRoles = $this->rolesExistente();
        $totalUsuarios = DB::table('usuarios')->count();
        $totalRoles = DB::table('model_has_roles')->count();

        $respuesta = $this->postJson($url, $this->payload($fechanac));

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors(['fechanac'])
            ->assertJsonPath('errors.fechanac', [$error]);

        $this->assertDatabaseMissing('usuarios', ['cedula' => self::NUEVA_CEDULA]);
        $this->assertDatabaseMissing('usuarios', ['email' => self::NUEVO_EMAIL]);
        $this->assertSame($totalUsuarios, DB::table('usuarios')->count());
        $this->assertSame($totalRoles, DB::table('model_has_roles')->count());

        $this->assertSame($antesFila, $this->filaExistente());
        $this->assertSame($antesRoles, $this->rolesExistente());
    }

    #[DataProvider('endpoints')]
    public function test_el_mensaje_de_edad_es_el_de_administrador_y_no_el_de_registro(string $url): void
    {
        $this->comoAdmin();

        $respuesta = $this->postJson($url, $this->payload('2009-11-07'));

        $respuesta->assertStatus(422);
        $this->assertSame([self::MSG_EDAD], $respuesta->json('errors.fechanac'));
        $this->assertStringNotContainsString('registrarte', $respuesta->getContent());
    }

    // ───────────────────────── Aceptaciones ─────────────────────────

    #[DataProvider('endpointsPorAceptada')]
    public function test_mayor_o_igual_a_18_crea_el_usuario_con_la_fecha_enviada(
        string $url, int $codigoOk, string $mensajeOk, string $fechanac
    ): void {
        $this->comoAdmin();
        $antesFila = $this->filaExistente();
        $antesRoles = $this->rolesExistente();
        $totalUsuarios = DB::table('usuarios')->count();

        $respuesta = $this->postJson($url, $this->payload($fechanac));

        $respuesta->assertStatus($codigoOk)
            ->assertJsonPath('message', $mensajeOk)
            ->assertJsonPath('data.cedula', self::NUEVA_CEDULA)
            ->assertJsonPath('data.email', self::NUEVO_EMAIL)
            ->assertJsonPath('data.fechanac', $fechanac)
            ->assertJsonStructure(['message', 'data' => ['cedula', 'nombre', 'email', 'fechanac', 'roles']])
            ->assertJsonMissingPath('data.contrasena');

        $this->assertDatabaseHas('usuarios', [
            'cedula'   => self::NUEVA_CEDULA,
            'email'    => self::NUEVO_EMAIL,
            'fechanac' => $fechanac,
        ]);
        $this->assertSame($totalUsuarios + 1, DB::table('usuarios')->count());

        $guardada = DB::table('usuarios')->where('cedula', self::NUEVA_CEDULA)->value('contrasena');
        $this->assertNotSame(self::CLAVE, $guardada);
        $this->assertTrue(Hash::check(self::CLAVE, $guardada));

        $this->assertTrue(User::find(self::NUEVA_CEDULA)->hasRole('trabajador'));

        // El usuario previo queda intacto.
        $this->assertSame($antesFila, $this->filaExistente());
        $this->assertSame($antesRoles, $this->rolesExistente());
    }

    #[DataProvider('endpoints')]
    public function test_secuencia_de_altas_validas_e_invalidas_no_altera_usuarios_existentes(
        string $url, int $codigoOk
    ): void {
        $admin = $this->comoAdmin();
        $antesFila = $this->filaExistente();
        $antesRoles = $this->rolesExistente();
        $antesAdmin = (array) DB::table('usuarios')->where('cedula', $admin->cedula)->first();

        $this->postJson($url, $this->payload('2008-11-08'))->assertStatus(422);
        $this->postJson($url, $this->payload('2008-11-07'))->assertStatus($codigoOk);
        $this->postJson($url, array_merge($this->payload('1990-01-01'), [
            'cedula' => self::NUEVA_CEDULA + 1,
            'email'  => 'otra.alta@redwork.test',
        ]))->assertStatus($codigoOk);
        $this->postJson($url, array_merge($this->payload('2026-12-01'), [
            'cedula' => self::NUEVA_CEDULA + 2,
            'email'  => 'futura@redwork.test',
        ]))->assertStatus(422);

        $this->assertDatabaseMissing('usuarios', ['cedula' => self::NUEVA_CEDULA + 2]);
        $this->assertSame($antesFila, $this->filaExistente());
        $this->assertSame($antesRoles, $this->rolesExistente());
        $this->assertSame($antesAdmin, (array) DB::table('usuarios')->where('cedula', $admin->cedula)->first());
    }

    // ───────────────────────── Autorización ─────────────────────────

    /** @return array<string, array{0:string,1:string}> */
    public static function endpointsPorRolNoAutorizado(): array
    {
        $casos = [];
        foreach (self::endpoints() as $ep => [$url]) {
            foreach (['trabajador', 'contratador'] as $rol) {
                $casos["{$ep} | {$rol}"] = [$url, $rol];
            }
            $casos["{$ep} | sin rol"] = [$url, ''];
        }

        return $casos;
    }

    #[DataProvider('endpointsPorRolNoAutorizado')]
    public function test_roles_no_administradores_reciben_403_y_no_crean_usuario(string $url, string $rol): void
    {
        $usuario = User::factory()->create(['cedula' => 20000002, 'email' => 'actor@redwork.test']);
        if ($rol !== '') {
            $usuario->assignRole($rol);
        }
        Sanctum::actingAs($usuario);
        $total = DB::table('usuarios')->count();

        // Incluso con una fecha válida de mayor de edad.
        $this->postJson($url, $this->payload('1990-01-01'))->assertStatus(403);
        // Y con una de menor de edad: la autorización se evalúa antes que la validación.
        $this->postJson($url, $this->payload('2010-01-01'))->assertStatus(403);

        $this->assertDatabaseMissing('usuarios', ['cedula' => self::NUEVA_CEDULA]);
        $this->assertSame($total, DB::table('usuarios')->count());
    }

    #[DataProvider('endpoints')]
    public function test_sin_autenticar_recibe_401_y_no_crea_usuario(string $url): void
    {
        $total = DB::table('usuarios')->count();

        $this->postJson($url, $this->payload('1990-01-01'))->assertStatus(401);
        $this->postJson($url, $this->payload('2010-01-01'))->assertStatus(401);

        $this->assertDatabaseMissing('usuarios', ['cedula' => self::NUEVA_CEDULA]);
        $this->assertSame($total, DB::table('usuarios')->count());
    }
}
