# Date Picker Protocol

Uniform styling for all date, time, and datetime inputs across the SPA.

## Rules

1. **Never use raw `<input type="date">` without a styling class.**
2. **Every date/time input MUST have a visible label.** Native date inputs do not support `placeholder` — they always show the browser locale format ("dd/mm/aaaa"), so an unlabeled date field is meaningless. Always associate a visible `<label>` and add an `aria-label`.
3. Always pick the correct class variant for the context.
4. The `BaseInput` component supports `type="date"`, `"time"`, and `"datetime-local"` via props `min`, `max`, `step`, `placeholder`, `disabled`, `required`.

## Label Wrappers

Native `<input type="date">` ignores `placeholder`. To prevent "dd/mm/aaaa" from appearing without context, wrap date inputs in a label with a stacked visible caption.

### `.filter-date-field` — Filter bars (with `.filter-date`)

```html
<label class="filter-date-field">
  <span>Desde</span>
  <input v-model="filters.from_date" type="date" class="filter-date" @change="load(1)" />
</label>
```

### `.mini-date-field` — Inline forms / cards (with `.date-field-input`)

```html
<label class="mini-date-field">
  <span>Desde</span>
  <input v-model="closedRangeStart" class="date-field-input" type="date" />
</label>
```

Both render a 12px muted uppercase label above the input. Use labels **"Desde" / "Hasta"** for ranges and **"Fecha"** for single dates.

## CSS Classes (defined in `resources/css/theme.css`)

### `.date-field-input` — Form Inputs

Use for **data entry**: forms, dialogs, popups, expense dates, appointment dates, patient birth date, invoice date, bonus expiry, etc.

```html
<div class="field">
  <label class="label">Fecha</label>
  <input v-model="form.date" type="date" class="date-field-input" />
</div>
```

- Full width, `padding: 12px 14px`, `border-radius: 10px`, `font-size: 15px`
- Same visual weight as `.input-field` (the global text input class)
- Premium focus ring: blue border + `rgba(primary, 0.15)` shadow

### `.filter-date` — Filter / Search Bar Inputs

Use for **list filtering**: agenda filters, billing date ranges, activity log, invoice log, notification log.

```html
<div class="filters">
  <select v-model="status">...</select>
  <input v-model="fromDate" type="date" class="filter-date" @change="load(1)" />
  <input v-model="toDate" type="date" class="filter-date" @change="load(1)" />
</div>
```

- Compact: `padding: 8px 12px`, `border-radius: 8px`, `font-size: 13px`
- Same height as adjacent `<select>` and `.search-input` elements

### `.time-grid-input` — Schedule Grid Time Inputs

Use for **time-of-day** entries inside schedule tables (business hours, team schedules, booking settings).

```html
<td v-for="row in businessHours" :key="`start-${row.day}`" class="hours-cell-center">
  <input class="time-grid-input" type="time" step="300" v-model="row.start" :disabled="!row.enabled" />
</td>
```

- Very compact: `padding: 6px 8px`, `border-radius: 8px`, `font-size: 13px`
- Fits inside table cells without overflow

### SweetAlert Popups — No class change needed

Date inputs inside SweetAlert2 dialogs are automatically styled via:

```css
.swal-popup-card input[type="date"] { ... }
```

The SweetAlert popup must have `customClass: { popup: 'swal-popup-card' }` in its config.

## BaseInput Component

`BaseInput.vue` can now handle date/time types:

```vue
<BaseInput
  label="Fecha de nacimiento"
  type="date"
  v-model="form.birth_date"
  :min="'1900-01-01'"
  :max="today"
/>
```

**When to use BaseInput vs raw input:**
- Use `BaseInput` when the input has a `label` and stands alone in a field group
- Use raw input with `.date-field-input` when the input is part of a complex layout (e.g., inside `datetime-pair`, `closed-controls-row`, or a grid cell)

## Do NOT Modify

- `AgendaDay.vue` mini-calendar (`class="mini-date"`) — this is a custom visual calendar component, not a standard input
- `SmallCalendar.vue` — dead code, stub only
- `StepCalendar.vue` — public booking calendar, fully custom

## Accessibility Checklist

For every date/time input added or edited:

- [ ] Visible `<label>` associated with the input (wraps it, or `for`/`id` binding)
- [ ] `aria-label` set (descriptive, e.g. `Fecha de nacimiento`, `Hora de entrada Lunes`)
- [ ] Correct styling class (`.date-field-input` form, `.filter-date` filter, `.time-grid-input` grid)
- [ ] Filter ranges use `.filter-date-field` wrapper with "Desde"/"Hasta"
- [ ] Inline card ranges use `.mini-date-field` wrapper with "Desde"/"Hasta"
- [ ] Single dates in cards use `.mini-date-field` with "Fecha"

## Files Reference

| File | Class Used |
|---|---|
| `appointments/Form.vue` | `.date-field-input` (start/end date) |
| `patients/Form.vue` | `.date-field-input` (birth date) |
| `finance/Index.vue` | `.filter-date` (benefits range), `.date-field-input` (expense date) |
| `invoices/Index.vue` | `.filter-date` (date range) |
| `invoices/Form.vue` | `.date-field-input` (invoice date) |
| `notifications/Index.vue` | `.filter-date` (date range) |
| `settings/Activity.vue` | `.filter-date` (date range) |
| `Configuration.vue` | `.time-grid-input` (business hours), `.date-field-input` (closed days) |
| `team/TeamUserForm.vue` | `.time-grid-input` (schedules), `.date-field-input` (exceptions) |
| `PatientBonuses.vue` | `.date-field-input` (bonus expiry) |
| `QuickAppointmentForm.vue` | `.date-field-input` (quick appointment date) |
| `payments/Form.vue` | `.date-field-input` (payment datetime-local) |
| `settings/BookingSettings.vue` | `.time-grid-input` (booking schedules) |
