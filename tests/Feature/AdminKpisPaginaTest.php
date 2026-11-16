<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Business Intelligence → Dashboard de KPIs en página propia (GET /admin/kpis, route admin.kpis).
 * Solo traslado de presentación: mismo módulo (kpi-dashboard.js) y mismo endpoint API.
 */
class AdminKpisPaginaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        foreach (['trabajador', 'contratador', 'administrador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    private function usuario(string $rol, bool $verificado = true): User
    {
        $u = $verificado ? User::factory()->create() : User::factory()->unverified()->create();
        $u->assignRole($rol);

        return $u;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return new DOMXPath($dom);
    }

    public function test_ruta_web_con_auth_verified_y_rol_administrador_sin_conflictos(): void
    {
        $ruta = Route::getRoutes()->getByName('admin.kpis');

        $this->assertNotNull($ruta);
        $this->assertSame('admin/kpis', $ruta->uri());
        $this->assertSame(['GET', 'HEAD'], $ruta->methods());
        $this->assertSame(['web', 'auth', 'verified', 'role:administrador'], $ruta->gatherMiddleware());

        $mismas = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => $r->uri() === 'admin/kpis');
        $this->assertCount(1, $mismas, 'No debe haber otra ruta en /admin/kpis');
        $this->assertSame('api/admin/kpis', Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/admin/kpis'))->uri());
    }

    public function test_permisos_de_la_pagina(): void
    {
        $this->get('/admin/kpis')->assertRedirect(route('login'));

        foreach (['contratador', 'trabajador'] as $rol) {
            $this->actingAs($this->usuario($rol))->get('/admin/kpis')->assertForbidden();
        }

        $this->actingAs($this->usuario('administrador', false))->get('/admin/kpis')->assertRedirect(route('verification.notice'));
        $this->actingAs($this->usuario('administrador'))->get('/admin/kpis')->assertOk();
    }

    public function test_pagina_monta_el_mismo_modulo_de_kpis_con_token_y_menu_bi_activo(): void
    {
        $admin = $this->usuario('administrador');
        $html = $this->actingAs($admin)->get('/admin/kpis')->assertOk()->getContent();
        $xp = $this->xpath($html);

        $kpi = $xp->query("//section[@id='kpi-admin'][@data-kpi-dashboard]");
        $this->assertSame(1, $kpi->length, 'Un único contenedor de KPIs');
        $this->assertSame('/api/admin/kpis/indicadores', $kpi->item(0)->getAttribute('data-endpoint'));
        $this->assertSame('global', $kpi->item(0)->getAttribute('data-alcance'));
        $this->assertSame('/api/categorias', $kpi->item(0)->getAttribute('data-categorias'));

        // Token de corta duración para consumir la API.
        $token = $xp->query("//meta[@name='api-token']")->item(0)->getAttribute('content');
        $this->assertNotSame('', $token);
        $registro = $admin->tokens()->where('name', 'dashboard-kpis')->first();
        $this->assertNotNull($registro?->expires_at);

        // Menú: BI debajo de Reportes, desplegado y con su opción activa.
        $grupos = array_map(fn ($n) => $n->getAttribute('id'), iterator_to_array($xp->query("//aside[@id='sidebar']/div[contains(@class,'nav-group')]")));
        $this->assertSame(['group-seguridad', 'group-param', 'group-trans', 'group-reportes', 'group-bi'], $grupos);
        $this->assertSame(1, $xp->query("//div[@id='group-bi'][contains(@class,'open')]//a[contains(@class,'active')][@href='" . route('admin.kpis') . "']")->length);

        // Las opciones del resto del menú vuelven a sus paneles del inicio.
        $this->assertSame(1, $xp->query("//a[@href='" . route('dashboard') . "#pagos-trans']")->length);
        $this->assertSame(1, $xp->query("//a[@href='" . route('reportes.index') . "']")->length);
    }

    public function test_inicio_del_admin_sin_kpis_duplicados_y_con_modulo_bi_tras_reportes(): void
    {
        $html = $this->actingAs($this->usuario('administrador'))->get('/dashboard')->assertOk()->getContent();
        $xp = $this->xpath($html);

        $this->assertSame(0, $xp->query('//*[@data-kpi-dashboard]')->length, 'Los KPIs ya no deben estar en el inicio');
        $this->assertStringNotContainsString('kpi-dashboard.js', $html);

        // Se conservan los demás componentes del inicio (estadísticas y auxiliares).
        $this->assertSame(1, $xp->query("//div[@id='panel-home']//div[@id='kpis-grid']")->length);
        $this->assertSame(2, $xp->query("//div[@id='panel-home']/div[contains(@class,'stats-grid')]")->length);

        $grupos = array_map(fn ($n) => $n->getAttribute('id'), iterator_to_array($xp->query("//aside[@id='sidebar']/div[contains(@class,'nav-group')]")));
        $this->assertSame(['group-seguridad', 'group-param', 'group-trans', 'group-reportes', 'group-bi'], $grupos);
        $enlace = $xp->query("//div[@id='group-bi']//a[@href='" . route('admin.kpis') . "']");
        $this->assertSame(1, $enlace->length);
        $this->assertStringContainsString('Dashboard de KPIs', $enlace->item(0)->textContent);
        $this->assertStringContainsString('Business Intelligence', $xp->query("//div[@id='group-bi']//div[contains(@class,'nav-group-title')]")->item(0)->textContent);
    }

    public function test_contratador_y_trabajador_no_ven_el_modulo_bi(): void
    {
        foreach (['contratador', 'trabajador'] as $rol) {
            $html = $this->actingAs($this->usuario($rol))->get('/dashboard')->assertOk()->getContent();
            $this->assertStringNotContainsString('Business Intelligence', $html);
            $this->assertStringNotContainsString(route('admin.kpis'), $html);
        }
    }
}
