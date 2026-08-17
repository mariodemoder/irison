<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cadencia de los recordatorios de citas
    |--------------------------------------------------------------------------
    |
    | Intervalo en minutos con el que se ejecutan los jobs de recordatorios
    | de citas (24h y 2h). Se ajusta mediante la variable de entorno
    | REMINDER_INTERVAL_MINUTES. Valores razonables: 1-59.
    |
    */

    'interval_minutes' => (int) env('REMINDER_INTERVAL_MINUTES', 15),

];
