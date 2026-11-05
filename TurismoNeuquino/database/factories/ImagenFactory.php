<?php

namespace Database\Factories;

use App\Models\Destino;
use App\Models\Imagen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imagen>
 */
class ImagenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destino_id' => Destino::factory(),
            'titulo' => fake()->sentence(3),
            'hash_hex' => fake()->regexify('[0-9a-f]{16}'),
            'mime_type' => 'image/png',
            'contenido' => random_bytes(32),
        ];
    }
}
