<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class QrPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->uuid(),
            'label' => 'Portail principal',
        ];
    }
}
