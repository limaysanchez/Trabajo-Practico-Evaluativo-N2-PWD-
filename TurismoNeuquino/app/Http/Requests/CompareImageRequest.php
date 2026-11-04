<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompareImageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Seleccioná una foto para comparar.',
            'image.uploaded' => 'La foto no se pudo subir. Probablemente supere el tamaño que permite el servidor.',
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La foto debe estar en formato JPG, PNG o WEBP.',
            'image.max' => 'La foto no puede superar los 5 MB.',
        ];
    }
}
