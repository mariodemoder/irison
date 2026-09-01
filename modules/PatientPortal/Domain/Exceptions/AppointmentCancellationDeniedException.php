<?php

namespace Modules\PatientPortal\Domain\Exceptions;

use RuntimeException;

class AppointmentCancellationDeniedException extends RuntimeException
{
    public function __construct(int $hours = 24)
    {
        parent::__construct("No es posible cancelar con menos de {$hours}h de antelación. Contacte con la clínica.");
    }
}
