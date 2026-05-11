<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Patient;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoicingService
{
    /**
     * @return array{document: Document, created: bool}
     */
    public function issueForAppointment(Appointment $appointment, User $user, ?string $notes = null): array
    {
        $created = false;
        $normalizedNotes = is_string($notes) ? trim($notes) : null;
        if ($normalizedNotes === '') {
            $normalizedNotes = null;
        }

        $document = DB::transaction(function () use ($appointment, $user, $normalizedNotes, &$created) {
            if (!empty($appointment->invoice_id)) {
                $existingByLink = Document::query()->find((int) $appointment->invoice_id);
                if ($existingByLink) {
                    return $existingByLink;
                }
            }

            $existing = Document::query()
                ->where('clinic_id', (int) $appointment->clinic_id)
                ->where('type', 'invoice')
                ->where('type_from', 'appointment')
                ->where('from_id', (int) $appointment->id)
                ->latest('id')
                ->first();

            if ($existing) {
                $appointment->invoice_id = (int) $existing->id;
                $appointment->save();

                return $existing;
            }

            $appointment->loadMissing('clinic');
            $patient = $appointment->patient()->withTrashed()->first();

            $created = true;

            $document = Document::create([
                'clinic_id' => (int) $appointment->clinic_id,
                'patient_id' => (int) $appointment->patient_id,
                'type' => 'invoice',
                'type_from' => 'appointment',
                'from_id' => (int) $appointment->id,
                'typeinvoice' => 'appointment',
                'clinic_name' => $appointment->clinic?->name,
                'clinic_nif' => $appointment->clinic?->nif,
                'clinic_address' => $appointment->clinic?->address,
                'clinic_zip' => $appointment->clinic?->zip,
                'clinic_province' => $appointment->clinic?->province,
                'clinic_country' => $appointment->clinic?->country,
                'user_full_name' => $user->name,
                'patient_nif' => $patient?->nif,
                'patient_full_name' => $patient?->name,
                'patient_email' => $patient?->email,
                'patient_phone' => $patient?->phone,
                'patient_address' => $patient?->address,
                'patient_zip' => $patient?->zip,
                'date' => now()->toDateString(),
                'amount' => (float) ($appointment->price ?? 0),
                'notes' => $normalizedNotes ?? $appointment->notes,
                'status' => 'issued',
            ]);

            $appointment->invoice_id = (int) $document->id;
            $appointment->save();

            return $document;
        });

        return [
            'document' => $document,
            'created' => $created,
        ];
    }

    /**
     * @return array{document: Document, created: bool}
     */
    public function issueForBonus(Bonus $bonus, User $user): array
    {
        $created = false;

        $document = DB::transaction(function () use ($bonus, $user, &$created) {
            if (!empty($bonus->invoice_id)) {
                $existingByLink = Document::query()->find((int) $bonus->invoice_id);
                if ($existingByLink) {
                    return $existingByLink;
                }
            }

            $existing = Document::query()
                ->where('clinic_id', (int) $bonus->clinic_id)
                ->where('type', 'invoice')
                ->where('type_from', 'package')
                ->where('from_id', (int) $bonus->id)
                ->latest('id')
                ->first();

            if ($existing) {
                $bonus->invoice_id = (int) $existing->id;
                $bonus->save();

                return $existing;
            }

            $bonus->loadMissing('clinic');
            $patient = $bonus->patient()->withTrashed()->first();

            $created = true;

            $document = Document::create([
                'clinic_id' => (int) $bonus->clinic_id,
                'patient_id' => (int) $bonus->patient_id,
                'type' => 'invoice',
                'type_from' => 'package',
                'from_id' => (int) $bonus->id,
                'typeinvoice' => 'package',
                'clinic_name' => $bonus->clinic?->name,
                'clinic_nif' => $bonus->clinic?->nif,
                'clinic_address' => $bonus->clinic?->address,
                'clinic_zip' => $bonus->clinic?->zip,
                'clinic_province' => $bonus->clinic?->province,
                'clinic_country' => $bonus->clinic?->country,
                'user_full_name' => $user->name,
                'patient_nif' => $patient?->nif,
                'patient_full_name' => $patient?->name,
                'patient_email' => $patient?->email,
                'patient_phone' => $patient?->phone,
                'patient_address' => $patient?->address,
                'patient_zip' => $patient?->zip,
                'date' => now()->toDateString(),
                'amount' => (float) ($bonus->price ?? 0),
                'notes' => $bonus->name,
                'status' => 'issued',
            ]);

            $bonus->invoice_id = (int) $document->id;
            $bonus->save();

            return $document;
        });

        return [
            'document' => $document,
            'created' => $created,
        ];
    }

    /**
     * @return array{document: Document, created: bool}
     */
    public function issueAbonoForInvoice(Document $invoice): array
    {
        if ($invoice->type !== Document::TYPE_INVOICE) {
            throw new DomainException('Solo se puede generar un abono desde una factura.');
        }

        $created = false;

        $document = DB::transaction(function () use ($invoice, &$created) {
            $existing = Document::query()
                ->where('clinic_id', (int) $invoice->clinic_id)
                ->where('type', Document::TYPE_ABONO)
                ->where('type_from', Document::TYPE_INVOICE)
                ->where('from_id', (int) $invoice->id)
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $created = true;

            $document = Document::create([
                'clinic_id' => (int) $invoice->clinic_id,
                'patient_id' => (int) $invoice->patient_id,
                'type' => Document::TYPE_ABONO,
                'type_from' => Document::TYPE_INVOICE,
                'from_id' => (int) $invoice->id,
                'typeinvoice' => $invoice->typeinvoice,
                'clinic_name' => $invoice->clinic_name,
                'clinic_nif' => $invoice->clinic_nif,
                'clinic_address' => $invoice->clinic_address,
                'clinic_zip' => $invoice->clinic_zip,
                'clinic_province' => $invoice->clinic_province,
                'clinic_country' => $invoice->clinic_country,
                'user_full_name' => $invoice->user_full_name,
                'patient_nif' => $invoice->patient_nif,
                'patient_full_name' => $invoice->patient_full_name,
                'patient_email' => $invoice->patient_email,
                'patient_phone' => $invoice->patient_phone,
                'patient_address' => $invoice->patient_address,
                'patient_zip' => $invoice->patient_zip,
                'date' => $invoice->date,
                'amount' => (float) $invoice->amount,
                'notes' => $invoice->notes,
                'status' => $invoice->status,
                'is_payed' => $invoice->is_payed,
                'is_sended' => $invoice->is_sended,
            ]);

            return $document;
        });

        return [
            'document' => $document,
            'created' => $created,
        ];
    }

    /**
     * Crea una factura de tipo 'varios' con líneas de items mixtos.
     *
     * @param  array{
     *     patient_id: int,
     *     date?: string,
     *     notes?: string|null,
     *     items: array<int, array{
     *         type: string,
     *         reference_id?: int|null,
     *         description: string,
     *         quantity: float,
     *         unit_price: float,
     *         tax_rate: float,
     *         buy_price?: float,
     *         buy_tax?: float,
     *     }>
     * } $input
     * @return array{document: Document, created: bool}
     * @throws ValidationException
     */
    public function issueVarios(array $input, User $user, int $clinicId): array
    {
        $validated = Validator::make($input, [
            'patient_id'          => ['required', 'integer', 'min:1'],
            'date'                => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:2000'],
            'items'               => ['required', 'array', 'min:1', 'max:100'],
            'items.*.type'        => ['required', 'in:appointment,bonus,product,manual'],
            'items.*.reference_id'=> ['nullable', 'integer', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.buy_price'   => ['nullable', 'numeric', 'min:0'],
            'items.*.buy_tax'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ])->validate();

        $patient = Patient::withTrashed()->find((int) $validated['patient_id']);

        if (!$patient || (int) $patient->clinic_id !== $clinicId) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente seleccionado no es válido.'],
            ]);
        }

        $appointmentIds = collect($validated['items'])
            ->filter(fn (array $row) => ($row['type'] ?? null) === 'appointment' && !empty($row['reference_id']))
            ->map(fn (array $row) => (int) $row['reference_id'])
            ->unique()
            ->values();

        $bonusIds = collect($validated['items'])
            ->filter(fn (array $row) => ($row['type'] ?? null) === 'bonus' && !empty($row['reference_id']))
            ->map(fn (array $row) => (int) $row['reference_id'])
            ->unique()
            ->values();

        $appointments = $appointmentIds->isEmpty()
            ? collect()
            : Appointment::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('id', $appointmentIds)
                ->get()
                ->keyBy('id');

        $bonuses = $bonusIds->isEmpty()
            ? collect()
            : Bonus::query()
                ->where('clinic_id', $clinicId)
                ->whereIn('id', $bonusIds)
                ->get()
                ->keyBy('id');

        $validationErrors = [];

        foreach ($validated['items'] as $index => $row) {
            $rowType = (string) ($row['type'] ?? '');
            $referenceId = isset($row['reference_id']) ? (int) $row['reference_id'] : 0;
            $errorKey = "items.{$index}.reference_id";

            if ($rowType === 'appointment' && $referenceId > 0) {
                /** @var Appointment|null $appointment */
                $appointment = $appointments->get($referenceId);
                if (!$appointment) {
                    $validationErrors[$errorKey][] = 'La cita seleccionada no es válida.';
                    continue;
                }
                if ((int) $appointment->patient_id !== (int) $patient->id) {
                    $validationErrors[$errorKey][] = 'La cita seleccionada no pertenece al paciente de la factura.';
                }
                if (!empty($appointment->invoice_id)) {
                    $validationErrors[$errorKey][] = 'La cita seleccionada ya tiene una factura emitida.';
                }
            }

            if ($rowType === 'bonus' && $referenceId > 0) {
                /** @var Bonus|null $bonus */
                $bonus = $bonuses->get($referenceId);
                if (!$bonus) {
                    $validationErrors[$errorKey][] = 'El bono seleccionado no es válido.';
                    continue;
                }
                if ((int) $bonus->patient_id !== (int) $patient->id) {
                    $validationErrors[$errorKey][] = 'El bono seleccionado no pertenece al paciente de la factura.';
                }
                if (!empty($bonus->invoice_id)) {
                    $validationErrors[$errorKey][] = 'El bono seleccionado ya tiene una factura emitida.';
                }
            }
        }

        if ($validationErrors !== []) {
            throw ValidationException::withMessages($validationErrors);
        }

        $document = DB::transaction(function () use ($validated, $user, $clinicId, $patient) {
            $clinic = \App\Models\Clinic::find($clinicId);

            $totalAmount = 0.0;
            foreach ($validated['items'] as $row) {
                $totalAmount += DocumentItem::computeTotal(
                    (float) $row['quantity'],
                    (float) $row['unit_price'],
                    (float) $row['tax_rate'],
                );
            }

            $document = Document::create([
                'clinic_id'          => $clinicId,
                'patient_id'         => (int) $validated['patient_id'],
                'type'               => Document::TYPE_INVOICE,
                'type_from'          => 'varios',
                'from_id'            => null,
                'typeinvoice'        => Document::TYPEINVOICE_VARIOS,
                'clinic_name'        => $clinic?->name,
                'clinic_nif'         => $clinic?->nif,
                'clinic_address'     => $clinic?->address,
                'clinic_zip'         => $clinic?->zip,
                'clinic_province'    => $clinic?->province,
                'clinic_country'     => $clinic?->country,
                'user_full_name'     => $user->name,
                'patient_nif'        => $patient?->nif,
                'patient_full_name'  => $patient?->name,
                'patient_email'      => $patient?->email,
                'patient_phone'      => $patient?->phone,
                'patient_address'    => $patient?->address,
                'patient_zip'        => $patient?->zip,
                'date'               => $validated['date'] ?? now()->toDateString(),
                'amount'             => $totalAmount,
                'notes'              => $validated['notes'] ?? null,
                'status'             => 'issued',
            ]);

            foreach ($validated['items'] as $index => $row) {
                $total = DocumentItem::computeTotal(
                    (float) $row['quantity'],
                    (float) $row['unit_price'],
                    (float) $row['tax_rate'],
                );

                DocumentItem::create([
                    'document_id'  => $document->id,
                    'type'         => $row['type'],
                    'reference_id' => isset($row['reference_id']) ? (int) $row['reference_id'] : null,
                    'description'  => $row['description'],
                    'quantity'     => (float) $row['quantity'],
                    'unit_price'   => (float) $row['unit_price'],
                    'tax_rate'     => (float) $row['tax_rate'],
                    'buy_price'    => (float) ($row['buy_price'] ?? 0),
                    'buy_tax'      => (float) ($row['buy_tax'] ?? 0),
                    'total'        => $total,
                    'sort_order'   => $index,
                ]);

                $referenceId = isset($row['reference_id']) ? (int) $row['reference_id'] : 0;
                if ($referenceId <= 0) {
                    continue;
                }

                if (($row['type'] ?? null) === 'appointment') {
                    Appointment::query()
                        ->where('clinic_id', $clinicId)
                        ->where('patient_id', (int) $validated['patient_id'])
                        ->where('id', $referenceId)
                        ->whereNull('invoice_id')
                        ->update(['invoice_id' => $document->id]);
                }

                if (($row['type'] ?? null) === 'bonus') {
                    Bonus::query()
                        ->where('clinic_id', $clinicId)
                        ->where('patient_id', (int) $validated['patient_id'])
                        ->where('id', $referenceId)
                        ->whereNull('invoice_id')
                        ->update(['invoice_id' => $document->id]);
                }
            }

            return $document;
        });

        return ['document' => $document, 'created' => true];
    }
}
