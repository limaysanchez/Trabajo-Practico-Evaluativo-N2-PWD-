<?php

namespace Database\Factories;

use App\Models\Destino;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Destino>
 */
class DestinoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->city(),
            'localidad_cercana' => fake()->city(),
            'departamento' => fake()->word(),
            'descripcion' => fake()->sentence(),
        ];
    }
}
