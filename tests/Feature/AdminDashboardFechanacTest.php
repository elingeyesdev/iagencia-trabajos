<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;


class AdminDashboardFechanacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['administrador', 'contratador', 'trabajador'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        // El manifiesto local de Vite no es parte del repositorio; no se prueban assets.
        $this->withoutVite();
    }

    private function usuarioConRol(string $rol, array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos);
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function xpathDe(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        return new DOMXPath($dom);
    }

    // ───────────────────────── Vista (HTML renderizado) ─────────────────────────

    public function test_dashboard_admin_contiene_input_fechanac_dentro_del_modal_de_usuario(): void
    {
        $admin = $this->usuarioConRol('administrador');

        $respuesta = $this->actingAs($admin)->get('/dashboard');
        $respuesta->assertOk();
        $respuesta->assertViewIs('admin.dashboard');

        $xpath = $this->xpathDe($respuesta->getContent());

        $inputs = $xpath->query('//*[@id="f-fechanac"]');
        $this->assertSame(1, $inputs->length, 'Debe existir exactamente un elemento con id f-fechanac.');

        /** @var DOMElement $input */
        $input = $inputs->item(0);
        $this->assertSame('input', strtolower($input->nodeName));
        $this->assertSame('date', $input->getAttribute('type'));
        $this->assertSame('fechanac', $input->getAttribute('name'));

        // Debe estar dentro del modal «Nuevo usuario» y del grupo #grupo-fechanac.
        $this->assertSame(1, $xpath->query('//*[@id="modal-usuario"]//*[@id="f-fechanac"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="modal-usuario"]//*[@id="grupo-fechanac"]//*[@id="f-fechanac"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="grupo-fechanac"]')->length);

        // Etiqueta «Fecha de nacimiento» en el mismo grupo.
        $etiquetas = $xpath->query('//*[@id="grupo-fechanac"]/label');
        $this->assertSame(1, $etiquetas->length);
        $this->assertSame('Fecha de nacimiento', trim($etiquetas->item(0)->textContent));

        // El grupo no está oculto en el HTML inicial (alta = modo por defecto del modal).
        $grupo = $xpath->query('//*[@id="grupo-fechanac"]')->item(0);
        $this->assertStringNotContainsString('display:none', str_replace(' ', '', $grupo->getAttribute('style')));
    }

    public function test_el_campo_fechanac_no_altera_los_demas_campos_del_modal(): void
    {
        $admin = $this->usuarioConRol('administrador');
        $xpath = $this->xpathDe($this->actingAs($admin)->get('/dashboard')->getContent());

        foreach (['usuario-id', 'f-nombre', 'f-email', 'f-cedula', 'f-ciudad', 'f-estado', 'f-password', 'f-rol', 'grupo-password'] as $id) {
            $this->assertSame(1, $xpath->query("//*[@id=\"modal-usuario\"]//*[@id=\"{$id}\"]")->length, "Falta #{$id} en el modal.");
        }
    }

    public function test_trabajador_y_contratador_no_ven_el_dashboard_admin(): void
    {
        foreach (['trabajador', 'contratador'] as $rol) {
            $usuario = $this->usuarioConRol($rol);
            $respuesta = $this->actingAs($usuario)->get('/dashboard');
            $respuesta->assertOk();
            $respuesta->assertDontSee('id="f-fechanac"', false);
            $respuesta->assertDontSee('modal-usuario', false);
        }
    }

    public function test_sin_autenticar_redirige_a_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_sin_verificar_es_redirigido_a_la_verificacion(): void
    {
        $admin = User::factory()->unverified()->create();
        $admin->assignRole('administrador');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    // ───────────────────────── JavaScript (verificación estática) ─────────────────────────

    private function js(): string
    {
        $ruta = resource_path('js/admin-dashboard.js');
        $this->assertFileExists($ruta);

        return str_replace("\r\n", "\n", file_get_contents($ruta));
    }

    private function cuerpoFuncion(string $codigo, string $firmaRegex): string
    {
        $this->assertSame(1, preg_match('/'.$firmaRegex.'\s*\{/', $codigo, $m, PREG_OFFSET_CAPTURE), "No se encontró la función {$firmaRegex}.");
        $inicio = $m[0][1] + strlen($m[0][0]) - 1;

        // Recorre llaves balanceadas desde la apertura de la función.
        $nivel = 0;
        for ($i = $inicio, $n = strlen($codigo); $i < $n; $i++) {
            if ($codigo[$i] === '{') {
                $nivel++;
            } elseif ($codigo[$i] === '}') {
                $nivel--;
                if ($nivel === 0) {
                    return substr($codigo, $inicio, $i - $inicio + 1);
                }
            }
        }
        $this->fail("Llaves desbalanceadas en {$firmaRegex}.");
    }

    public function test_js_guardar_usuario_envia_fechanac_solo_al_crear(): void
    {
        $cuerpo = $this->cuerpoFuncion($this->js(), 'async\s+function\s+guardarUsuario\s*\(\s*\)');

        // La fecha se lee de #f-fechanac y se añade al body solo si no hay id (alta/POST).
        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*!\s*id\s*\)\s*\{?\s*body\.fechanac\s*=\s*document\.getElementById\(\s*['\"]f-fechanac['\"]\s*\)\.value\s*;?\s*\}?/",
            $cuerpo
        );

        // El objeto literal inicial (compartido con PUT) no incluye fechanac.
        $this->assertSame(1, preg_match('/const\s+body\s*=\s*\{(.*?)\};/s', $cuerpo, $literal));
        $this->assertStringNotContainsString('fechanac', $literal[1]);

        // fechanac solo aparece en la asignación condicionada.
        $this->assertSame(1, preg_match_all('/body\.fechanac/', $cuerpo));
        $this->assertSame(1, preg_match_all("/['\"]f-fechanac['\"]/", $cuerpo));

        // Sin id → POST /api/admin/usuarios; con id → PUT (comportamiento previo intacto).
        $this->assertMatchesRegularExpression("/const\s+method\s*=\s*id\s*\?\s*'PUT'\s*:\s*'POST'/", $cuerpo);
        $this->assertMatchesRegularExpression("/:\s*'\/api\/admin\/usuarios'/", $cuerpo);
    }

    public function test_js_editar_usuario_oculta_y_vacia_el_grupo_fechanac(): void
    {
        $cuerpo = $this->cuerpoFuncion($this->js(), 'function\s+editarUsuario\s*\([^)]*\)');

        $this->assertMatchesRegularExpression(
            "/document\.getElementById\(\s*['\"]grupo-fechanac['\"]\s*\)\.style\.display\s*=\s*['\"]none['\"]/",
            $cuerpo
        );
        $this->assertMatchesRegularExpression(
            "/document\.getElementById\(\s*['\"]f-fechanac['\"]\s*\)\.value\s*=\s*['\"]{2}/",
            $cuerpo
        );
        // El ocultamiento de la contraseña al editar se conserva.
        $this->assertMatchesRegularExpression(
            "/document\.getElementById\(\s*['\"]grupo-password['\"]\s*\)\.style\.display\s*=\s*['\"]none['\"]/",
            $cuerpo
        );
    }
}
