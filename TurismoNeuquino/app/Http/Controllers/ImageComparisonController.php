<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompareImageRequest;
use App\Http\Requests\RegisterImageRequest;
use App\Repositories\ImageJsonRepository;
use App\Services\ImageHashService;
use App\Services\TemporaryImageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Intervention\Image\Exceptions\DecoderException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Coordina el flujo HTTP: comparar una foto y luego registrarla o descartarla.
 *
 * El hash, la persistencia y los archivos temporales viven en sus propias clases.
 * La comparación pendiente se guarda en la sesión para que el navegador no pueda alterarla.
 */
class ImageComparisonController extends Controller
{
    private const CLAVE_SESION = 'comparacion';

    public function index(): View
    {
        return view('image-comparison.index');
    }

    public function compare(
        CompareImageRequest $request,
        ImageHashService $hashService,
        ImageJsonRepository $repositorio,
        TemporaryImageService $temporales,
    ): RedirectResponse {
        $temporales->eliminar($request->session()->pull(self::CLAVE_SESION.'.temporal'));
        $request->session()->forget(self::CLAVE_SESION);
        $temporales->purgarAntiguos();

        $temporal = $temporales->guardar($request->file('image'));

        try {
            $hashHex = $hashService->calcularHex($temporales->rutaAbsoluta($temporal));
        } catch (DecoderException) {
            $temporales->eliminar($temporal);

            return to_route('image.index')->withErrors(['image' => 'No se pudo leer la imagen. Probá con otro archivo.']);
        }

        $masCercana = $hashService->buscarMasCercana($hashHex, $repositorio->todasLasImagenes());

        $request->session()->put(self::CLAVE_SESION, [
            'temporal' => $temporal,
            'hash_hex' => $hashHex,
            'coincide' => $masCercana !== null && $hashService->esCoincidencia($masCercana['distancia']),
            'mas_cercana' => $masCercana === null ? null : [
                'destino_id' => $masCercana['imagen']['destino_id'],
                'titulo' => $masCercana['imagen']['titulo'] ?? '',
                'hash_hex' => $masCercana['imagen']['hash_hex'],
                'distancia' => $masCercana['distancia'],
                'similitud' => $hashService->similitud($masCercana['distancia']),
            ],
        ]);

        return to_route('image.result');
    }

    public function result(
        Request $request,
        ImageJsonRepository $repositorio,
        TemporaryImageService $temporales,
    ): View|RedirectResponse {
        $comparacion = $request->session()->get(self::CLAVE_SESION);

        if ($comparacion === null) {
            return to_route('image.index')->with('error', 'Primero subí una foto para comparar.');
        }

        $destinoCoincidente = $comparacion['coincide']
            ? $repositorio->buscarDestino($comparacion['mas_cercana']['destino_id'])
            : null;

        // El JSON pudo cambiar desde la comparación, por ejemplo si se editó a mano.
        if ($comparacion['coincide'] && $destinoCoincidente === null) {
            $temporales->eliminar($comparacion['temporal']);
            $request->session()->forget(self::CLAVE_SESION);

            return to_route('image.index')->with('error', 'El destino de la comparación ya no existe. Volvé a subir la foto.');
        }

        return view('image-comparison.result', [
            'comparacion' => $comparacion,
            'destinoCoincidente' => $destinoCoincidente,
            'destinos' => $repositorio->todosLosDestinos(),
            'umbral' => config('turismo.umbral_hamming'),
        ]);
    }

    /**
     * Muestra la foto temporal, que es privada. Solo sirve la indicada en la sesión.
     */
    public function temporaryImage(Request $request, TemporaryImageService $temporales): BinaryFileResponse
    {
        $temporal = $request->session()->get(self::CLAVE_SESION.'.temporal');

        abort_unless($temporal !== null && $temporales->existe($temporal), 404);

        return response()->file($temporales->rutaAbsoluta($temporal));
    }

    public function register(
        RegisterImageRequest $request,
        ImageJsonRepository $repositorio,
        TemporaryImageService $temporales,
    ): RedirectResponse {
        $comparacion = $request->session()->get(self::CLAVE_SESION);

        if ($comparacion === null || $comparacion['coincide']) {
            return to_route('image.index')->with('error', 'No hay ninguna foto pendiente de registro.');
        }

        if (! $temporales->existe($comparacion['temporal'])) {
            $request->session()->forget(self::CLAVE_SESION);

            return to_route('image.index')->with('error', 'La foto temporal ya no está disponible. Volvé a subirla.');
        }

        $datos = $request->validated();
        $archivo = $temporales->publicar($comparacion['temporal']);
        $imagen = $repositorio->armarImagen($archivo, $datos['titulo'], $comparacion['hash_hex']);

        try {
            if ($datos['modo'] === 'existente') {
                $repositorio->agregarImagen($datos['destino_id'], $imagen);
            } else {
                $repositorio->crearDestino($datos, $imagen);
            }
        } catch (Throwable $excepcion) {
            // Sin esto quedaría una foto pública que el JSON no conoce.
            $temporales->revertirPublicacion($archivo);

            throw $excepcion;
        }

        $temporales->eliminar($comparacion['temporal']);
        $request->session()->forget(self::CLAVE_SESION);

        return to_route('image.index')->with('exito', 'La foto se registró correctamente.');
    }

    public function discard(Request $request, TemporaryImageService $temporales): RedirectResponse
    {
        $temporales->eliminar($request->session()->get(self::CLAVE_SESION.'.temporal'));
        $request->session()->forget(self::CLAVE_SESION);

        return to_route('image.index');
    }
}
