<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el registro de una foto en un destino nuevo.
 *
 * La foto y su hash no se reciben del formulario: se toman de la sesión.
 */
class RegisterImageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120', 'unique:destinos,nombre'],
            'departamento' => ['required', 'string', 'max:80'],
            'descripcion' => ['required', 'string', 'max:1000'],
            'titulo' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribí el nombre del lugar.',
            'nombre.max' => 'El nombre no puede superar los 120 caracteres.',
            'nombre.unique' => 'Ya existe un destino con ese nombre.',
            'departamento.required' => 'Escribí el departamento.',
            'departamento.max' => 'El departamento no puede superar los 80 caracteres.',
            'descripcion.required' => 'Escribí una descripción.',
            'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
            'titulo.required' => 'Escribí un título para la imagen.',
            'titulo.max' => 'El título no puede superar los 120 caracteres.',
        ];
    }
}
