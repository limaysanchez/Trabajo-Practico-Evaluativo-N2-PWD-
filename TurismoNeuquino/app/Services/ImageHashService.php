<?php

namespace App\Services;

use Illuminate\Container\Attributes\Config;
use InvalidArgumentException;
use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\ImageHash;

/**
 * Cálculo y comparación de hashes perceptuales.
 *
 * Aporta la librería jenssegers/imagehash:
 * - La reducción de la foto a 9x8 píxeles y la luminancia de cada píxel.
 * - El hash DifferenceHash de 64 bits, comparando cada píxel con el siguiente.
 * - La conversión con toHex() y fromBits(), y la distancia de Hamming con distance().
 *
 * Desarrolló el grupo:
 * - La reconstrucción de un hash desde hexadecimal, que la librería no ofrece.
 * - La normalización a 16 caracteres, porque toHex() recorta los ceros a la izquierda.
 * - La similitud proporcional, el umbral de coincidencia y la búsqueda de la foto más cercana.
 */
class ImageHashService
{
    private const BITS_DEL_HASH = 64;

    private const LARGO_HEX = 16;

    public function __construct(
        private ImageHash $imageHash,
        #[Config('turismo.umbral_hamming')] private int $umbralHamming,
    ) {}

    /**
     * Calcula el hash de una foto y lo devuelve en hexadecimal de 16 caracteres.
     */
    public function calcularHex(string $rutaImagen): string
    {
        $hex = $this->imageHash->hash($rutaImagen)->toHex();

        return str_pad($hex, self::LARGO_HEX, '0', STR_PAD_LEFT);
    }

    /**
     * Reconstruye un hash de la librería a partir de su valor hexadecimal.
     *
     * Reemplaza a Hash::fromHex(), que no existe en la versión 0.11 de la librería.
     *
     * @throws InvalidArgumentException si el texto no es un hash hexadecimal válido.
     */
    public function hexAHash(string $hex): Hash
    {
        if (! preg_match('/^[0-9a-fA-F]{1,16}$/', $hex)) {
            throw new InvalidArgumentException("El hash \"{$hex}\" no es un hexadecimal válido de hasta 16 caracteres.");
        }

        $bits = '';

        foreach (str_split(str_pad($hex, self::LARGO_HEX, '0', STR_PAD_LEFT)) as $digito) {
            $bits .= str_pad(base_convert($digito, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        return Hash::fromBits($bits);
    }

    /**
     * Devuelve cuántos bits difieren entre dos hashes (distancia de Hamming).
     */
    public function distancia(string $hexA, string $hexB): int
    {
        return $this->hexAHash($hexA)->distance($this->hexAHash($hexB));
    }

    /**
     * Convierte una distancia en un porcentaje de similitud sobre 64 bits.
     */
    public function similitud(int $distancia): float
    {
        return round((self::BITS_DEL_HASH - $distancia) / self::BITS_DEL_HASH * 100, 2);
    }

    /**
     * Indica si una distancia alcanza para considerar que las fotos coinciden.
     */
    public function esCoincidencia(int $distancia): bool
    {
        return $distancia <= $this->umbralHamming;
    }

    /**
     * Busca la foto más parecida a un hash, sin evaluar todavía si coincide.
     *
     * @param  array<int, array<string, mixed>>  $imagenes
     * @return array{imagen: array<string, mixed>, distancia: int}|null
     */
    public function buscarMasCercana(string $hex, array $imagenes): ?array
    {
        $masCercana = null;

        foreach ($imagenes as $imagen) {
            $distancia = $this->distancia($hex, $imagen['hash_hex']);

            if ($masCercana === null || $distancia < $masCercana['distancia']) {
                $masCercana = ['imagen' => $imagen, 'distancia' => $distancia];
            }
        }

        return $masCercana;
    }
}
