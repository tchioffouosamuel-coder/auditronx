<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DisciplineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => $this->faker->randomElement(['Mathématiques', 'Physique', 'Français', 'Histoire-Géo']),
            'code' => strtoupper($this->faker->unique()->bothify('DISC-###')),
            'coefficient' => $this->faker->numberBetween(1, 6),
        ];
    }
}
