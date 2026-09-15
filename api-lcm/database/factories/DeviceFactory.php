<?php

namespace Database\Factories;

use App\Models\Enseignant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_id' => Enseignant::factory(),
            'device_uuid' => $this->faker->unique()->uuid(),
            'device_type' => 'mobile',
            'activated_at' => now(),
        ];
    }
}
