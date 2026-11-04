@extends('layouts.app')

@section('content')

<div class="card shadow">

    <div class="card-header">
        Turismo Neuquino: reconocé tu destino
    </div>

    <div class="card-body">

        <p>
            Subí una foto y buscamos si fue tomada en alguno de los destinos turísticos
            de la provincia de Neuquén que tenemos registrados.
        </p>

        <form action="{{ route('image.compare') }}"
              method="POST"
              enctype="multipart/form-data">

            @csrf

            <div class="mb-3">

                <label class="form-label" for="image">
                    Foto del lugar
                </label>

                <input
                    type="file"
                    id="image"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="form-control @error('image') is-invalid @enderror"
                    required>

                @error('image')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror

                <div class="form-text">Formatos JPG, PNG o WEBP, hasta 5 MB.</div>

            </div>

            <button
                class="btn btn-primary">

                Viajar bien zarpado.

            </button>

        </form>

    </div>

</div>

@endsection
