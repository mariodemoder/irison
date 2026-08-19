# Finance Module

## Overview

The Finance module (`modules/Finance/`) manages expenses, team member rates (tarifas), and profitability calculations for each clinic.

## Tabs

### Gastos (Expenses)
- CRUD for clinic expenses with categories, IVA, and payment methods.
- Paginated list with search and category filter.

### Tarifas (Rates)
- Shows **all team members** (not just professionals with profile slug 'professional') in the rates table.
- Each team member can have a `cost_per_hour` value used for labor cost calculations in the benefits dashboard.
- **Owner restriction**: The backend `ProfessionalRateController::update()` prevents assigning `cost_per_hour` to the clinic owner (returns 422).
- Rates are stored in the `professional_rates` table keyed by `user_id`.

### Pendientes de Cobro
- First tab in the Finance page (default active tab).
- Shows all appointments with `payment_status` of `pending` or `partially_paid`, excluding canceled appointments.
- Uses `AppointmentPendingPaymentService::calculatePendingAmount()` to compute the actual pending amount per appointment, accounting for bonus coverage, credit usages, and existing payments.
- Displays a summary bar with total count and total pending amount.
- Filters: professional, date range (from/to).
- Each row shows: date, patient, professional, service, price, paid amount, pending amount, and status chip (Pendiente/Parcial).
- **Register Payment** button opens a modal pre-filled with the pending amount. User selects payment method (Efectivo/Tarjeta/Transferencia) and optional notes.
- Partial payments update the appointment to `partially_paid`; full payments update to `paid`.
- Overpayment is blocked server-side (amount must not exceed pending amount).

### Beneficios (Benefits)
- Calculates revenue, labor cost, expenses, profit, and margin for a selected date range.
- Breakdowns by service, by professional, and by expense category.
- **New KPIs**: ticket medio (average revenue per paid operation) and total paid operations count.
- **New breakdown**: revenue by payment method (Efectivo, Tarjeta, Transferencia) with count, total, and percentage.
- Supports period-over-period variation comparison.

## Key Files

- `modules/Finance/Infrastructure/Controllers/ProfessionalRateController.php` — CRUD for team member rates.
- `modules/Finance/Routes/api.php` — API routes under the `auth` middleware group.
- `resources/js/views/finance/Index.vue` — SPA view with the three tabs.
- `modules/Finance/Infrastructure/Persistence/BenefitsDataProvider.php` — Financial aggregations for the benefits dashboard.

## Benefits API Response Structure

The `GET /api/finance/benefits` endpoint returns:

```json
{
  "data": {
    "totals": {
      "revenue": 0,
      "expenses": 0,
      "labor_cost": 0,
      "cost": 0,
      "profit": 0,
      "margin_percentage": 0,
      "paid_operations_count": 0
    },
    "by_service": [{ "name": "", "count": 0, "revenue": 0 }],
    "by_professional": [{ "user_id": 0, "user_name": "", "revenue": 0, "labor_cost": 0, "contribution": 0 }],
    "by_category": [{ "name": "", "total": 0 }],
    "revenue_by_payment_method": [{ "method": "cash", "label": "Efectivo", "count": 0, "total": 0, "percentage": 0 }],
    "previous_totals": { ... },
    "variation": { ... }
  }
}
```

### New KPIs (Fase 0)
- **`paid_operations_count`**: Total completed payments excluding refunds in the period.
- **`ticket medio`** (frontend computed): `revenue / paid_operations_count`.

### New Breakdown (Fase 0)
- **`revenue_by_payment_method`**: Groups completed payments by `method` field. Labels: cash→Efectivo, card→Tarjeta, transfer→Transferencia. Each entry includes `count`, `total`, and `percentage` (of total revenue).

## Design Decision: All Team Members in Rates

As of the fix for the "Tarifas" tab, the rates table displays **all team members** rather than only users with `profile.slug === 'professional'`. This ensures non-professional staff (e.g., admins, receptionists) can also have an assigned cost_per_hour for accurate labor cost calculations. The backend still enforces that the clinic owner cannot have a rate set.
