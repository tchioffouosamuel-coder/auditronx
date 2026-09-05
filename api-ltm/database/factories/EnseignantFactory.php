<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EnseignantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => $this->faker->name(),
            'matricule' => $this->faker->unique()->numerify('MAT-####'),
            'email' => $this->faker->unique()->safeEmail(),
            'fonction' => 'Enseignant',
            'section' => $this->faker->randomElement(['Sciences', 'Lettres', 'Techniques']),
            'grade' => 'Certifié',
            'tel' => $this->faker->unique()->numerify('6########'),
            'password' => null,
            'est_admin' => false,
        ];
    }
}
