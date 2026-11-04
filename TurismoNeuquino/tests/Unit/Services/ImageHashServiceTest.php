<?php

namespace Tests\Unit\Services;

use App\Services\ImageHashService;
use InvalidArgumentException;
use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\ImageHash;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageHashServiceTest extends TestCase
{
    private function crearServicio(?Hash $hashCalculado = null): ImageHashService
    {
        $imageHash = $this->createStub(ImageHash::class);

        if ($hashCalculado !== null) {
            $imageHash->method('hash')->willReturn($hashCalculado);
        }

        return new ImageHashService($imageHash, 5);
    }

    public function test_calculated_hex_keeps_leading_zeros_at_16_characters(): void
    {
        $bits = str_repeat('0', 8).str_repeat('1', 56);

        $hex = $this->crearServicio(Hash::fromBits($bits))->calcularHex('foto.jpg');

        $this->assertSame('00ffffffffffffff', $hex);
    }

    public function test_all_zero_hash_produces_16_zero_characters(): void
    {
        $hex = $this->crearServicio(Hash::fromBits(str_repeat('0', 64)))->calcularHex('foto.jpg');

        $this->assertSame('0000000000000000', $hex);
    }

    public function test_hex_rebuilds_the_original_64_bits(): void
    {
        $bits = str_repeat('0', 8).str_repeat('10', 28);
        $hex = $this->crearServicio(Hash::fromBits($bits))->calcularHex('foto.jpg');

        $this->assertSame($bits, $this->crearServicio()->hexAHash($hex)->toBits());
    }

    public function test_short_hex_is_treated_as_having_leading_zeros(): void
    {
        $servicio = $this->crearServicio();

        $this->assertSame(0, $servicio->distancia('ffffffffffffff', '00ffffffffffffff'));
    }

    public function test_distance_counts_the_different_bits(): void
    {
        $this->assertSame(3, $this->crearServicio()->distancia('ff00f0f0f0f0ff00', 'ff01f0f0f1f0ff10'));
    }

    public function test_opposite_hashes_have_distance_64(): void
    {
        $this->assertSame(64, $this->crearServicio()->distancia('ffffffffffffffff', '0000000000000000'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hexInvalidos(): array
    {
        return [
            'vacío' => [''],
            'caracter no hexadecimal' => ['zz00f0f0f0f0ff00'],
            'más de 16 caracteres' => ['ff00f0f0f0f0ff001'],
        ];
    }

    #[DataProvider('hexInvalidos')]
    public function test_rejects_invalid_hex(string $hex): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->crearServicio()->hexAHash($hex);
    }

    /**
     * @return array<string, array{0: int, 1: float}>
     */
    public static function similitudes(): array
    {
        return [
            'idénticas' => [0, 100.0],
            'en el umbral' => [5, 92.19],
            'mitad de los bits' => [32, 50.0],
            'la fórmula anterior daba cero' => [50, 21.88],
            'opuestas' => [64, 0.0],
        ];
    }

    #[DataProvider('similitudes')]
    public function test_similarity_is_proportional_to_64_bits(int $distancia, float $esperada): void
    {
        $this->assertSame($esperada, $this->crearServicio()->similitud($distancia));
    }

    public function test_threshold_accepts_5_bits_and_rejects_6(): void
    {
        $servicio = $this->crearServicio();

        $this->assertTrue($servicio->esCoincidencia(5));
        $this->assertFalse($servicio->esCoincidencia(6));
    }

    public function test_closest_image_is_the_one_with_the_lowest_distance(): void
    {
        $lejana = ['titulo' => 'Lejana', 'hash_hex' => '0000000000000000'];
        $cercana = ['titulo' => 'Cercana', 'hash_hex' => 'fffffffffffffff0'];

        $resultado = $this->crearServicio()->buscarMasCercana('ffffffffffffffff', [$lejana, $cercana]);

        $this->assertSame(['imagen' => $cercana, 'distancia' => 4], $resultado);
    }

    public function test_closest_image_is_null_without_registered_images(): void
    {
        $this->assertNull($this->crearServicio()->buscarMasCercana('ffffffffffffffff', []));
    }
}
