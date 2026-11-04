<?php

namespace Tests;

/**
 * Genera imágenes con un hash conocido, sin depender de fotos reales.
 */
trait GeneraImagenes
{
    protected const HASH_DESCENDENTE = 'ffffffffffffffff';

    protected const HASH_ASCENDENTE = '0000000000000000';

    /**
     * Devuelve un PNG con un degradado horizontal.
     *
     * Si cada píxel es más claro que el siguiente, todos los bits del hash valen 1.
     */
    protected function pngDegradado(bool $descendente = true): string
    {
        $imagen = imagecreatetruecolor(180, 160);

        for ($x = 0; $x < 180; $x++) {
            $gris = $descendente ? 255 - $x : 75 + $x;
            imagefilledrectangle($imagen, $x, 0, $x, 159, imagecolorallocate($imagen, $gris, $gris, $gris));
        }

        ob_start();
        imagepng($imagen);

        return ob_get_clean();
    }
}
