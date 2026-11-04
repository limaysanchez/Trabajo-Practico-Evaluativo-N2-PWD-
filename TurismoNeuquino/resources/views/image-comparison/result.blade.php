@extends('layouts.app')

@section('content')

@php
    $masCercana = $comparacion['mas_cercana'];
    $modoAnterior = old('modo');
@endphp

<h2 class="mb-4">
    Resultado de la comparación
</h2>

@if ($comparacion['coincide'])
    <div class="alert alert-success">
        ¡Coincidencia encontrada! Esta foto fue tomada en <strong>{{ $destinoCoincidente['nombre'] }}</strong>.
    </div>
@else
    <div class="alert alert-warning">
        No encontramos ninguna coincidencia con los destinos registrados.
        Podés sumar esta foto al catálogo o descartarla.
    </div>
@endif

<div class="row g-4 mb-4">

    <div class="col-md-4">

        <div class="card">

            <div class="card-header">
                Foto subida
            </div>

            <img
                src="{{ route('image.temporary') }}"
                alt="Foto subida"
                class="img-fluid">

        </div>

    </div>

    <div class="col-md-8">

        <div class="card">

            <div class="card-header">
                Detalle técnico
            </div>

            <div class="card-body">

                <table class="table mb-0">

                    <tbody>

                    <tr>
                        <th scope="row">Hash de la foto subida</th>
                        <td><code>{{ $comparacion['hash_hex'] }}</code></td>
                    </tr>

                    @if ($masCercana)

                        <tr>
                            <th scope="row">Hash de la foto más parecida</th>
                            <td><code>{{ $masCercana['hash_hex'] }}</code></td>
                        </tr>

                        <tr>
                            <th scope="row">Distancia de Hamming</th>
                            <td>{{ $masCercana['distancia'] }} de 64 bits (coincide con hasta {{ $umbral }})</td>
                        </tr>

                        <tr>
                            <th scope="row">Similitud</th>
                            <td>

                                <div class="progress">

                                    <div
                                        class="progress-bar {{ $comparacion['coincide'] ? 'bg-success' : 'bg-secondary' }}"
                                        style="width: {{ $masCercana['similitud'] }}%">

                                        {{ number_format($masCercana['similitud'], 2, ',', '.') }}%

                                    </div>

                                </div>

                            </td>
                        </tr>

                    @else

                        <tr>
                            <td colspan="2">Todavía no hay fotos registradas para comparar.</td>
                        </tr>

                    @endif

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@if ($comparacion['coincide'])

    <h3 class="h4">{{ $destinoCoincidente['nombre'] }}</h3>

    <p class="text-muted">
        @if (! empty($destinoCoincidente['localidad_cercana']))
            Cerca de {{ $destinoCoincidente['localidad_cercana'] }}.
        @endif
        Departamento {{ $destinoCoincidente['departamento'] ?? 'sin indicar' }}.
        {{ $destinoCoincidente['descripcion'] ?? '' }}
    </p>

    <div class="row g-3 mb-4">

        @foreach ($destinoCoincidente['imagenes'] as $imagen)

            <div class="col-6 col-md-3">

                <div class="card h-100">

                    <img
                        src="{{ asset('storage/'.$imagen['archivo']) }}"
                        alt="{{ $imagen['titulo'] ?? 'Foto del destino' }}"
                        class="card-img-top">

                    <div class="card-body p-2">
                        <small>{{ $imagen['titulo'] ?? '' }}</small>
                    </div>

                </div>

            </div>

        @endforeach

    </div>

