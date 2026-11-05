<?php

namespace App\Models;

use Database\Factories\ImagenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\ImageHash\Hash;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;

/**
 * Foto de un destino, guardada en la base de datos junto con su hash perceptual.
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
#[Table('imagenes')]
#[Fillable(['titulo', 'hash_hex', 'mime_type', 'contenido'])]
class Imagen extends Model
{
    /** @use HasFactory<ImagenFactory> */
    use HasFactory;

    private const BITS_DEL_HASH = 64;

    private const LARGO_HEX = 16;

    /**
     * @return BelongsTo<Destino, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Destino::class);
    }

    /**
     * Calcula el hash de una foto y lo devuelve en hexadecimal de 16 caracteres.
     */
    public static function calcularHash(string $contenido): string
    {
        $hex = (new ImageHash(new DifferenceHash))->hash($contenido)->toHex();

        return str_pad($hex, self::LARGO_HEX, '0', STR_PAD_LEFT);
    }

    /**
     * Busca la foto más parecida a un hash, sin evaluar todavía si coincide.
     *
     * Trae solo las columnas necesarias para no cargar en memoria el contenido de cada foto.
     *
     * @return array{imagen: self, distancia: int}|null
     */
    public static function buscarMasCercana(string $hashHex): ?array
    {
        $masCercana = null;

        foreach (self::all(['destino_id', 'hash_hex']) as $imagen) {
            $distancia = self::distancia($hashHex, $imagen->hash_hex);

            if ($masCercana === null || $distancia < $masCercana['distancia']) {
                $masCercana = ['imagen' => $imagen, 'distancia' => $distancia];
            }
        }

        return $masCercana;
    }

    /**
     * Devuelve cuántos bits difieren entre dos hashes (distancia de Hamming).
     */
    public static function distancia(string $hexA, string $hexB): int
    {
        return self::hexAHash($hexA)->distance(self::hexAHash($hexB));
    }

    /**
     * Convierte una distancia en un porcentaje de similitud sobre 64 bits.
     */
    public static function similitud(int $distancia): float
    {
        return round((self::BITS_DEL_HASH - $distancia) / self::BITS_DEL_HASH * 100, 2);
    }

    /**
     * Indica si una distancia alcanza para considerar que las fotos coinciden.
     */
    public static function esCoincidencia(int $distancia): bool
    {
        return $distancia <= config('turismo.umbral_hamming');
    }

    /**
     * Reconstruye un hash de la librería a partir de su valor hexadecimal.
     *
     * Reemplaza a Hash::fromHex(), que no existe en la versión 0.11 de la librería.
     */
    private static function hexAHash(string $hex): Hash
    {
        $bits = '';

        foreach (str_split($hex) as $digito) {
            $bits .= str_pad(base_convert($digito, 16, 2), 4, '0', STR_PAD_LEFT);
        }

        return Hash::fromBits($bits);
    }
}
