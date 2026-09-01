<?php

namespace Modules\PatientPortal\Application\DTOs;

class AppointmentRequestDTO
{
    public function __construct(
        public readonly string $preferred_date,
        public readonly string $preferred_time,
        public readonly ?int $professional_id,
        public readonly ?string $service_name,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            $request->input('preferred_date'),
            $request->input('preferred_time'),
            $request->input('professional_id'),
            $request->input('service_name'),
            $request->input('notes'),
        );
    }
}
