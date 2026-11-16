<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Fábrica adaptada a la tabla real `usuarios`
 * (PK `cedula`, contraseña en `contrasena`, sin `remember_token`).
 * Los usuarios se crean verificados por defecto; usar unverified() para lo contrario.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cedula'     => fake()->unique()->numberBetween(1000000, 99999999),
            'nombre'     => fake()->name(),
            'email'      => fake()->unique()->safeEmail(),
            'telefono'   => fake()->numerify('7#######'),
            'ciudad'     => fake()->city(),
            'fechanac'   => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'estado'     => 1,
            'email_verified_at' => now(),
            'contrasena' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
