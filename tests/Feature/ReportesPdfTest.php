<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Contratacion;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\Publicacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Incremento 4 — Reportes en PDF: GET /api/admin/reportes/{tipo}/pdf (TCPDF 7, generado en memoria).
 *
 * Mismos datos sembrados y totales que ReportesTest (ver su docblock). El PDF se comprueba por su
 * contenido real: TCPDF 7 comprime SIEMPRE los streams (setCompression() no tiene efecto en el motor),
 * así que textoPdf() descomprime cada stream (gzuncompress) y extrae las cadenas de los operadores
 * «(…) Tj» (fuente núcleo helvetica → Windows-1252, se convierten a UTF-8). Cada celda/línea se dibuja con
 * su propio Tj, de modo que «valor seguido de etiqueta» identifica una celda del resumen estadístico.
 */
class ReportesPdfTest extends TestCase
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

        $this->travelTo(Carbon::parse('2026-11-14 10:20:30'));

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
            'estado' => $estado, 'referencia_externa' => 'SIM-PDF-' . $this->ref++,
            'cedula_registra' => $this->contratador->cedula, 'fecha_registro' => now(),
        ])->save();
    }

    /** GET /api/admin/reportes/{tipo}/pdf como lo pide reportes.js (Accept: application/pdf). */
    private function pdf(string $tipo, array $filtros = [], ?User $como = null): TestResponse
    {
        Sanctum::actingAs($como ?? $this->admin);

        return $this->get('/api/admin/reportes/' . $tipo . '/pdf' . ($filtros ? '?' . http_build_query($filtros) : ''), ['Accept' => 'application/pdf']);
    }

    /**
     * Texto del PDF: una entrada por operador Tj (celda o línea), en orden de dibujo (encabezado primero).
     *
     * @return list<string>
     */
    private function textoPdf(string $pdf): array
    {
        // Cada stream se extrae con su longitud exacta (/Length N >> stream\n…\nendstream, formato de TCPDF).
        // Antes se usaba #stream\r?\n(.*?)\r?\nendstream#s: si el último byte comprimido de una página era
        // 0x0D (\r) la expresión lo consumía, gzuncompress fallaba y esa página se descartaba en silencio
        // (fallo intermitente dependiente de los datos aleatorios del factory).
        preg_match_all('#/Length (\d+)\b[^>]*>>\s*stream\r?\n#', $pdf, $cabeceras, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $streams = [];
        foreach ($cabeceras as $c) {
            $streams[] = substr($pdf, $c[0][1] + strlen($c[0][0]), (int) $c[1][0]);
        }
        $this->assertNotEmpty($streams, 'No se encontraron streams en el PDF');
        $lineas = [];
        foreach ($streams as $stream) {
            $plano = @gzuncompress($stream);
            if ($plano === false) {
                continue; // imagen u otro stream no textual
            }
            preg_match_all('#\((.*?)\) Tj#s', $plano, $t);
            foreach ($t[1] as $cadena) {
                $lineas[] = mb_convert_encoding(stripcslashes($cadena), 'UTF-8', 'Windows-1252');
            }
        }

        return $lineas;
    }

    /** Afirma que $a va inmediatamente seguido de $b (p. ej. valor y etiqueta de una celda del resumen). */
    private function assertSeguido(array $lineas, string $a, string $b): void
    {
        foreach ($lineas as $i => $l) {
            if ($l === $a && ($lineas[$i + 1] ?? null) === $b) {
                $this->assertTrue(true);

                return;
            }
        }
        $this->fail("No se encontró «{$a}» seguido de «{$b}» en el PDF.");
    }

    /** @return array{0: float, 1: float} ancho y alto (pt) del primer /MediaBox */
    private function mediaBox(string $pdf): array
    {
        $this->assertSame(1, preg_match('#/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $m));

        return [(float) $m[3] - (float) $m[1], (float) $m[4] - (float) $m[2]];
    }

    /** [tipo, orientación, título, nombre en el archivo] */
    public static function tipos(): array
    {
        return [
            'usuarios'      => ['usuarios', 'L', 'REPORTE DE USUARIOS', 'Usuarios'],
            'publicaciones' => ['publicaciones', 'L', 'REPORTE DE PUBLICACIONES', 'Publicaciones'],
            'categorias'    => ['categorias', 'P', 'REPORTE DE CATEGORÍAS', 'Categorias'],
            'actividad'     => ['actividad', 'P', 'REPORTE DE ACTIVIDAD DEL SISTEMA', 'Actividad'],
            'pagos'         => ['pagos', 'L', 'REPORTE DE PAGOS SIMULADOS, INGRESOS Y COMISIONES', 'Pagos'],
        ];
    }

    // ── Respuesta, formato y diseño ──

    #[DataProvider('tipos')]
    public function test_pdf_valido_con_cabeceras_orientacion_encabezado_y_pie(string $tipo, string $orientacion, string $titulo, string $nombre): void
    {
        $r = $this->pdf($tipo)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="RedWork_Reporte_' . $nombre . '_20261114.pdf"');

        $cache = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);

        $pdf = $r->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringEndsWith('%%EOF', rtrim($pdf));
        $this->assertSame((string) strlen($pdf), $r->headers->get('Content-Length'));

        // LETTER: 612 × 792 pt. L = ancho > alto; P = alto > ancho.
        [$ancho, $alto] = $this->mediaBox($pdf);
        $this->assertEqualsWithDelta(612.0, min($ancho, $alto), 0.5);
        $this->assertEqualsWithDelta(792.0, max($ancho, $alto), 0.5);
        $orientacion === 'L' ? $this->assertGreaterThan($alto, $ancho) : $this->assertGreaterThan($ancho, $alto);

        $texto = $this->textoPdf($pdf);
        foreach (['RED-WORK', 'Sistema de gestión de empleos', 'Fecha: 14/11/2026   Hora: 10:20:30', 'Reporte N.º 20261114-102030',
            'Generado por: Ana Admin', $titulo, 'RESUMEN ESTADÍSTICO', 'RedWork © 2026'] as $esperado) {
            $this->assertContains($esperado, $texto, "Falta «{$esperado}» en el PDF de {$tipo}");
        }
        // Pie «Página X de Y» en todas las páginas (actividad ocupa 2 páginas en vertical con estos datos).
        $pies = array_values(array_filter($texto, fn ($l) => preg_match('/^Página \d+ de \d+$/', $l) === 1));
        $this->assertNotEmpty($pies);
        $total = count($pies);
        $this->assertSame(array_map(fn ($n) => "Página {$n} de {$total}", range(1, $total)), $pies);
        $this->assertTrue(collect($texto)->contains(fn ($l) => str_starts_with($l, 'Generado automáticamente por el Sistema RedWork')));
        // Texto plano: no se interpreta HTML ni se exponen hashes.
        $this->assertStringNotContainsString('$2y$', implode("\n", $texto));
    }

    // ── Totales (los mismos de la API JSON) ──

    public function test_pdf_usuarios_totales_por_rol_y_estado(): void
    {
        $texto = $this->textoPdf($this->pdf('usuarios')->assertOk()->getContent());

        $this->assertContains('Filtros: rol = todos · estado = todos', $texto);
        $this->assertSeguido($texto, '4', 'Usuarios');
        $this->assertSeguido($texto, '3', 'Activos');
        $this->assertSeguido($texto, '1', 'Inactivos');
        $this->assertSeguido($texto, 'trabajador', '2');
        $this->assertSeguido($texto, 'Total de usuarios', '4');
        $this->assertContains('Pedro Inactivo', $texto);
        $this->assertContains('Cédula', $texto);
    }

    public function test_pdf_publicaciones_suma_y_totales(): void
    {
        $texto = $this->textoPdf($this->pdf('publicaciones')->assertOk()->getContent());

        $this->assertSeguido($texto, '3', 'Publicaciones');
        $this->assertSeguido($texto, 'Bs 350.75', 'Suma de pagos ofertados');
        $this->assertSeguido($texto, 'Total (3 publicaciones)', '350.75');
        $this->assertContains('Mesa de roble', $texto);
        $this->assertContains('Carpintería', $texto);
    }

    public function test_pdf_categorias_cuenta_publicaciones(): void
    {
        $texto = $this->textoPdf($this->pdf('categorias')->assertOk()->getContent());

        $this->assertSeguido($texto, '2', 'Categorías');
        $this->assertSeguido($texto, 'Total de publicaciones en categorías existentes', '3');
        $this->assertContains('Jardinería', $texto);
    }

    public function test_pdf_actividad_conteos_y_banda_de_simulados_en_pagos(): void
    {
        $texto = $this->textoPdf($this->pdf('actividad')->assertOk()->getContent());

        $this->assertContains('Periodo: inicio a hoy', $texto);
        $this->assertSeguido($texto, '4', 'Usuarios registrados');
        $this->assertSeguido($texto, '3', 'Postulaciones');
        $this->assertSeguido($texto, '4', 'Pagos (simulados)');
        $this->assertContains('OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL', $texto);
        $this->assertContains('Pagos recientes (simulados)', $texto);
    }

    public function test_pdf_pagos_banda_de_simulados_y_totales_confirmados(): void
    {
        $texto = $this->textoPdf($this->pdf('pagos')->assertOk()->getContent());

        $this->assertContains('OPERACIONES SIMULADAS — NO REPRESENTAN DINERO REAL', $texto);
        $this->assertSeguido($texto, '4', 'Pagos');
        $this->assertSeguido($texto, 'Bs 45.15', 'Cobrado (confirmados)');
        $this->assertSeguido($texto, 'Bs 3.01', 'Comisión RedWork (confirmados)');
        $this->assertSeguido($texto, 'Bs 42.14', 'Neto trabajadores (confirmados)');
        $this->assertSeguido($texto, 'Confirmados (2)', '45.15');
        $this->assertContains('Sí', $texto);
    }

    public function test_pdf_sin_datos_muestra_sin_registros(): void
    {
        $texto = $this->textoPdf($this->pdf('actividad', ['desde' => '2030-01-01'])->assertOk()->getContent());

        $this->assertContains('Periodo: 2030-01-01 a hoy', $texto);
        $this->assertSeguido($texto, '0', 'Usuarios registrados');
        $this->assertGreaterThanOrEqual(5, count(array_keys($texto, 'Sin registros', true)));
    }

    // ── Filtros (mismos que la API JSON) ──

    public function test_pdf_aplica_los_filtros_y_coincide_con_la_api_json(): void
    {
        Sanctum::actingAs($this->admin);
        $json = $this->getJson('/api/admin/reportes/usuarios?rol=trabajador')->assertOk();
        $this->assertSame(2, $json->json('total'));

        $texto = $this->textoPdf($this->pdf('usuarios', ['rol' => 'trabajador'])->assertOk()->getContent());
        $this->assertContains('Filtros: rol = trabajador · estado = todos', $texto);
        $this->assertSeguido($texto, (string) $json->json('total'), 'Usuarios');
        $this->assertSeguido($texto, 'Total de usuarios', '2');
        $this->assertContains('Tania Trabajadora', $texto);
        $this->assertNotContains('Carlos Contratador', $texto);

        $texto = $this->textoPdf($this->pdf('publicaciones', ['categoria' => $this->catA->id_categoria])->assertOk()->getContent());
        $this->assertContains('Filtros: estado = todos · categoría = Carpintería · fecha de publicación: inicio a hoy', $texto);
        $this->assertSeguido($texto, 'Bs 300.75', 'Suma de pagos ofertados');
        $this->assertNotContains('Podar jardín', $texto);

        $texto = $this->textoPdf($this->pdf('pagos', ['estado' => 'pendiente'])->assertOk()->getContent());
        $this->assertContains('Filtros: estado = pendiente · fecha de pago: inicio a hoy', $texto);
        $this->assertSeguido($texto, '1', 'Pagos');
        $this->assertSeguido($texto, 'Bs 0.00', 'Cobrado (confirmados)');

        $texto = $this->textoPdf($this->pdf('actividad', ['desde' => '2026-01-01', 'hasta' => '2026-01-31'])->assertOk()->getContent());
        $this->assertContains('Periodo: 2026-01-01 a 2026-01-31', $texto);
        $this->assertSeguido($texto, '1', 'Usuarios registrados');
        $this->assertSeguido($texto, '2', 'Pagos (simulados)');
    }

    public static function filtrosInvalidos(): array
    {
        return [
            'usuarios rol inexistente'    => ['usuarios', ['rol' => 'inexistente'], 'rol'],
            'publicaciones categoría'     => ['publicaciones', ['categoria' => '1 OR 1=1'], 'categoria'],
            'actividad rango invertido'   => ['actividad', ['desde' => '2026-02-01', 'hasta' => '2026-01-01'], 'hasta'],
            'pagos estado manipulado'     => ['pagos', ['estado' => "confirmado' --"], 'estado'],
        ];
    }

    #[DataProvider('filtrosInvalidos')]
    public function test_pdf_con_filtros_invalidos_responde_422_json(string $tipo, array $filtros, string $campo): void
    {
        $r = $this->pdf($tipo, $filtros)->assertStatus(422)->assertJsonStructure(['message', 'errors' => [$campo]]);
        $this->assertStringStartsWith('application/json', (string) $r->headers->get('Content-Type'));
        $this->assertStringNotContainsString('%PDF', $r->getContent());
    }

    public function test_pdf_categorias_ignora_filtros_igual_que_la_api_json(): void
    {
        // La acción JSON de categorías no admite ni valida filtros; el PDF se comporta igual.
        $this->pdf('categorias', ['rol' => 'inexistente'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_indica_el_truncado_al_superar_limite_filas(): void
    {
        // Datos fijos (sin valores aleatorios del factory): el contenido del PDF es idéntico en cada ejecución.
        $base = User::factory()->raw(['ciudad' => 'La Paz', 'telefono' => '70000000', 'fechanac' => '1990-01-01']);
        $filas = [];
        for ($i = 0; $i < 1000; $i++) {
            $filas[] = array_merge($base, ['cedula' => 100000 + $i, 'nombre' => 'Masivo ' . $i, 'email' => "masivo{$i}@ejemplo.test",
                'contrasena' => Hash::make('x', ['rounds' => 4])]);
        }
        foreach (array_chunk($filas, 200) as $lote) {
            DB::table('usuarios')->insert($lote);
        }

        $texto = $this->textoPdf($this->pdf('usuarios')->assertOk()->getContent());
        $this->assertContains('Se muestran los primeros 1000 de 1004 registros (límite 1000 filas); los totales consideran todos.', $texto);
        $this->assertSeguido($texto, '1004', 'Usuarios');
        $this->assertSeguido($texto, 'Total de usuarios', '1004');
        // Varias páginas: la cabecera de la tabla se repite en cada página nueva y el pie numera todas.
        $paginas = count(array_filter($texto, fn ($l) => preg_match('/^Página \d+ de \d+$/', $l) === 1));
        $this->assertGreaterThan(10, $paginas);
        $this->assertContains("Página {$paginas} de {$paginas}", $texto);
        $this->assertSame($paginas, count(array_keys($texto, 'Fecha de registro', true)), 'cabecera de tabla repetida en cada página');
    }

    // ── Autorización ──

    #[DataProvider('tipos')]
    public function test_pdf_sin_token_no_entrega_el_pdf(string $tipo): void
    {
        // Con Accept JSON: 401. Con Accept PDF Laravel redirige al login (comportamiento global de la
        // API para clientes no JSON); reportes.js lo trata como sesión expirada. En ningún caso hay PDF.
        $this->getJson('/api/admin/reportes/' . $tipo . '/pdf')->assertUnauthorized();

        $r = $this->get('/api/admin/reportes/' . $tipo . '/pdf', ['Accept' => 'application/pdf']);
        $this->assertNotSame(200, $r->status());
        $this->assertStringNotContainsString('%PDF', (string) $r->getContent());
    }

    #[DataProvider('tipos')]
    public function test_pdf_contratador_y_trabajador_reciben_403(string $tipo): void
    {
        $this->pdf($tipo, [], $this->contratador)->assertForbidden();
        $this->pdf($tipo, [], $this->trabajador)->assertForbidden();

        Sanctum::actingAs($this->trabajador);
        $this->getJson('/api/admin/reportes/' . $tipo . '/pdf')->assertForbidden();
    }

    // ── Sin archivos temporales ──

    public function test_generar_pdfs_no_deja_archivos_temporales(): void
    {
        $storage = fn () => collect(\Illuminate\Support\Facades\File::allFiles(storage_path()))
            ->map(fn ($f) => $f->getPathname())
            ->reject(fn ($p) => str_contains($p, DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR))
            ->sort()->values()->all();
        // En el temporal del sistema solo se consideran nombres que podrían provenir de TCPDF / PDF
        // (otros procesos del equipo escriben ahí y harían la prueba inestable).
        $temporal = fn () => collect(glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . '*') ?: [])
            ->filter(fn ($p) => preg_match('/pdf|tcpdf|tc-?lib|font|img/i', basename($p)) === 1)
            ->sort()->values()->all();

        $antesStorage = $storage();
        $antesTemporal = $temporal();

        foreach (array_keys(self::tipos()) as $tipo) {
            $this->assertStringStartsWith('%PDF-', $this->pdf($tipo)->assertOk()->getContent());
        }

        $this->assertSame([], array_values(array_diff($storage(), $antesStorage)), 'Archivos nuevos en storage/');
        $this->assertSame([], array_values(array_diff($temporal(), $antesTemporal)), 'Archivos nuevos en el temporal del sistema');
    }
}
