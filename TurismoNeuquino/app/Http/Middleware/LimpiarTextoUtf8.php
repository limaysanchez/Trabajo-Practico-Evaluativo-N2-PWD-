<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Reemplaza los bytes que no son UTF-8 en los textos recibidos.
 *
 * La sesión de Laravel y nuestro archivo de destinos se guardan en JSON, que no los admite.
 * Sin esto, un texto mal codificado borraría la comparación pendiente del usuario.
 */
class LimpiarTextoUtf8 extends TransformsRequest
{
    /**
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function transform($key, $value): mixed
    {
        return is_string($value) ? mb_scrub($value, 'UTF-8') : $value;
    }
}
