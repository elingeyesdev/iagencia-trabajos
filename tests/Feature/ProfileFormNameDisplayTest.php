<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tarea 1.3.1 (QA): el formulario de perfil muestra el nombre real (`usuarios.nombre`)
 * en el input `name="name"`, respeta old('name') tras un error de validación y
 * refleja el nombre actualizado.
 */
class ProfileFormNameDisplayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Devuelve la etiqueta <input> completa cuyo atributo name es "name".
     */
    private function nameInputTag(string $html): string
    {
        $found = preg_match('/<input\b[^>]*\bname="name"[^>]*>/i', $html, $m);
        $this->assertSame(1, $found, 'No se encontró el input con name="name" en el formulario de perfil.');

        return $m[0];
    }

    /**
     * Devuelve el valor (decodificado) del atributo value del input name="name".
     */
    private function nameInputValue(string $html): ?string
    {
        $tag = $this->nameInputTag($html);

        if (! preg_match('/\bvalue="([^"]*)"/i', $tag, $m)) {
            return null;
        }

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function test_profile_form_shows_stored_nombre_in_name_input(): void
    {
        $user = User::factory()->create([
            'nombre' => 'Zoé O\'Brien & Cía Distintivo',
            'email' => 'zoe.display@example.com',
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();

        $this->assertSame('Zoé O\'Brien & Cía Distintivo', $this->nameInputValue($response->getContent()));
        // Comprobación adicional con el escape HTML aplicado por Blade.
        $response->assertSee('value="'.e('Zoé O\'Brien & Cía Distintivo').'"', false);
    }

    public function test_name_input_keeps_name_and_id_attributes(): void
    {
        $user = User::factory()->create(['nombre' => 'Atributos Conservados']);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();

        $tag = $this->nameInputTag($response->getContent());
        $this->assertMatchesRegularExpression('/\bid="name"/', $tag);
        $this->assertMatchesRegularExpression('/\bname="name"/', $tag);
        $this->assertStringNotContainsString('name="nombre"', $response->getContent());
    }

    public function test_after_validation_error_name_input_shows_old_value_not_stored(): void
    {
        $user = User::factory()->create([
            'nombre' => 'Nombre Almacenado Original',
            'email' => 'old.value@example.com',
        ]);

        $response = $this->actingAs($user)
            ->from('/profile')
            ->followingRedirects()
            ->patch('/profile', [
                'name' => 'Nombre Enviado Con Error',
                'email' => 'correo-no-valido',
            ]);

        $response->assertOk();

        $html = $response->getContent();
        $this->assertSame('Nombre Enviado Con Error', $this->nameInputValue($html));
        $this->assertStringNotContainsString('Nombre Almacenado Original', $this->nameInputTag($html));

        // El dato real no cambió.
        $fresh = User::find($user->cedula);
        $this->assertSame('Nombre Almacenado Original', $fresh->nombre);
        $this->assertSame('old.value@example.com', $fresh->email);
    }

    public function test_updated_nombre_is_persisted_and_shown_on_next_visit(): void
    {
        $user = User::factory()->create([
            'nombre' => 'Nombre Anterior Unico',
            'email' => 'update.display@example.com',
        ]);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Nombre Nuevo Unico',
                'email' => 'update.display@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $fresh = User::find($user->cedula);
        $this->assertSame('Nombre Nuevo Unico', $fresh->nombre);

        $response = $this->actingAs($fresh)->get('/profile');

        $response->assertOk();
        $this->assertSame('Nombre Nuevo Unico', $this->nameInputValue($response->getContent()));
        $this->assertStringNotContainsString('Nombre Anterior Unico', $response->getContent());
    }
}
