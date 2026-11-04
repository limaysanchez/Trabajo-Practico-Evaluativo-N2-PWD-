<?php

namespace App\Repositories;

use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

/**
 * Lectura y escritura de la base de datos JSON (desarrollo del grupo).
 *
 * Bloquea el archivo en cada operación para que dos registros simultáneos no se pisen.
 * Si el JSON está dañado lanza un error y nunca lo sobrescribe.
 */
class ImageJsonRepository
{
    private const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';

    public function __construct(
        #[Config('turismo.archivo_json')] private string $archivoJson,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function todosLosDestinos(): array
    {
        return $this->leer();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buscarDestino(string $idDestino): ?array
    {
        foreach ($this->leer() as $destino) {
            if ($destino['id'] === $idDestino) {
                return $destino;
            }
        }

        return null;
    }

    /**
     * Devuelve las fotos de todos los destinos, cada una con el id de su destino.
     *
     * @return array<int, array<string, mixed>>
     */
    public function todasLasImagenes(): array
    {
        $imagenes = [];

        foreach ($this->leer() as $destino) {
            foreach ($destino['imagenes'] as $imagen) {
                $imagenes[] = [...$imagen, 'destino_id' => $destino['id']];
            }
        }

        return $imagenes;
    }

    /**
     * Genera el identificador de un destino a partir de su nombre.
     */
    public function generarId(string $nombre): string
    {
        return Str::slug($nombre);
    }

    /**
     * Arma el registro de una foto con el formato que usa el JSON.
     *
     * @return array{archivo: string, titulo: string, hash_hex: string, registrada_en: string}
     */
    public function armarImagen(string $archivo, string $titulo, string $hashHex): array
    {
        return [
            'archivo' => $archivo,
            'titulo' => $titulo,
            'hash_hex' => $hashHex,
            'registrada_en' => now(self::ZONA_HORARIA)->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, string>  $imagen
     *
     * @throws RuntimeException si el destino no existe.
     */
    public function agregarImagen(string $idDestino, array $imagen): void
    {
        $this->modificar(function (array $destinos) use ($idDestino, $imagen): array {
            foreach ($destinos as $indice => $destino) {
                if ($destino['id'] === $idDestino) {
                    $destinos[$indice]['imagenes'][] = $imagen;

                    return $destinos;
                }
            }

            throw new RuntimeException("El destino \"{$idDestino}\" no existe.");
        });
    }

    /**
     * @param  array<string, string>  $datos
     * @param  array<string, string>  $imagen
     *
     * @throws RuntimeException si ya existe un destino con ese nombre.
     */
    public function crearDestino(array $datos, array $imagen): string
    {
        $idDestino = $this->generarId($datos['nombre']);

        $this->modificar(function (array $destinos) use ($idDestino, $datos, $imagen): array {
            if (in_array($idDestino, array_column($destinos, 'id'), true)) {
                throw new RuntimeException("El destino \"{$idDestino}\" ya existe.");
            }

            $destinos[] = [
                'id' => $idDestino,
                'nombre' => $datos['nombre'],
                'departamento' => $datos['departamento'],
                'descripcion' => $datos['descripcion'],
                'imagenes' => [$imagen],
            ];

            return $destinos;
        });

        return $idDestino;
    }

    /**
     * Reemplaza todos los destinos. Lo usa el comando turismo:sembrar.
     *
     * @param  array<int, array<string, mixed>>  $destinos
     */
    public function reemplazarDestinos(array $destinos): void
    {
        $this->modificar(fn (): array => $destinos);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function leer(): array
    {
        $ruta = $this->rutaAbsoluta();

        if (! is_file($ruta)) {
            return [];
        }

        $archivo = fopen($ruta, 'r');
        flock($archivo, LOCK_SH);

        try {
            return $this->decodificar(stream_get_contents($archivo));
        } finally {
            flock($archivo, LOCK_UN);
            fclose($archivo);
        }
    }

    /**
     * Lee, modifica y escribe el JSON manteniendo el bloqueo durante todo el proceso.
     *
     * @param  Closure(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $cambio
     */
    private function modificar(Closure $cambio): void
    {
        $ruta = $this->rutaAbsoluta();

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0755, true);
        }

        $archivo = fopen($ruta, 'c+');
        flock($archivo, LOCK_EX);

        try {
            $destinos = $this->decodificar(stream_get_contents($archivo));

            $json = json_encode(
                ['destinos' => $cambio($destinos)],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );

            rewind($archivo);
            ftruncate($archivo, 0);
            fwrite($archivo, $json);
            fflush($archivo);
        } finally {
            flock($archivo, LOCK_UN);
            fclose($archivo);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws RuntimeException si el contenido no tiene el formato esperado.
     */
    private function decodificar(string $contenido): array
    {
        // Algunos editores de Windows agregan una marca BOM que json_decode no acepta.
        $contenido = trim(preg_replace('/^\xEF\xBB\xBF/', '', $contenido));

        if ($contenido === '') {
            return [];
        }

        try {
            $datos = json_decode($contenido, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $excepcion) {
            throw new RuntimeException('El archivo JSON de destinos está dañado: '.$excepcion->getMessage(), previous: $excepcion);
        }

        if (! is_array($datos) || ! is_array($datos['destinos'] ?? null)) {
            throw new RuntimeException('El archivo JSON de destinos debe contener una lista "destinos".');
        }

        foreach ($datos['destinos'] as $destino) {
            if (! isset($destino['id'], $destino['nombre']) || ! is_array($destino['imagenes'] ?? null)) {
                throw new RuntimeException('Cada destino del JSON debe tener "id", "nombre" e "imagenes".');
            }

            foreach ($destino['imagenes'] as $imagen) {
                if (! isset($imagen['archivo'], $imagen['hash_hex'])) {
                    throw new RuntimeException("Cada imagen de \"{$destino['nombre']}\" debe tener \"archivo\" y \"hash_hex\".");
                }
            }
        }

        return $datos['destinos'];
    }

    private function rutaAbsoluta(): string
    {
        return Storage::disk('local')->path($this->archivoJson);
    }
}
