<?php

namespace Modules\PatientPortal\Application\DTOs;

class ProfileUpdateDTO
{
    public function __construct(
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly ?string $phone,
        public readonly ?string $address,
        public readonly ?string $zip,
        public readonly ?string $city,
        public readonly ?string $province,
        public readonly ?string $country,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            $request->input('first_name'),
            $request->input('last_name'),
            $request->input('phone'),
            $request->input('address'),
            $request->input('zip'),
            $request->input('city'),
            $request->input('province'),
            $request->input('country'),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
        ], fn($v) => $v !== null);
    }
}
