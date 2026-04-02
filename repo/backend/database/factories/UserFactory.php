<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
/** @extends Factory<User> */
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
            'identifier' => fake()->unique()->userName(),
            'password_hash' => static::$password ??= Hash::make('Password@Test123'),
            'role' => fake()->randomElement(['staff', 'reviewer', 'administrator']),
            'site_id' => fake()->numberBetween(1, 3),
            'department_id' => fake()->numberBetween(1, 5),
            'is_banned' => false,
            'muted_until' => null,
            'locked_until' => null,
            'failed_attempts' => 0,
        ];
    }
}
