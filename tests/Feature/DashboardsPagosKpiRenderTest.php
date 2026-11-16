<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Bloque 6 — Renderizado de los dashboards por rol (GET /dashboard, usuarios verificados,
 * sin Vite) y presencia/ubicación de los elementos nuevos de pagos, KPIs, reportes y
 * Parametrización. La ubicación del menú se comprueba con DOM/XPath.
 */
class DashboardsPagosKpiRenderTest extends TestCase
{
    use RefreshDatabase;

    private const METODOS = ['efectivo', 'transferencia', 'qr', 'deposito', 'otro'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function usuario(string $rol): User
    {
        $u = User::factory()->create(); // verificado por defecto (email_verified_at = now())
        $u->assignRole($rol);

        return $u;
    }

    /** @return array{0: string, 1: DOMXPath} */
    private function dashboard(string $rol): array
    {
        $html = $this->actingAs($this->usuario($rol))->get('/dashboard')->assertOk()->getContent();

        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return [$html, new DOMXPath($dom)];
    }

    private function cuenta(DOMXPath $xp, string $expr): int
    {
        return $xp->query($expr)->length;
    }

    public function test_admin_dashboard_muestra_pagos_kpis_reportes_y_parametrizacion(): void
    {
        [, $xp] = $this->dashboard('administrador');

        foreach (['panel-pagos-trans', 'kpi-publicaciones-activas', 'kpi-contrataciones-activas', 'kpi-pagos-confirmados',
                  'modal-pago-detalle', 'modal-pago-confirmar', 'modal-pago-anular', 'pago-anular-motivo',
                  'pagos-filtro-estado', 'tbody-pagos', 'panel-comision'] as $id) {
            $this->assertSame(1, $this->cuenta($xp, "//*[@id='{$id}']"), "Falta (o está duplicado) #{$id}");
        }

        // Enlace a reportes.
        $href = route('reportes.index');
        $this->assertGreaterThanOrEqual(1, $this->cuenta($xp, "//a[@href='{$href}']"), 'Falta el enlace a Reportes');

        // Pagos dentro de Transaccional.
        $this->assertSame(1, $this->cuenta($xp, "//div[@id='group-trans']//a[contains(@onclick, \"showPanel('pagos-trans')\")]"));

        // Calificaciones dentro de Parametrización y NO en Transaccional.
        $this->assertSame(1, $this->cuenta($xp, "//div[@id='group-param']//a[contains(@onclick, \"showPanel('calificaciones-trans')\")]"));
        $this->assertSame(0, $this->cuenta($xp, "//div[@id='group-trans']//a[contains(@onclick, 'calificaciones')]"));
        $this->assertSame(0, $this->cuenta($xp, "//div[@id='group-trans']//a[contains(., 'Calificaciones')]"));
        $this->assertSame(1, $this->cuenta($xp, "//a[contains(@onclick, \"showPanel('calificaciones-trans')\")]"), 'Calificaciones debe aparecer una sola vez en el menú');

        // Comisión: el enlace del menú está comentado intencionalmente por el propietario (menú vigente),
        // así que no debe aparecer en ningún lugar del menú; el panel conserva el valor inicial de 0%.
        $this->assertSame(0, $this->cuenta($xp, "//a[contains(@onclick, \"showPanel('comision')\")]"), 'El enlace de Comisión está retirado del menú');
        $valor = $xp->query("//*[@id='panel-comision']//*[@id='comision-valor']")->item(0);
        $this->assertNotNull($valor);
        $this->assertSame('0%', trim($valor->textContent));

        // Filtro de estado con los 3 estados de pago.
        $estados = array_map(fn ($n) => $n->value, iterator_to_array($xp->query("//select[@id='pagos-filtro-estado']/option/@value")));
        $this->assertSame(['', 'pendiente', 'confirmado', 'anulado'], $estados);
    }

    public function test_contratador_dashboard_muestra_modal_pagos_con_los_cinco_metodos(): void
    {
        [, $xp] = $this->dashboard('contratador');

        $this->assertSame(1, $this->cuenta($xp, "//*[@id='modal-pagos']"));
        $this->assertSame(1, $this->cuenta($xp, "//*[@id='modal-pagos']//form[@id='form-pago']"));
        foreach (['pago-monto', 'pago-fecha', 'pago-observacion', 'pago-guardar', 'pago-contratacion', 'pago-bloqueo'] as $id) {
            $this->assertSame(1, $this->cuenta($xp, "//form[@id='form-pago']//*[@id='{$id}']"), "Falta #{$id} en form-pago");
        }
        $metodos = array_map(fn ($n) => $n->value, iterator_to_array($xp->query("//form[@id='form-pago']//select[@id='pago-metodo']/option/@value")));
        $this->assertSame(self::METODOS, $metodos);

        foreach (['monto', 'fecha_pago', 'metodo', 'observacion'] as $campo) {
            $this->assertSame(1, $this->cuenta($xp, "//form[@id='form-pago']//*[contains(@class,'pago-error') and @data-campo='{$campo}']"), "Falta el contenedor de error de {$campo}");
        }
        $this->assertSame(1, $this->cuenta($xp, "//meta[@name='api-token' and string-length(@content) > 0]"));
    }

    public function test_trabajador_dashboard_muestra_mis_pagos_en_solo_lectura(): void
    {
        [$html, $xp] = $this->dashboard('trabajador');

        $this->assertSame(1, $this->cuenta($xp, "//*[@id='panel-pagos']"));
        $this->assertSame(1, $this->cuenta($xp, "//a[@data-panel='pagos']"));
        $this->assertSame(1, $this->cuenta($xp, "//*[@id='panel-pagos']//*[@id='tbody-pagos']"));
        $this->assertSame(1, $this->cuenta($xp, "//*[@id='panel-pagos']//*[@id='tbody-pagos-resumen']"));

        // Sin botones, formularios ni acciones de escritura en el panel.
        $this->assertSame(0, $this->cuenta($xp, "//*[@id='panel-pagos']//button"));
        $this->assertSame(0, $this->cuenta($xp, "//*[@id='panel-pagos']//form"));
        $this->assertSame(0, $this->cuenta($xp, "//*[@id='panel-pagos']//*[@onclick or @data-action]"));
        $panel = $xp->query("//*[@id='panel-pagos']")->item(0)->textContent;
        foreach (['Confirmar', 'Anular', 'Registrar'] as $accion) {
            $this->assertStringNotContainsStringIgnoringCase($accion, $panel);
        }

        // El script (separado en resources/js/trabajador-dashboard.js y cargado con @vite)
        // solo consulta su endpoint de lectura; ni el script ni la vista usan endpoints de escritura.
        $script = file_get_contents(resource_path('js/trabajador-dashboard.js'));
        $this->assertStringContainsString("api('/api/trabajador/pagos')", $script);
        foreach (['script' => $script, 'html' => $html] as $origen => $texto) {
            $this->assertStringNotContainsString('/api/admin/pagos', $texto, $origen);
            $this->assertStringNotContainsString('/confirmar', $texto, $origen);
            $this->assertStringNotContainsString('/anular', $texto, $origen);
            $this->assertDoesNotMatchRegularExpression('#contrataciones/[^\'"]*/pagos#', $texto, $origen);
        }
        $this->assertStringContainsString("'resources/js/trabajador-dashboard.js'", file_get_contents(resource_path('views/dashboard.blade.php')));
    }
}
