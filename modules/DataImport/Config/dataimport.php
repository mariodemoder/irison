<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | DataImport Module Configuration
    |--------------------------------------------------------------------------
    |
    | Límites y defaults del módulo de importación CSV (planes PRO/Enterprise).
    |
    */

    // Máximo de filas de datos procesadas por petición (import síncrono).
    'max_rows' => 2000,

    // Tamaño máximo del CSV en KB (validación de UploadedFile).
    'max_csv_kb' => 5120,

    // Tamaño máximo del ZIP de imágenes en KB (validación de UploadedFile).
    'max_zip_kb' => 10240,

    // Estado por defecto al importar pacientes.
    'patient_status_default' => 'active',

    // Comportamiento de la cita inicial generada desde historias clínicas.
    'clinical_history' => [
        'default_status' => 'completed',
        'booking_source' => 'import',
        'price' => 0,
    ],

    // Imágenes de paciente (mismas reglas que PatientImageController).
    'images' => [
        'max_per_patient' => 6,
        'max_kb' => 200,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],
];