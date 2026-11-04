<?php

namespace App\Services;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Manejo de las fotos subidas que todavía no se registraron (desarrollo del grupo).
 *
 * Quedan en el disco privado "local" hasta que el usuario las registra o las descarta.
 */
class TemporaryImageService
{
    public function __construct(
        #[Config('turismo.carpeta_temporales')] private string $carpetaTemporales,
        #[Config('turismo.carpeta_destinos')] private string $carpetaDestinos,
        #[Config('turismo.horas_vida_temporales')] private int $horasDeVida,
    ) {}

    /**
     * Guarda la foto con un nombre aleatorio y devuelve su ruta relativa.
     *
     * No se usa el nombre original porque lo elige el cliente y puede ser inseguro.
     */
    public function guardar(UploadedFile $imagen): string
    {
        return $imagen->store($this->carpetaTemporales, 'local');
    }

    public function rutaAbsoluta(string $temporal): string
    {
        return Storage::disk('local')->path($temporal);
    }

    public function existe(string $temporal): bool
    {
        return Storage::disk('local')->exists($temporal);
    }

    public function eliminar(?string $temporal): void
    {
        if ($temporal !== null) {
            Storage::disk('local')->delete($temporal);
        }
    }

    /**
     * Borra las fotos abandonadas, por ejemplo si el usuario cerró la pestaña.
     */
    public function purgarAntiguos(): void
    {
        $limite = now()->subHours($this->horasDeVida)->getTimestamp();

        foreach (Storage::disk('local')->files($this->carpetaTemporales) as $archivo) {
            if (Storage::disk('local')->lastModified($archivo) < $limite) {
                Storage::disk('local')->delete($archivo);
            }
        }
    }

    /**
     * Copia la foto a la carpeta pública y devuelve su ruta relativa.
     *
     * Copia en lugar de mover para conservar la original si después falla el JSON.
     */
    public function publicar(string $temporal): string
    {
        $archivo = $this->carpetaDestinos.'/'.basename($temporal);

        Storage::disk('public')->put($archivo, Storage::disk('local')->get($temporal));

        return $archivo;
    }

    public function revertirPublicacion(string $archivo): void
    {
        Storage::disk('public')->delete($archivo);
    }
}
