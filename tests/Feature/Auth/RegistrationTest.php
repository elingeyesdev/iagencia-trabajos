<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // El registro asigna un rol de Spatie; debe existir (RolesSeeder en producción).
        Role::firstOrCreate(['name' => 'trabajador', 'guard_name' => 'web']);

        $response = $this->post('/register', [
            'cedula' => 1234567,
            'nombre' => 'Test User',
            'email' => 'test@example.com',
            'fechanac' => '1990-01-01',
            'password' => 'Clave-Segura-2026!',
            'password_confirmation' => 'Clave-Segura-2026!',
            'rol' => 'trabajador',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::find(1234567);
        $this->assertNotNull($user);
        $this->assertSame('Test User', $user->nombre);
        $this->assertTrue($user->hasRole('trabajador'));
    }
}
