<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Correcciones de interfaz: botón «Ver» retirado de Usuarios, sin «x» suelta en el encabezado
 * del administrador y datos mínimos del trabajador en #trabajador-me.
 */
class InterfazCorreccionesTest extends TestCase
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
        $u = User::factory()->create(['ciudad' => 'La Paz', 'telefono' => '71234567', 'direccion' => 'Calle Privada 123']);
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

    public function test_usuarios_sin_boton_ver_y_con_editar_y_eliminar(): void
    {
        $this->usuario('trabajador');
        $html = $this->actingAs($this->usuario('administrador'))->get('/dashboard')->assertOk()->getContent();
        $xp = $this->xpath($html);

        $filas = $xp->query("//table[@id='tabla-usuarios']/tbody/tr");
        $this->assertGreaterThanOrEqual(2, $filas->length);
        foreach ($filas as $fila) {
            $titulos = array_map(fn ($b) => $b->getAttribute('title'), iterator_to_array($xp->query(".//button[contains(@class,'icon-btn')]", $fila)));
            $this->assertSame(['Editar', 'Eliminar'], $titulos);
        }
        $this->assertStringNotContainsString('verUsuario(', $html);
        $this->assertSame(0, $xp->query("//*[@id='modal-ver-usuario']")->length);

        // El «Ver» de otras secciones se conserva (Roles: «Ver usuarios»).
        $this->assertGreaterThanOrEqual(1, $xp->query("//button[@title='Ver usuarios']")->length);
    }

    public function test_encabezado_admin_sin_caracteres_sueltos(): void
    {
        foreach (['/dashboard', '/admin/kpis'] as $url) {
            $html = $this->actingAs($this->usuario('administrador'))->get($url)->assertOk()->getContent();
            $xp = $this->xpath($html);

            $textoSuelto = '';
            foreach ($xp->query("//nav[contains(@class,'topbar')]/text()") as $nodo) {
                $textoSuelto .= trim($nodo->textContent);
            }
            $this->assertSame('', $textoSuelto, "Texto suelto en el encabezado de {$url}");
            $this->assertDoesNotMatchRegularExpression('#</div>x\s#', $html);
        }
    }

    public function test_datos_json_del_trabajador_solo_con_los_campos_usados(): void
    {
        $trabajador = $this->usuario('trabajador');
        $html = $this->actingAs($trabajador)->get('/dashboard')->assertOk()->getContent();

        $bloque = $this->xpath($html)->query("//script[@type='application/json'][@id='trabajador-me']");
        $this->assertSame(1, $bloque->length);
        $datos = json_decode($bloque->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['cedula', 'nombre', 'ciudad'], array_keys($datos));
        $this->assertSame([$trabajador->cedula, $trabajador->nombre, 'La Paz'], array_values($datos));
        foreach (['contrasena', 'password', 'token', 'email', 'telefono', 'direccion', 'remember_token', 'email_verified_at'] as $campo) {
            $this->assertArrayNotHasKey($campo, $datos);
        }
        $this->assertStringNotContainsString('Calle Privada 123', $bloque->item(0)->textContent);
    }
}
