<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Preparación para Render: HTTPS detrás del proxy sin romper Laragon (HTTP), enlaces firmados de
 * verificación válidos, cookies seguras, cabeceras de proxy limitadas, recuperación de contraseña
 * sin 500 si el correo falla y comprobaciones estáticas de los archivos de despliegue.
 * No usa la base real: SQLite en memoria (phpunit.xml).
 */
class ProduccionDespliegueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        URL::forceScheme(null);
        parent::tearDown();
    }

    /** Vuelve a ejecutar AppServiceProvider::boot() con el entorno y HAS_SSL indicados. */
    private function arrancarCon(string $entorno, mixed $hasSsl): void
    {
        $this->app['env'] = $entorno;
        config(['app.env' => $entorno, 'app.force_https' => $hasSsl]);
        URL::forceScheme(null);
        (new AppServiceProvider($this->app))->boot();
    }

    // ── HTTPS: Laragon (local) sigue en HTTP; producción genera HTTPS ──

    public function test_local_sin_has_ssl_genera_urls_http(): void
    {
        $this->arrancarCon('local', null);

        $this->assertStringStartsWith('http://', url('/dashboard'));
        $this->assertStringStartsWith('http://', route('login'));
    }

    public function test_produccion_genera_urls_https_aunque_app_url_sea_http(): void
    {
        $this->arrancarCon('production', null);

        $this->assertStringStartsWith('https://', url('/dashboard'));
        $this->assertStringStartsWith('https://', asset('build/manifest.json'));
        $firmada = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => 1, 'hash' => 'x']);
        $this->assertStringStartsWith('https://', $firmada);
    }

    public function test_has_ssl_explicito_manda_en_local(): void
    {
        $this->arrancarCon('local', false);
        $this->assertStringStartsWith('http://', url('/'));

        $this->arrancarCon('local', true);
        $this->assertStringStartsWith('https://', url('/'));
    }

    public function test_has_ssl_se_lee_desde_config_y_no_con_env_en_el_proveedor(): void
    {
        // Con config:cache, env() fuera de config/ devuelve null: el proveedor debe usar config().
        $fuente = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringNotContainsString("env('HAS_SSL')", $fuente);
        $this->assertStringContainsString("config('app.force_https')", $fuente);
        $this->assertSame([], array_values(array_filter(
            glob(app_path('{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE),
            fn ($f) => preg_match('/\benv\(/', file_get_contents($f))
        )), 'No debe usarse env() fuera de config/ (se pierde con config:cache)');
    }

    // ── Proxy de Render: esquema HTTPS reconocido y enlace de verificación firmado válido ──

    private function enlaceVerificacionHttpReenviado(User $user): string
    {
        $this->arrancarCon('production', null);
        $firmada = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $this->assertStringStartsWith('https://', $firmada);

        // Render termina TLS y reenvía al contenedor por HTTP con X-Forwarded-Proto: https.
        return 'http://' . substr($firmada, strlen('https://'));
    }

    public function test_verificacion_de_correo_funciona_detras_del_proxy_de_confianza(): void
    {
        config(['trustedproxy.proxies' => '*']);
        $user = User::factory()->unverified()->create();
        $enlace = $this->enlaceVerificacionHttpReenviado($user);

        $this->actingAs($user)
            ->get($enlace, ['X-Forwarded-Proto' => 'https'])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_sin_proxy_de_confianza_las_cabeceras_reenviadas_se_ignoran(): void
    {
        config(['trustedproxy.proxies' => null]);
        $user = User::factory()->unverified()->create();
        $enlace = $this->enlaceVerificacionHttpReenviado($user);

        // Sin confiar en el proxy la URL vista es http:// y la firma (calculada sobre https://) no coincide.
        $this->actingAs($user)
            ->get($enlace, ['X-Forwarded-Proto' => 'https'])
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_el_proxy_no_puede_alterar_host_ni_puerto_de_las_urls(): void
    {
        config(['trustedproxy.proxies' => '*']);
        Route::get('/_prueba_url_proxy', fn () => response()->json([
            'url' => url('/x'),
            'secure' => request()->secure(),
            'ip' => request()->ip(),
        ]));

        $r = $this->get('http://localhost/_prueba_url_proxy', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Host' => 'atacante.test',
            'X-Forwarded-Port' => '8443',
        ])->assertOk();

        $this->assertTrue($r->json('secure'));
        $this->assertSame('203.0.113.7', $r->json('ip'));
        $this->assertSame('https://localhost/x', $r->json('url'));
    }

    public function test_cookie_de_sesion_es_secure_detras_del_proxy_https(): void
    {
        // Sin SESSION_SECURE_COOKIE (valor por defecto del código): Secure se decide por el esquema de la petición.
        config(['trustedproxy.proxies' => '*', 'session.secure' => null]);

        $cookie = collect($this->get('http://localhost/login', ['X-Forwarded-Proto' => 'https'])->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', strtolower((string) $cookie->getSameSite()));
    }

    public function test_cookie_de_sesion_no_es_secure_en_http_local(): void
    {
        config(['session.secure' => null]);
        $cookie = collect($this->get('http://localhost/login')->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertFalse($cookie->isSecure(), 'Laragon por HTTP debe seguir recibiendo la cookie de sesión');
    }

    // ── Correo ──

    public function test_recuperar_contrasena_con_fallo_de_correo_no_da_500(): void
    {
        Mail::extend('fallido', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('Conexión SMTP bloqueada (simulada).');
            }

            public function __toString(): string
            {
                return 'fallido://';
            }
        });
        config(['mail.mailers.fallido' => ['transport' => 'fallido'], 'mail.default' => 'fallido']);
        Mail::purge();
        Log::spy();

        $user = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => 'No pudimos enviar el correo de recuperación en este momento. Inténtalo de nuevo más tarde.']);

        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => str_contains($msg, 'recuperación')
            && $ctx === ['exception' => TransportException::class])->once();
    }

    public function test_correo_por_defecto_es_sincrono_sin_worker(): void
    {
        // Las notificaciones de verificación y recuperación no usan colas: no hace falta un worker en Render.
        $this->assertNotContains(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements(\Illuminate\Auth\Notifications\VerifyEmail::class));
        $this->assertNotContains(\Illuminate\Contracts\Queue\ShouldQueue::class, class_implements(\Illuminate\Auth\Notifications\ResetPassword::class));
        $this->assertArrayHasKey('timeout', config('mail.mailers.smtp'));
    }

    // ── Archivos de despliegue (comprobación estática; Docker no está disponible en este equipo) ──

    public function test_dockerfile_construye_dependencias_fuentes_tcpdf_y_vite_sin_operaciones_de_bd(): void
    {
        $docker = file_get_contents(base_path('Dockerfile'));

        $this->assertMatchesRegularExpression('/^FROM php:8\.[3-9]/m', $docker);
        $this->assertStringContainsString('pdo_mysql', $docker);
        $this->assertStringContainsString('composer install --no-dev', $docker);
        $this->assertStringContainsString('build_fonts.php', $docker);
        $this->assertStringContainsString('test -s vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json', $docker);
        $this->assertStringContainsString('npm ci', $docker);
        $this->assertStringContainsString('npm run build', $docker);
        $this->assertStringContainsString('test -s public/build/manifest.json', $docker);
        $this->assertStringContainsString('APACHE_DOCUMENT_ROOT=/var/www/html/public', $docker);
        $this->assertStringContainsString('Listen ${PORT}', $docker);

        foreach (['migrate', 'db:seed', 'db:wipe', 'key:generate', 'config:cache', '.env'] as $prohibido) {
            $this->assertDoesNotMatchRegularExpression('/^RUN .*' . preg_quote($prohibido, '/') . '/m', $docker, "El Dockerfile no debe ejecutar {$prohibido} en la construcción");
        }
    }

    public function test_arranque_no_migra_ni_genera_claves(): void
    {
        $inicio = file_get_contents(base_path('docker/start.sh'));
        $ordenes = preg_replace('/^\s*#.*$/m', '', $inicio); // solo órdenes, sin comentarios

        $this->assertStringStartsWith("#!/bin/sh\n", $inicio, 'Fin de línea LF y shell POSIX');
        $this->assertStringNotContainsString("\r", $inicio);
        foreach (['migrate', 'db:seed', 'db:wipe', 'key:generate', 'cache:clear', 'optimize:clear'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $ordenes);
        }
        $this->assertStringContainsString('php artisan config:cache', $inicio);
        $this->assertStringContainsString('exec apache2-foreground', $inicio);
    }

    public function test_dockerignore_y_gitignore_excluyen_secretos_y_artefactos(): void
    {
        $dockerignore = array_map('trim', file(base_path('.dockerignore')));
        foreach (['.env', '.env.*', 'vendor', 'node_modules', 'public/build', '*.sql', '*.log', 'tests', '.git', 'bootstrap/cache/*.php'] as $patron) {
            $this->assertContains($patron, $dockerignore, ".dockerignore debe excluir {$patron}");
        }

        $gitignore = array_map('trim', file(base_path('.gitignore')));
        foreach (['.env', '.env.*', '!.env.example', '/vendor', '/node_modules', '/public/build', '*.sql', '*.log'] as $patron) {
            $this->assertContains($patron, $gitignore, ".gitignore debe contener {$patron}");
        }
    }

    public function test_fuentes_tcpdf_disponibles_en_el_entorno_probado(): void
    {
        $this->assertFileExists(base_path('vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json'));
    }
}
