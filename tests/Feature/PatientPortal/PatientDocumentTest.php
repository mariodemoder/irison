<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

use App\Models\Document;

class PatientDocumentTest extends PatientPortalTestCase
{
    public function test_index_returns_own_documents(): void
    {
        $this->makeDocumentFor($this->patient, 'FR-000001');
        $this->makeDocumentFor($this->patient, 'FR-000002');

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/documents');

        $response->assertOk()
            ->assertJsonCount(2, 'documents');
    }

    public function test_show_returns_own_document(): void
    {
        $document = $this->makeDocumentFor($this->patient, 'FR-000001');

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/documents/{$document->id}");

        $response->assertOk()
            ->assertJsonPath('document.id', $document->id)
            ->assertJsonPath('document.type', Document::TYPE_INVOICE);
    }

    public function test_show_other_patient_document_returns_404(): void
    {
        $document = $this->makeDocumentFor($this->otherPatient, 'FR-000100');

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/documents/{$document->id}")
            ->assertNotFound();
    }

    public function test_show_foreign_clinic_document_returns_404(): void
    {
        $document = $this->makeDocumentFor($this->foreignPatient, 'FR-000200');

        $this->withHeaders($this->patientHeaders())
            ->getJson("/api/patient/documents/{$document->id}")
            ->assertNotFound();
    }
}