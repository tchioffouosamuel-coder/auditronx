<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AccessPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bssid' => strtoupper($this->faker->unique()->macAddress()),
            'ssid' => 'ETABLISSEMENT-WIFI',
            'label' => 'Portail principal',
        ];
    }
}
