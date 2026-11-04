<?php

return [

    /*
    | Cantidad máxima de bits distintos para considerar que dos fotos coinciden.
    */
    'umbral_hamming' => (int) env('TURISMO_UMBRAL_HAMMING', 5),

    /*
    | Base de datos JSON, relativa al disco "local" (storage/app/private).
    */
    'archivo_json' => 'turismo/images.json',

    /*
    | Fotos subidas que todavía no se registraron, relativas al disco "local".
    */
    'carpeta_temporales' => 'temporales',

    /*
    | Fotos registradas, relativas al disco "public" (storage/app/public).
    */
    'carpeta_destinos' => 'destinos',

    /*
    | Antigüedad a partir de la cual se borra una foto temporal abandonada.
    */
    'horas_vida_temporales' => 24,

    /*
    | Datos de partida versionados en git, que usa el comando turismo:sembrar.
    */
    'ruta_semillas' => database_path('semillas'),

];
