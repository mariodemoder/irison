<?php

namespace App\Exports;

use App\Models\Appointment;
use App\Models\ClinicalRecord;
use App\Models\PatientConsent;
use App\Models\Document;
use App\Models\Bonus;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\Payment;
use App\Models\Product;

class ClinicBackupExport
{
    public function __construct(
        private int $clinicId,
    ) {}

    public function build(XlsxWriter $writer): void
    {
        $this->exportPatients($writer);
        $this->exportPayments($writer);
        $this->exportInvoices($writer);
        $this->exportConsents($writer);
        $this->exportBonuses($writer);
        $this->exportProducts($writer);
        $this->exportPendingAppointments($writer);
        $this->exportClinicalRecords($writer);
        $this->exportAttachments($writer);
    }

    private function exportPatients(XlsxWriter $writer): void
    {
        $writer->addSheet('Pacientes');
        $writer->writeHeaderRow(['ID', 'Contador', 'Nombre', 'Apellidos', 'DNI/NIF', 'Teléfono', 'Email', 'Fecha nacimiento', 'Dirección', 'Notas', 'Creado']);
        Patient::where('clinic_id', $this->clinicId)
            ->chunk(500, function ($patients) use ($writer) {
                foreach ($patients as $p) {
                    $writer->writeRow([
                        $p->id, $p->counter, $p->name, $p->surname ?? '', $p->dni ?? '', $p->phone ?? '', $p->email ?? '',
                        $p->birth_date ?? '', $p->address ?? '', $p->notes ?? '', $p->created_at ?? '',
                    ]);
                }
            });
    }

    private function exportPayments(XlsxWriter $writer): void
    {
        $writer->addSheet('Pagos');
        $writer->writeHeaderRow(['ID', 'Contador', 'Paciente', 'Importe', 'Concepto', 'Método', 'Estado', 'Fecha pago', 'Notas']);
        Payment::where('clinic_id', $this->clinicId)
            ->with('patient:id,counter,first_name,last_name')
            ->chunk(500, function ($payments) use ($writer) {
                foreach ($payments as $pay) {
                    $patientName = $pay->patient
                        ? ($pay->patient->counter ? $pay->patient->counter . ' · ' : '') . $pay->patient->name
                        : ('Paciente #' . $pay->patient_id);
                    $writer->writeRow([
                        $pay->id, $pay->counter ?? '', $patientName, $pay->amount,
                        $pay->concept ?? '', $pay->method ?? '', $pay->status ?? '',
                        $pay->paid_at ?? $pay->created_at ?? '', $pay->notes ?? '',
                    ]);
                }
            });
    }

    private function exportInvoices(XlsxWriter $writer): void
    {
        $writer->addSheet('Facturas');
        $writer->writeHeaderRow(['ID', 'Número', 'Paciente', 'Tipo', 'Importe', 'Estado', 'Pagada', 'Fecha']);
        Document::where('clinic_id', $this->clinicId)
            ->with('patient:id,counter,first_name,last_name')
            ->chunk(500, function ($docs) use ($writer) {
                foreach ($docs as $d) {
                    $patientName = $d->patient
                        ? ($d->patient->counter ? $d->patient->counter . ' · ' : '') . $d->patient->name
                        : ('Paciente #' . $d->patient_id);
                    $writer->writeRow([
                        $d->id, $d->counter ?? '', $patientName, $d->typeinvoice ?? $d->type ?? '',
                        $d->amount, $d->status ?? '', $d->is_payed ? 'Sí' : 'No', $d->date ?? $d->created_at ?? '',
                    ]);
                }
            });
    }

    private function exportConsents(XlsxWriter $writer): void
    {
        $writer->addSheet('Consentimientos');
        $writer->writeHeaderRow(['ID', 'Paciente', 'Plantilla', 'Estado', 'Creado', 'Firmado']);
        PatientConsent::where('clinic_id', $this->clinicId)
            ->with('patient:id,counter,first_name,last_name')
            ->chunk(500, function ($consents) use ($writer) {
                foreach ($consents as $c) {
                    $patientName = $c->patient
                        ? ($c->patient->counter ? $c->patient->counter . ' · ' : '') . $c->patient->name
                        : ('Paciente #' . $c->patient_id);
                    $writer->writeRow([
                        $c->id, $patientName, $c->template?->title ?? '—', $c->status ?? '',
                        $c->created_at ?? '', $c->signed_at ?? '',
                    ]);
                }
            });
    }

