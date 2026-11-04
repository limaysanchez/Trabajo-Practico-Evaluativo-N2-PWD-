@extends('layouts.app')

@section('content')

<h2 class="mb-4">
    Resultados
</h2>

<div class="row">

    <div class="col-md-4">

        <div class="card">

            <div class="card-header">
                Imagen subida
            </div>

            <img
                src="{{ asset('storage/images/'.$uploadedImage) }}"
                class="img-fluid">

        </div>

    </div>

    <div class="col-md-8">

        <div class="card">

            <div class="card-header">
                Coincidencias
            </div>

            <div class="card-body">

                <table class="table">

                    <thead>

                    <tr>
                        <th>Imagen</th>
                        <th>Distancia</th>
                        <th>Similitud</th>
                    </tr>

                    </thead>

                    <tbody>

                    @foreach($matches as $match)

                        <tr>

                            <td>{{ $match['file'] }}</td>

                            <td>{{ $match['distance'] }}</td>

                            <td>
                                {{ $match['similarity'] }}%
                            </td>

                        </tr>

                    @endforeach

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection