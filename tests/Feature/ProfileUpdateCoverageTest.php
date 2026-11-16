<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Tarea 1.3 (QA): cobertura complementaria de PATCH /profile y GET /profile.
 */
class ProfileUpdateCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_keep_current_email_without_uniqueness_error(): void
    {
        $user = User::factory()->create(['email' => 'actual@example.com']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Nombre Cambiado',
                'email' => 'actual@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $fresh = User::find($user->cedula);
        $this->assertSame('actual@example.com', $fresh->email);
        $this->assertSame('Nombre Cambiado', $fresh->nombre);
    }

    public function test_user_cannot_update_another_users_profile(): void
    {
        $victim = User::factory()->create([
            'nombre' => 'Victima Original',
            'email' => 'victima@example.com',
        ]);
        $attacker = User::factory()->create([
            'nombre' => 'Atacante Original',
            'email' => 'atacante@example.com',
        ]);

        $this->actingAs($attacker)
            ->patch('/profile', [
                'cedula' => $victim->cedula,
                'name' => 'Nombre Inyectado',
                'email' => 'inyectado@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $victimFresh = User::find($victim->cedula);
        $this->assertSame('Victima Original', $victimFresh->nombre);
        $this->assertSame('victima@example.com', $victimFresh->email);

        $attackerFresh = User::find($attacker->cedula);
        $this->assertSame('Nombre Inyectado', $attackerFresh->nombre);
        $this->assertSame('inyectado@example.com', $attackerFresh->email);

        $this->assertSame(2, User::count());
    }

    public function test_spatie_roles_cannot_be_changed_through_profile_update(): void
    {
        Role::firstOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('trabajador');

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Sin Escalada',
                'email' => $user->email,
                'role' => 'administrador',
                'roles' => ['administrador'],
            ])
            ->assertSessionHasNoErrors();

        $fresh = User::find($user->cedula);
        $this->assertTrue($fresh->hasRole('trabajador'));
        $this->assertFalse($fresh->hasRole('administrador'));
        $this->assertSame(['trabajador'], $fresh->getRoleNames()->all());
    }

    public function test_profile_page_responds_and_shows_current_email(): void
    {
        $user = User::factory()->create(['email' => 'visible@example.com']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('visible@example.com');
    }
}