    private function exportBonuses(XlsxWriter $writer): void
    {
        $writer->addSheet('Bonos');
        $writer->writeHeaderRow(['ID', 'Contador', 'Nombre', 'Paciente', 'Sesiones totales', 'Sesiones restantes', 'Precio', 'Facturado', 'Estado', 'Expira']);
        Bonus::where('clinic_id', $this->clinicId)
            ->with('patient:id,counter,first_name,last_name')
            ->chunk(500, function ($bonuses) use ($writer) {
                foreach ($bonuses as $b) {
                    $patientName = $b->patient
                        ? ($b->patient->counter ? $b->patient->counter . ' · ' : '') . $b->patient->name
                        : ('Paciente #' . $b->patient_id);
                    $writer->writeRow([
                        $b->id, $b->counter ?? '', $b->name ?? '', $patientName,
                        $b->total_sessions ?? 0, $b->remaining_sessions ?? 0,
                        $b->price ?? 0, $b->invoice_id ? 'Sí' : 'No',
                        $b->status ?? '', $b->expires_at ?? '',
                    ]);
                }
            });
    }

    private function exportProducts(XlsxWriter $writer): void
    {
        $writer->addSheet('Productos');
        $writer->writeHeaderRow(['ID', 'Referencia', 'Nombre', 'Precio venta', 'IVA venta', 'Precio compra', 'IVA compra', 'Familia', 'Lote', 'Stock']);
        Product::where('clinic_id', $this->clinicId)
            ->chunk(500, function ($products) use ($writer) {
                foreach ($products as $prod) {
                    $writer->writeRow([
                        $prod->id, $prod->reference ?? '', $prod->name ?? '', $prod->sale_price ?? 0,
                        $prod->sale_tax ?? '', $prod->buy_price ?? '', $prod->buy_tax ?? '',
                        $prod->family ?? '', $prod->lot ?? '', $prod->stock ?? 0,
                    ]);
                }
            });
    }

    private function exportPendingAppointments(XlsxWriter $writer): void
    {
        $writer->addSheet('Agenda pendiente');
        $writer->writeHeaderRow(['ID', 'Paciente', 'Tipo cita', 'Profesional', 'Inicio', 'Estado', 'Notas']);
        Appointment::where('clinic_id', $this->clinicId)
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->with(['patient:id,counter,first_name,last_name', 'appointmentType:id,description', 'professional:id,name'])
            ->chunk(500, function ($apps) use ($writer) {
                foreach ($apps as $a) {
                    $patientName = $a->patient
                        ? ($a->patient->counter ? $a->patient->counter . ' · ' : '') . $a->patient->name
                        : ('Paciente #' . $a->patient_id);
                    $writer->writeRow([
                        $a->id, $patientName, $a->appointmentType?->description ?? '', $a->professional?->name ?? '',
                        $a->start_time ?? '', $a->status ?? '', $a->notes ?? '',
                    ]);
                }
            });
    }

    private function exportClinicalRecords(XlsxWriter $writer): void
    {
        $writer->addSheet('Historias clínicas');
        $writer->writeHeaderRow(['Paciente', 'NIF', 'Notas de cita']);
        Appointment::where('clinic_id', $this->clinicId)
            ->where('status', 'completed')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->with('patient:id,counter,first_name,last_name,nif')
            ->chunk(500, function ($apps) use ($writer) {
                foreach ($apps as $a) {
                    $patientName = $a->patient
                        ? ($a->patient->counter ? $a->patient->counter . ' · ' : '') . $a->patient->name
                        : ('Paciente #' . $a->patient_id);
                    $writer->writeRow([
                        $patientName, $a->patient?->nif ?? '', $a->notes ?? '',
                    ]);
                }
            });
    }

    private function exportAttachments(XlsxWriter $writer): void
    {
        $writer->addSheet('Adjuntos');
        $writer->writeHeaderRow(['ID', 'Paciente', 'Descripción', 'Tipo archivo', 'Tamaño (KB)', 'Creado']);
        PatientImage::where('clinic_id', $this->clinicId)
            ->with('patient:id,counter,first_name,last_name')
            ->chunk(500, function ($images) use ($writer) {
                foreach ($images as $img) {
                    $patientName = $img->patient
                        ? ($img->patient->counter ? $img->patient->counter . ' · ' : '') . $img->patient->name
                        : ('Paciente #' . $img->patient_id);
                    $writer->writeRow([
                        $img->id, $patientName, $img->description ?? '', $img->mime_type ?? '',
                        $img->size_bytes ? round($img->size_bytes / 1024, 1) : 0, $img->created_at ?? '',
                    ]);
                }
            });
    }
}
