<?php

namespace App\Http\Requests;

use App\Repositories\ImageJsonRepository;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el registro de una foto en un destino existente o en uno nuevo.
 *
 * La foto y su hash no se reciben del formulario: se toman de la sesión.
 */
class RegisterImageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ImageJsonRepository $repositorio): array
    {
        $idsExistentes = array_column($repositorio->todosLosDestinos(), 'id');

        return [
            'modo' => ['required', Rule::in(['existente', 'nuevo'])],
            'titulo' => ['required', 'string', 'max:120'],
            'destino_id' => ['exclude_unless:modo,existente', 'required', 'string', Rule::in($idsExistentes)],
            'nombre' => [
                'exclude_unless:modo,nuevo',
                'required',
                'string',
                'max:120',
                function (string $atributo, mixed $valor, Closure $fallar) use ($repositorio, $idsExistentes): void {
                    $id = $repositorio->generarId((string) $valor);

                    if ($id === '') {
                        $fallar('El nombre del lugar debe contener letras o números.');
                    } elseif (in_array($id, $idsExistentes, true)) {
                        $fallar('Ya existe un destino con ese nombre. Elegilo en la opción de destino existente.');
                    }
                },
            ],
            'departamento' => ['exclude_unless:modo,nuevo', 'required', 'string', 'max:80'],
            'descripcion' => ['exclude_unless:modo,nuevo', 'required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modo.required' => 'Elegí cómo querés registrar la foto.',
            'modo.in' => 'La opción de registro no es válida.',
            'titulo.required' => 'Escribí un título para la imagen.',
            'titulo.max' => 'El título no puede superar los 120 caracteres.',
            'destino_id.required' => 'Elegí un destino.',
            'destino_id.in' => 'El destino elegido no existe.',
            'nombre.required' => 'Escribí el nombre del lugar.',
            'nombre.max' => 'El nombre no puede superar los 120 caracteres.',
            'departamento.required' => 'Escribí el departamento.',
            'departamento.max' => 'El departamento no puede superar los 80 caracteres.',
            'descripcion.required' => 'Escribí una descripción.',
            'descripcion.max' => 'La descripción no puede superar los 1000 caracteres.',
        ];
    }
}
