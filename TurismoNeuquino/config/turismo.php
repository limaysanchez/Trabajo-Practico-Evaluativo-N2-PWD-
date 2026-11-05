<?php

return [

    /*
    | Cantidad máxima de bits distintos para considerar que dos fotos coinciden.
    */
    'umbral_hamming' => (int) env('TURISMO_UMBRAL_HAMMING', 5),

];
