<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Appointment;
use App\Models\Bonus;
use App\Models\Document;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoicingService
{
    /**
     * @return array{document: Document, created: bool}
     */
    public function issueForAppointment(Appointment $appointment, User $user): array
    {
        $created = false;

        $document = DB::transaction(function () use ($appointment, $user, &$created) {
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
                'notes' => $appointment->notes,
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
}
