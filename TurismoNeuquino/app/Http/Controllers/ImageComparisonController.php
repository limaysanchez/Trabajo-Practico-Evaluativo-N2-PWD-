<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompareImageRequest;
use App\Http\Requests\RegisterImageRequest;
use App\Models\Destino;
use App\Models\Imagen;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Exceptions\DecoderException;

/**
 * Coordina el flujo HTTP: comparar una foto y luego registrarla o descartarla.
 *
 * La comparación pendiente, con su foto, se guarda en la sesión para que el navegador no pueda alterarla.
 * La foto recién llega a la base de datos cuando el usuario la registra.
 */
class ImageComparisonController extends Controller
{
    private const CLAVE_SESION = 'comparacion';

    public function index(): View
    {
        return view('image-comparison.index');
    }

    public function compare(CompareImageRequest $request): RedirectResponse
    {
        $request->session()->forget(self::CLAVE_SESION);

        $foto = $request->file('image');
        $contenido = $foto->get();

        try {
            $hashHex = Imagen::calcularHash($contenido);
        } catch (DecoderException) {
            return to_route('image.index')->withErrors(['image' => 'No se pudo leer la imagen. Probá con otro archivo.']);
        }

        $masCercana = Imagen::buscarMasCercana($hashHex);

        $request->session()->put(self::CLAVE_SESION, [
            'foto_base64' => base64_encode($contenido),
            'mime_type' => $foto->getMimeType(),
            'hash_hex' => $hashHex,
            'coincide' => $masCercana !== null && Imagen::esCoincidencia($masCercana['distancia']),
            'mas_cercana' => $masCercana === null ? null : [
                'destino_id' => $masCercana['imagen']->destino_id,
                'hash_hex' => $masCercana['imagen']->hash_hex,
                'distancia' => $masCercana['distancia'],
                'similitud' => Imagen::similitud($masCercana['distancia']),
            ],
        ]);

        return to_route('image.result');
    }

    public function result(Request $request): View|RedirectResponse
    {
        $comparacion = $request->session()->get(self::CLAVE_SESION);

        if ($comparacion === null) {
            return to_route('image.index')->with('error', 'Primero subí una foto para comparar.');
        }

        $destinoCoincidente = $comparacion['coincide']
            ? Destino::with('imagenes:id,destino_id,titulo')->find($comparacion['mas_cercana']['destino_id'])
            : null;

        // La base pudo cambiar desde la comparación, por ejemplo si se borró el destino.
        if ($comparacion['coincide'] && $destinoCoincidente === null) {
            $request->session()->forget(self::CLAVE_SESION);

            return to_route('image.index')->with('error', 'El destino de la comparación ya no existe. Volvé a subir la foto.');
        }

        return view('image-comparison.result', [
            'comparacion' => $comparacion,
            'destinoCoincidente' => $destinoCoincidente,
            'umbral' => config('turismo.umbral_hamming'),
        ]);
    }

    /**
     * Sirve una foto guardada en la base de datos.
     */
    public function show(Imagen $imagen): Response
    {
        return response($imagen->contenido)->header('Content-Type', $imagen->mime_type);
    }

    public function register(RegisterImageRequest $request): RedirectResponse
    {
        $comparacion = $request->session()->get(self::CLAVE_SESION);

        if ($comparacion === null || $comparacion['coincide']) {
            return to_route('image.index')->with('error', 'No hay ninguna foto pendiente de registro.');
        }

        DB::transaction(function () use ($request, $comparacion): void {
            $destino = Destino::create($request->safe()->except('titulo'));

            $destino->imagenes()->create([
                'titulo' => $request->validated('titulo'),
                'hash_hex' => $comparacion['hash_hex'],
                'mime_type' => $comparacion['mime_type'],
                'contenido' => base64_decode($comparacion['foto_base64']),
            ]);
        });

        $request->session()->forget(self::CLAVE_SESION);

        return to_route('image.index')->with('exito', 'La foto se registró correctamente.');
    }

    public function discard(Request $request): RedirectResponse
    {
        $request->session()->forget(self::CLAVE_SESION);

        return to_route('image.index');
    }
}
