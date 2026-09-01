<?php

declare(strict_types=1);

namespace Tests\Feature\PatientPortal;

class PatientPaymentTest extends PatientPortalTestCase
{
    public function test_index_returns_own_payments_paginated(): void
    {
        $this->makePayment($this->patient, ['concept' => 'session', 'status' => 'paid', 'paid_at' => now()]);
        $this->makePayment($this->patient, ['concept' => 'credit', 'status' => 'paid', 'paid_at' => now()]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/payments');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [],
                'total', 'per_page', 'current_page',
            ])
            ->assertJsonPath('total', 2);
    }

    public function test_pending_returns_only_pending_payments(): void
    {
        $this->makePayment($this->patient, ['status' => 'pending']);
        $this->makePayment($this->patient, ['status' => 'paid', 'paid_at' => now()]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/payments/pending');

        $response->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.status', 'pending');
    }

    public function test_index_excludes_foreign_clinic_payments(): void
    {
        $this->makePayment($this->patient, ['status' => 'paid', 'paid_at' => now()]);
        $this->makePayment($this->foreignPatient, ['status' => 'paid', 'paid_at' => now()]);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/payments');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_pending_excludes_other_patient_payments(): void
    {
        $this->makePayment($this->patient, ['status' => 'pending']);
        $this->makePayment($this->otherPatient, ['status' => 'pending']);

        $response = $this->withHeaders($this->patientHeaders())
            ->getJson('/api/patient/payments/pending');

        $response->assertOk()
            ->assertJsonCount(1, 'payments');
    }
}