<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tarea 1.3: actualización de perfil sobre la tabla real `usuarios`.
 */
class ProfileUpdateSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_profile(): void
    {
        $this->patch('/profile', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
        ])->assertRedirect('/login');
    }

    public function test_name_is_mapped_to_nombre_column(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Nuevo',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Nombre Nuevo', $user->refresh()->nombre);
    }

    public function test_email_already_used_by_another_user_is_rejected(): void
    {
        $other = User::factory()->create(['email' => 'ocupado@example.com']);
        $user = User::factory()->create();
        $originalEmail = $user->email;

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Otro Nombre',
                'email' => $other->email,
            ])
            ->assertSessionHasErrors('email')
            ->assertRedirect('/profile');

        $this->assertSame($originalEmail, $user->refresh()->email);
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', ['name' => '', 'email' => 'no-es-email'])
            ->assertSessionHasErrors(['name', 'email']);
    }

    public function test_sensitive_fields_cannot_be_changed_through_profile_update(): void
    {
        $user = User::factory()->create(['estado' => 1]);
        $cedula = $user->cedula;
        $hash = $user->contrasena;

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nombre Seguro',
                'email' => 'seguro@example.com',
                'cedula' => 1,
                'estado' => 0,
                'contrasena' => 'OtraClave#2026XYZ',
                'id_rol' => 99,
                'rol_id' => 99,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNull(User::find(1));
        $fresh = User::find($cedula);
        $this->assertNotNull($fresh);
        $this->assertSame(1, (int) $fresh->estado);
        $this->assertSame($hash, $fresh->contrasena);
        $this->assertTrue(Hash::check('password', $fresh->contrasena));
        $this->assertSame(1, (int) $fresh->id_rol);
        $this->assertSame('Nombre Seguro', $fresh->nombre);
        $this->assertSame('seguro@example.com', $fresh->email);
    }
}
