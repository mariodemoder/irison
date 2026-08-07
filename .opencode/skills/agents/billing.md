# Especialista en Facturación — Irison

Dominio: facturas (Document), pagos (Payment, BillingPayment), suscripciones, Stripe, vistas Vue de facturación.

## Dominio principal

### Backend
- `Document` — facturas. Campos: `typeinvoice` (`appointment`|`package`), `counter` (autoasignado por `CounterService`), `amount`, `status` (`draft`|`issued`|`cancelled`), datos snapshotteados (`patient_*`, `clinic_*`)
- `Payment` — pagos vinculados a citas/paquetes
- `BillingPayment` — pagos de suscripción (Stripe/fake)
- `InvoicingService` (`app/Services/Documents/InvoicingService.php`) — creación/gestión de facturas
- `PaymentService` (`app/Services/Payments/PaymentService.php`) — pagos a pacientes
- `PaymentProviderInterface` + `Resolver` + `FakePaymentProvider` + `StripePaymentProvider` — abstracción de proveedor (`modules/Subscriptions/Infrastructure/Payment/`)
- Controllers: `BillingController`, `PaymentController`, `StripeCheckoutController`, `StripeWebhookHandler`, `DocumentController` (billing en `modules/Subscriptions/Infrastructure/Controllers/`)
- Policies: `PaymentPolicy`, `DocumentPolicy`

### Frontend
- `resources/js/views/invoices/Index.vue` — listado con filtros y totales
- `resources/js/views/invoices/Show.vue` — detalle con PDF preview y botón de abonar
- Helpers: `formatCurrency`, `formatDateOnlyDay`, `goBackWithPriority`

## Convenciones
- Multi-tenancy: filtrar por `clinic_id`, usar `Gate::authorize`
- Snapshots: datos de paciente/clínica se copian al crear factura, no se referencian dinámicamente
- Numeración: `CounterService` asigna `counter` en evento `creating` del modelo
- Estados: `draft` = pendiente, `issued` = pagado, `cancelled` = anulado
- Proveedor: `Resolver::resolve()` — nunca hardcodear
- Tests: SQLite in-memory, usar factories

## Stripe no disponible
- Backend: `BillingController::createCheckout()` → `503` + `code=STRIPE_UNREACHABLE`
- Frontend: `BillingRequired.vue` → fallback local `POST /api/subscribe/fake`
