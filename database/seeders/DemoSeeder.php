<?php

namespace Database\Seeders;

use App\Models\Pago;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

/**
 * Datos de DEMOSTRACIÓN de RedWork (pagos simulados incluidos).
 *
 * - Solo para bases de datos locales o desechables. Se niega a ejecutarse en producción.
 * - Idempotente: puede ejecutarse varias veces sin duplicar datos.
 * - Uso: php artisan db:seed --class=RolesSeeder
 *        php artisan db:seed --class=ParametrosSeeder
 *        php artisan db:seed --class=DemoSeeder
 *
 * Cuentas de demostración (todas con correo verificado):
 *   admin.demo@redwork.test        (administrador)  cédula 9000001
 *   contratador.demo@redwork.test  (contratador)    cédula 9000002
 *   trabajador.demo@redwork.test   (trabajador)     cédula 9000003
 * Contraseña de las tres cuentas: la constante PASSWORD de esta clase.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo-RedWork-2026!';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder no puede ejecutarse en producción.');
        }

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $admin       = $this->usuario(9000001, 'Ana Administradora', 'admin.demo@redwork.test', 'administrador');
        $contratador = $this->usuario(9000002, 'Carlos Contratador', 'contratador.demo@redwork.test', 'contratador');
        $trabajador  = $this->usuario(9000003, 'Tania Trabajadora', 'trabajador.demo@redwork.test', 'trabajador');

        $idCategoria = DB::table('categorias')->where('nombre_categoria', 'Construcción (demo)')->value('id_categoria')
            ?? DB::table('categorias')->insertGetId([
                'nombre_categoria' => 'Construcción (demo)',
                'descripcion'      => 'Categoría de demostración',
                'estado'           => 1,
            ]);

        $pub1 = $this->publicacion('Pintado de fachada (demo)', 500, 1, $contratador->cedula, $idCategoria);
        $pub2 = $this->publicacion('Instalación eléctrica (demo)', 300, 1, $contratador->cedula, $idCategoria);
        $this->publicacion('Jardinería (demo, inactiva)', 120, 0, $contratador->cedula, $idCategoria);

        $c1 = $this->contratacion($pub1, $trabajador->cedula, 500, 'activa');
        $c2 = $this->contratacion($pub2, $trabajador->cedula, 300, 'finalizada');

        $this->pago($c1, 'SIM-DEMO-0001', '150.00', 'transferencia', 'confirmado', $contratador->cedula, $admin->cedula);
        $this->pago($c1, 'SIM-DEMO-0002', '100.00', 'efectivo', 'pendiente', $contratador->cedula, null);
        $this->pago($c2, 'SIM-DEMO-0003', '50.00', 'qr', 'anulado', $contratador->cedula, $admin->cedula);
    }

    private function usuario(int $cedula, string $nombre, string $email, string $rol): User
    {
        $user = User::where('cedula', $cedula)->first();

        if (! $user) {
            $user = new User();
            $user->forceFill([
                'cedula'            => $cedula,
                'nombre'            => $nombre,
                'email'             => $email,
                'contrasena'        => Hash::make(self::PASSWORD),
                'ciudad'            => 'La Paz',
                'fechanac'          => '1990-01-01',
                'estado'            => 1,
                'email_verified_at' => now(),
            ])->save();
        }

        if (! $user->hasRole($rol)) {
            $user->assignRole($rol);
        }

        return $user;
    }

    private function publicacion(string $titulo, int $pago, int $estado, int $cedula, int $idCategoria): int
    {
        return DB::table('publicaciones')->where('titulo', $titulo)->value('id_labor')
            ?? DB::table('publicaciones')->insertGetId([
                'tipo'              => 'Servicio',
                'titulo'            => $titulo,
                'descripcion'       => 'Publicación de demostración.',
                'pago'              => $pago,
                'lugar'             => 'La Paz',
                'fecha_publicacion' => now()->toDateString(),
                'cedula'            => $cedula,
                'id_categoria'      => $idCategoria,
                'estado'            => $estado,
            ]);
    }

    private function contratacion(int $idLabor, int $cedulaTrabajador, int $monto, string $estado): int
    {
        $idPostulacion = DB::table('postulaciones')
            ->where('id_labor', $idLabor)->where('cedula', $cedulaTrabajador)->value('id_postulacion')
            ?? DB::table('postulaciones')->insertGetId([
                'id_labor'          => $idLabor,
                'cedula'            => $cedulaTrabajador,
                'mensaje'           => 'Postulación de demostración.',
                'fecha_postulacion' => now()->toDateString(),
                'estado'            => 'aceptada',
            ]);

        return DB::table('contrataciones')->where('id_postulacion', $idPostulacion)->value('id_contratacion')
            ?? DB::table('contrataciones')->insertGetId([
                'id_postulacion' => $idPostulacion,
                'fecha_inicio'   => now()->toDateString(),
                'fecha_fin'      => $estado === 'activa' ? null : now()->toDateString(),
                'monto_acordado' => $monto,
                'estado'         => $estado,
            ]);
    }

    private function pago(int $idContratacion, string $ref, string $monto, string $metodo, string $estado, int $registra, ?int $admin): void
    {
        if (Pago::where('proveedor', 'simulado')->where('referencia_externa', $ref)->exists()) {
            return;
        }

        (new Pago())->forceFill([
            'id_contratacion'     => $idContratacion,
            'monto'               => $monto,
            'porcentaje_comision' => '0.00',
            'monto_comision'      => '0.00',
            'monto_neto'          => $monto,
            'fecha_pago'          => now()->toDateString(),
            'metodo'              => $metodo,
            'estado'              => $estado,
            'es_simulado'         => true,
            'proveedor'           => 'simulado',
            'referencia_externa'  => $ref,
            'observacion'         => 'Pago simulado de demostración.',
            'cedula_registra'     => $registra,
            'fecha_registro'      => now(),
            'cedula_confirma'     => $estado === 'confirmado' ? $admin : null,
            'fecha_confirmacion'  => $estado === 'confirmado' ? now() : null,
            'cedula_anula'        => $estado === 'anulado' ? $admin : null,
            'fecha_anulacion'     => $estado === 'anulado' ? now() : null,
            'motivo_anulacion'    => $estado === 'anulado' ? 'Anulación de demostración.' : null,
        ])->save();
    }
}
