# Frontend Skill

## Error Handling (SPA)
- `resources/js/services/api.js` — Centralized 401 logout, 402 subscription redirect, 403 forbidden/read-only, 422 validation, 500 fallback
- Field-level validation: keep in form component
- Page-level errors: `resources/js/components/ErrorAlert.vue` via global state in `resources/js/shared/globalHttpError.js`, rendered from `App.vue`
- Login/registration use plain `axios` (no interceptor) so invalid creds don't trigger 401 logout
- Repeated load-error messages: `resources/js/shared/httpErrors.js` (`getLoadErrorMessage`)
- Toast: config in `resources/js/services/toastConfig.js`, registered in `resources/js/app.js`
- Toast CSS: Irison classes in `resources/css/app.css` (`.irison-toast`, variant colors, progress bar, close button)

## Button Styling
- Centralized in `resources/css/app.css`
- Shared classes: `.btn`, `.btn--solid`, `.btn--ghost`
- `.muted` for "Volver" and secondary pills
- `.edit-btn` for all edit actions (consistent pill height)
- `.quick-trigger` for actions menu button next to "Volver"
- No inline padding/font-size overrides on edit/back buttons

## Popup Form Styling
- Creation popups: always visible labels (placeholders only complementary)
- Shared CSS in `resources/css/app.css`: `.swal-popup-card`, `.swal-card`, `.create-row`, `.create-grid-2`
- No duplicate popup styles in view-scoped files

## Table Responsiveness (Index Views)

Cambios en `resources/css/app.css:446-489` para que las tablas index de productos, pagos, bonos, facturas, equipo se adapten al ancho del formulario:

- `table-layout: fixed` → `auto` en `.entity-table` (línea 449)
- Eliminados todos los `min-width` fijos por tabla: products (1120px), payments (1160px), bonuses (1260px), invoices (1040px)
- Eliminados `width` fijos de `.col-min` / `.col-mid` (antes tenían 85px y 150px respectivamente)
- Eliminado `@media (max-width:900px)` que forzaba `min-width:760px`
- Se mantienen clases `.col-min` / `.col-mid` en templates Vue pero sin width fijo; heredan de `table-layout:auto`
- Nueva clase `.wide-min` (85px) y `.wide-mid` (150px) para columnas que sí necesitan ancho fijo opcional
- Nueva clase `.col-max` (20%) como alias de `.wide-max`

## Patient Show Layout

`resources/js/views/patients/Show.vue` — Grid de historial en la ficha paciente.

### history-grid
- `grid-template-columns: repeat(2, 1fr)` (antes 3 columnas) para dar más espacio a cada card.
- Cada card contiene: Citas, Bonos, Pagos, Consentimientos.

### Appointments line
- Una línea por cita con: `fecha + hora` (bold nowrap) + `tipo cita` (ellipsis) + `profesional` (nowrap) + `estado` (badge, margin-left auto).
- Flex nowrap en toda la línea.
- Backend: `app/Services/Patients/PatientsServices.php:96-109` eager load `appointments.appointmentType` y `appointments.professional`.
- Response: `appointment_type.description` y `professional.name`.

## Date Picker Protocol

All date/time inputs must use standardized CSS classes defined in `resources/css/theme.css`.

### Classes

| Class | Context | Size |
|---|---|---|
| `.date-field-input` | Form inputs (data entry) | Full — `padding:12px 14px`, `border-radius:10px`, `font-size:15px` |
| `.filter-date` | Filter/search bars (date ranges) | Compact — `padding:8px 12px`, `border-radius:8px`, `font-size:13px` |
| `.time-grid-input` | Schedule grid time cells | Tiny — `padding:6px 8px`, `border-radius:8px`, `font-size:13px` |

### Rules
1. Never use raw `<input type="date">` without a styling class.
2. **Every date/time input MUST have a visible label** (`<label class="filter-date-field">` for filters, `<label class="mini-date-field">` for cards) AND an `aria-label`. Native date inputs ignore `placeholder` — without a label the field shows a bare "dd/mm/aaaa".
3. Use `.date-field-input` for data entry (forms, dialogs, popups).
4. Use `.filter-date` for list filters (agenda, billing, activity, invoices, notifications).
5. Use `.time-grid-input` for time-of-day in schedule tables.
6. SweetAlert date inputs: no class change needed — `.swal-popup-card input[type="date"]` handles it.
7. `BaseInput.vue` supports `type="date"`, `"time"`, `"datetime-local"` with `min`, `max`, `step`, `placeholder`, `disabled`, `required` props.
8. Use `BaseInput` when the input has a standalone label; use raw input with the class when inside complex layouts.

Full reference: `docs/frontend/date-picker-protocol.md`
