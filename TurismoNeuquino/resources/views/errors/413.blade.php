@extends('layouts.app')

@section('content')

<div class="alert alert-danger">
    La foto es demasiado grande para el servidor. Probá con una de hasta 5 MB.
</div>

<a href="{{ route('image.index') }}" class="btn btn-primary">Volver</a>

@endsection
