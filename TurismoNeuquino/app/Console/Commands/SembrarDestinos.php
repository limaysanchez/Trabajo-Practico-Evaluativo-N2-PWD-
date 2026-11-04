<?php

namespace App\Console\Commands;

use App\Repositories\ImageJsonRepository;
use App\Services\ImageHashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Genera la base JSON con hashes reales a partir de los datos versionados en git.
 *
 * Hace falta porque storage/ está ignorado por git y no llega a quien clona el proyecto.
 */
class SembrarDestinos extends Command
{
    protected $signature = 'turismo:sembrar {--force : Reemplaza los destinos que ya existen}';

    protected $description = 'Carga los destinos y las fotos de database/semillas en la base JSON';

    public function handle(ImageHashService $hashService, ImageJsonRepository $repositorio): int
    {
        if ($repositorio->todosLosDestinos() !== [] && ! $this->option('force')) {
            $this->warn('La base JSON ya tiene destinos. Usá --force para reemplazarlos.');

            return self::SUCCESS;
        }

        $rutaSemillas = config('turismo.ruta_semillas');
        $archivoSemillas = $rutaSemillas.'/destinos.json';

        if (! is_file($archivoSemillas)) {
            $this->error("No se encontró el archivo {$archivoSemillas}.");

            return self::FAILURE;
        }

        $semillas = json_decode(file_get_contents($archivoSemillas), true);

        if (! is_array($semillas['destinos'] ?? null)) {
            $this->error('El archivo de semillas debe contener una lista "destinos".');

            return self::FAILURE;
        }

        $faltantes = $this->fotosFaltantes($semillas['destinos'], $rutaSemillas);

        if ($faltantes !== []) {
            $this->error('Faltan fotos en la carpeta de semillas: '.implode(', ', $faltantes));

            return self::FAILURE;
        }

        $destinos = [];
        $cantidadFotos = 0;

        foreach ($semillas['destinos'] as $semilla) {
            $imagenes = [];

            foreach ($semilla['imagenes'] ?? [] as $imagen) {
                $origen = "{$rutaSemillas}/fotos/{$imagen['archivo']}";
                $archivo = config('turismo.carpeta_destinos').'/'.$imagen['archivo'];

                Storage::disk('public')->put($archivo, file_get_contents($origen));

                $imagenes[] = $repositorio->armarImagen($archivo, $imagen['titulo'], $hashService->calcularHex($origen));
                $cantidadFotos++;
            }

            $destinos[] = [
                'id' => $repositorio->generarId($semilla['nombre']),
                'nombre' => $semilla['nombre'],
                'localidad_cercana' => $semilla['localidad_cercana'] ?? null,
                'departamento' => $semilla['departamento'],
                'descripcion' => $semilla['descripcion'],
                'imagenes' => $imagenes,
            ];
        }

        $repositorio->reemplazarDestinos($destinos);

        $this->info('Se cargaron '.count($destinos)." destinos y {$cantidadFotos} fotos.");

        return self::SUCCESS;
    }

    /**
     * Revisa las fotos antes de empezar, para no dejar una carga a medias.
     *
     * @param  array<int, array<string, mixed>>  $destinos
     * @return array<int, string>
     */
    private function fotosFaltantes(array $destinos, string $rutaSemillas): array
    {
        $faltantes = [];

        foreach ($destinos as $destino) {
            foreach ($destino['imagenes'] ?? [] as $imagen) {
                if (! is_file("{$rutaSemillas}/fotos/{$imagen['archivo']}")) {
                    $faltantes[] = $imagen['archivo'];
                }
            }
        }

        return $faltantes;
    }
}
