<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClasseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Terminale '.$this->faker->randomLetter(),
            'code' => strtoupper($this->faker->unique()->bothify('CLS-###')),
            'niveau' => 'Terminale',
            'effectif' => $this->faker->numberBetween(20, 45),
        ];
    }
}
