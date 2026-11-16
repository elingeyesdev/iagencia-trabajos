<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sistema de temas claro/oscuro en los cuatro dashboards (vistas renderizadas por Laravel), sin afectar
 * a las pantallas públicas ni a los reportes; sección Calificaciones del contratador y menú del trabajador.
 */
class TemasDashboardsTest extends TestCase
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

    private function usuario(string $rol): User
    {
        $u = User::factory()->create();
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

    public static function dashboards(): array
    {
        return [
            'administrador' => ['administrador', '/dashboard', 'rw-admin'],
            'business intelligence' => ['administrador', '/admin/kpis', 'rw-admin'],
            'contratador' => ['contratador', '/dashboard', 'rw-contratador'],
            'trabajador' => ['trabajador', '/dashboard', 'rw-trabajador'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dashboards')]
    public function test_dashboard_incluye_interruptor_y_arranque_del_tema(string $rol, string $url, string $clase): void
    {
        $html = $this->actingAs($this->usuario($rol))->get($url)->assertOk()->getContent();
        $xp = $this->xpath($html);

        $this->assertSame('dark', $xp->query('//html')->item(0)->getAttribute('data-theme'), 'Oscuro por defecto');
        $this->assertSame($clase, $xp->query('//body')->item(0)->getAttribute('class'));

        // Arranque en <head> antes de cualquier otro script: aplica la preferencia guardada sin parpadeo.
        $primerScript = $xp->query('//head/script')->item(0);
        $this->assertNotNull($primerScript);
        $this->assertStringContainsString("localStorage.getItem('redwork-theme')==='light'?'light':'dark'", $primerScript->textContent);

        // Interruptor único, accesible, dentro del encabezado.
        $sw = $xp->query("//nav[contains(@class,'topbar')]//button[@data-theme-toggle]");
        $this->assertSame(1, $sw->length);
        $boton = $sw->item(0);
        $this->assertSame('switch', $boton->getAttribute('role'));
        $this->assertSame('false', $boton->getAttribute('aria-checked'));
        $this->assertSame('button', $boton->getAttribute('type'));
        $this->assertNotSame('', $boton->getAttribute('aria-label'));
        $this->assertSame('Tema oscuro', trim($xp->query(".//*[contains(@class,'theme-switch-label')]", $boton)->item(0)->textContent));
    }

    public function test_pantallas_publicas_y_reportes_no_cambian(): void
    {
        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-theme-toggle', $html, $url);
            $this->assertStringNotContainsString('redwork-theme', $html, $url);
        }

        $admin = $this->usuario('administrador');
        $html = $this->actingAs($admin)->get(route('reportes.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-theme-toggle', $html);
    }

    public function test_contratador_tiene_seccion_calificaciones_en_el_menu(): void
    {
        $xp = $this->xpath($this->actingAs($this->usuario('contratador'))->get('/dashboard')->assertOk()->getContent());

        $menu = array_map(fn ($n) => $n->getAttribute('data-panel'), iterator_to_array($xp->query("//aside[@id='sidebar']/a[contains(@class,'nav-item')]")));
        $this->assertSame(['home', 'mis-publicaciones', 'postulaciones', 'contrataciones', 'calificaciones', 'indicadores', 'perfil'], $menu);
        $this->assertSame(1, $xp->query("//div[@id='panel-calificaciones']//tbody[@id='tbody-calificaciones']")->length);
        $this->assertSame(1, $xp->query("//div[@id='panel-calificaciones']//*[@id='calif-resumen-contratador']")->length);
        // El modal de calificar sigue siendo único (se reutiliza desde ambas secciones).
        $this->assertSame(1, $xp->query("//*[@id='modal-calificar']")->length);
    }

    public function test_trabajador_menu_con_una_sola_opcion_buscar_y_pestanas_internas(): void
    {
        $xp = $this->xpath($this->actingAs($this->usuario('trabajador'))->get('/dashboard')->assertOk()->getContent());

        $this->assertSame(1, $xp->query("//aside[@id='sidebar']//a[@data-panel='buscar']")->length);
        $this->assertSame(0, $xp->query("//aside[@id='sidebar']//a[@data-filtro='recurrente' or @data-filtro='temporal']")->length);
        foreach (['todos', 'recurrente', 'temporal', 'ciudad', 'mejor'] as $f) {
            $this->assertSame(1, $xp->query("//div[@id='filtros']//button[@data-filtro='{$f}']")->length, "Falta la pestaña {$f}");
        }
    }
}
