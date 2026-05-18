<?php

namespace Tests\Feature\Documents;

use App\Http\Middleware\EnsureClinic;
use App\Http\Middleware\EnsureClinicIsActive;
use App\Http\Middleware\CheckSubscriptionAccess;
use App\Models\Clinic;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IssueAbonoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureClinic::class);
        $this->withoutMiddleware(EnsureClinicIsActive::class);
        $this->withoutMiddleware(CheckSubscriptionAccess::class);
    }

    public function test_it_creates_a_rectification_invoice_from_an_invoice_using_abono_counter(): void
    {
        $clinic = Clinic::create(['name' => 'Clinic Test']);
        $patient = Patient::create([
            'clinic_id' => $clinic->id,
            'first_name' => 'Mario',
            'last_name' => 'Paciente',
            'nif' => '12345678A',
            'email' => 'mario@example.test',
            'phone' => '600000000',
            'address' => 'Calle Falsa 123',
            'zip' => '28001',
        ]);

        $user = User::create([
            'name' => 'Owner Test',
            'email' => 'owner.abono@example.test',
            'password' => Hash::make('password'),
            'clinic_id' => $clinic->id,
            'role' => 'owner',
        ]);

        $invoice = Document::create([
            'clinic_id' => $clinic->id,
            'patient_id' => $patient->id,
            'type' => Document::TYPE_INVOICE,
            'type_from' => 'package',
            'from_id' => 45,
            'counter' => 'FR-000001',
            'typeinvoice' => 'package',
            'clinic_name' => 'Clinic Test',
            'clinic_nif' => 'B12345678',
            'clinic_address' => 'Avenida Principal 1',
            'clinic_zip' => '28001',
            'clinic_province' => 'Madrid',
            'clinic_country' => 'España',
            'user_full_name' => 'Owner Test',
            'patient_nif' => '12345678A',
            'patient_full_name' => 'Mario Paciente',
            'patient_email' => 'mario@example.test',
            'patient_phone' => '600000000',
            'patient_address' => 'Calle Falsa 123',
            'patient_zip' => '28001',
            'date' => '2026-03-29',
            'amount' => 89.50,
            'notes' => 'Bono 5 sesiones',
            'status' => 'issued',
            'is_payed' => true,
            'is_sended' => false,
        ]);

        DocumentItem::create([
            'document_id' => $invoice->id,
            'type' => DocumentItem::TYPE_MANUAL,
            'reference_id' => null,
            'description' => 'Sesión 60 minutos',
            'quantity' => 1,
            'unit_price' => 50,
            'tax_rate' => 21,
            'buy_price' => 0,
            'buy_tax' => 0,
            'total' => 60.50,
            'sort_order' => 0,
        ]);

        DocumentItem::create([
            'document_id' => $invoice->id,
            'type' => DocumentItem::TYPE_MANUAL,
            'reference_id' => null,
            'description' => 'Masaje descarga',
            'quantity' => 2,
            'unit_price' => 12,
            'tax_rate' => 10,
            'buy_price' => 0,
            'buy_tax' => 0,
            'total' => 26.40,
            'sort_order' => 1,
        ]);

        $this->actingAs($user, 'sanctum');
        app()->instance('activeClinic', null);

        $response = $this->postJson('/api/documents/' . $invoice->id . '/abono');

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', Document::TYPE_ABONO);

        $abonoId = (int) $response->json('data.id');

        $abono = Document::query()->findOrFail($abonoId);

        $this->assertSame(Document::TYPE_ABONO, $abono->type);
        $this->assertSame(Document::TYPE_INVOICE, $abono->type_from);
        $this->assertSame($invoice->id, (int) $abono->from_id);
        $this->assertSame($invoice->typeinvoice, $abono->typeinvoice);
        $this->assertSame((string) $invoice->amount, (string) $abono->amount);
        $this->assertSame($invoice->notes, $abono->notes);
        $this->assertSame($invoice->clinic_name, $abono->clinic_name);
        $this->assertSame($invoice->patient_full_name, $abono->patient_full_name);
        $this->assertMatchesRegularExpression('/^AB-\d{6}$/', (string) $abono->counter);

        $invoiceItems = DocumentItem::query()
            ->where('document_id', $invoice->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $abonoItems = DocumentItem::query()
            ->where('document_id', $abono->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->assertCount($invoiceItems->count(), $abonoItems);

        foreach ($invoiceItems as $index => $invoiceItem) {
            $abonoItem = $abonoItems[$index];

            $this->assertSame($invoiceItem->type, $abonoItem->type);
            $this->assertSame($invoiceItem->reference_id, $abonoItem->reference_id);
            $this->assertSame($invoiceItem->description, $abonoItem->description);
            $this->assertSame((string) $invoiceItem->quantity, (string) $abonoItem->quantity);
            $this->assertSame((string) $invoiceItem->unit_price, (string) $abonoItem->unit_price);
            $this->assertSame((string) $invoiceItem->tax_rate, (string) $abonoItem->tax_rate);
            $this->assertSame((string) $invoiceItem->total, (string) $abonoItem->total);
            $this->assertSame((int) $invoiceItem->sort_order, (int) $abonoItem->sort_order);
        }

        $secondResponse = $this->postJson('/api/documents/' . $invoice->id . '/abono');

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.id', $abono->id);

        $this->assertSame(1, Document::query()
            ->where('type', Document::TYPE_ABONO)
            ->where('type_from', Document::TYPE_INVOICE)
            ->where('from_id', $invoice->id)
            ->count());
    }
}