@else

    <div class="row g-4 mb-4">

        @if ($destinos !== [])

            <div class="col-md-6">

                <div class="card h-100">

                    <div class="card-header">
                        Asociar a un destino existente
                    </div>

                    <div class="card-body">

                        <form action="{{ route('image.register') }}" method="POST">

                            @csrf

                            <input type="hidden" name="modo" value="existente">

                            <div class="mb-3">

                                <label class="form-label" for="destino_id">Destino</label>

                                <select id="destino_id" name="destino_id"
                                        class="form-select @error('destino_id') is-invalid @enderror" required>

                                    <option value="">Elegí un destino</option>

                                    @foreach ($destinos as $destino)
                                        <option value="{{ $destino['id'] }}" @selected(old('destino_id') === $destino['id'])>
                                            {{ $destino['nombre'] }}
                                        </option>
                                    @endforeach

                                </select>

                                @error('destino_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                            </div>

                            <div class="mb-3">

                                <label class="form-label" for="titulo_existente">Título de la imagen</label>

                                <input type="text" id="titulo_existente" name="titulo" maxlength="120"
                                       value="{{ $modoAnterior === 'existente' ? old('titulo') : '' }}"
                                       class="form-control {{ $modoAnterior === 'existente' && $errors->has('titulo') ? 'is-invalid' : '' }}"
                                       required>

                                @if ($modoAnterior === 'existente')
                                    @error('titulo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif

                            </div>

                            <button class="btn btn-primary">Registrar en este destino</button>

                        </form>

                    </div>

                </div>

            </div>

        @endif

        <div class="col-md-6">

            <div class="card h-100">

                <div class="card-header">
                    Crear un lugar turístico nuevo
                </div>

                <div class="card-body">

                    <form action="{{ route('image.register') }}" method="POST">

                        @csrf

                        <input type="hidden" name="modo" value="nuevo">

                        <div class="mb-3">

                            <label class="form-label" for="nombre">Nombre del lugar</label>

                            <input type="text" id="nombre" name="nombre" maxlength="120" value="{{ old('nombre') }}"
                                   class="form-control @error('nombre') is-invalid @enderror" required>

                            @error('nombre')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                        </div>

                        <div class="mb-3">

                            <label class="form-label" for="departamento">Departamento</label>

                            <input type="text" id="departamento" name="departamento" maxlength="80" value="{{ old('departamento') }}"
                                   class="form-control @error('departamento') is-invalid @enderror" required>

                            @error('departamento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                        </div>

                        <div class="mb-3">

                            <label class="form-label" for="descripcion">Descripción</label>

                            <textarea id="descripcion" name="descripcion" rows="3" maxlength="1000"
                                      class="form-control @error('descripcion') is-invalid @enderror" required>{{ old('descripcion') }}</textarea>

                            @error('descripcion')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                        </div>

                        <div class="mb-3">

                            <label class="form-label" for="titulo_nuevo">Título de la imagen</label>

                            <input type="text" id="titulo_nuevo" name="titulo" maxlength="120"
                                   value="{{ $modoAnterior === 'nuevo' ? old('titulo') : '' }}"
                                   class="form-control {{ $modoAnterior === 'nuevo' && $errors->has('titulo') ? 'is-invalid' : '' }}"
                                   required>

                            @if ($modoAnterior === 'nuevo')
                                @error('titulo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif

                        </div>

                        <button class="btn btn-primary">Crear lugar y registrar</button>

                    </form>

                </div>

            </div>

        </div>

    </div>

@endif

<form action="{{ route('image.discard') }}" method="POST" class="mb-4">

    @csrf

    <button class="btn btn-outline-secondary">
        {{ $comparacion['coincide'] ? 'Comparar otra foto' : 'Descartar esta foto' }}
    </button>

</form>

<div class="card">

    <div class="card-header">
        ¿Cómo funciona?
    </div>

    <div class="card-body row">

        <div class="col-md-6">

            <h4 class="h6">Lo aporta la librería jenssegers/imagehash</h4>

            <ul class="mb-md-0">
                <li>Reducción de la foto a 9x8 píxeles y cálculo de su luminancia.</li>
                <li>Hash DifferenceHash de 64 bits.</li>
                <li>Conversión a hexadecimal y distancia de Hamming.</li>
            </ul>

        </div>

        <div class="col-md-6">

            <h4 class="h6">Lo desarrolló el grupo</h4>

            <ul class="mb-0">
                <li>Inyección de dependencias con Laravel.</li>
                <li>Persistencia en JSON con ImageJsonRepository.</li>
                <li>Reconstrucción del hash desde hexadecimal, umbral y similitud.</li>
                <li>Manejo de archivos temporales e interfaz en español.</li>
            </ul>

        </div>

    </div>

</div>

@endsection
