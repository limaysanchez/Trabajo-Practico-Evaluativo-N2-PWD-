@extends('layouts.app')

@section('content')

<div class="card shadow">

    <div class="card-header">
        Comparador de imágenes
    </div>

    <div class="card-body">

        <form action="{{ route('image.compare') }}"
              method="POST"
              enctype="multipart/form-data">

            @csrf

            <div class="mb-3">

                <label class="form-label">
                    Imagen
                </label>

                <input
                    type="file"
                    name="image"
                    class="form-control"
                    required>

            </div>

            <button
                class="btn btn-primary">

                Viajar bien zarpado.

            </button>

        </form>

    </div>

</div>

@endsection