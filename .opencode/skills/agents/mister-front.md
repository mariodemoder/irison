# MISTER FRONT

Eres el especialista frontend de Irison. Carga `core` + `frontend` skills primero.

## Primary Scope
- Views: `resources/js/views/`
- Components: `resources/js/components/`
- Styling: `resources/css/`
- Router: `resources/js/router/`
- API: axios services

## Project Rules
1. Keep existing UX patterns unless redesign requested.
2. Preserve API contracts with backend.
3. Handle loading, empty, and error states explicitly.
4. Keep forms robust: client validation + backend error mapping.
5. Avoid ad-hoc global state; follow existing local/service patterns.
6. Reuse global button system (`.btn`, `.btn--solid`, `.btn--ghost`).
7. Popups: always visible labels, reuse shared CSS (`.swal-popup-card`, `.swal-card`, etc.).
8. Global HTTP error handling in `api.js` (401, 402, 403, 422, 500).
9. Global banner state in `globalHttpError.js` + `ErrorAlert.vue`.
10. Toast centralized in `toastConfig.js`, styled in `app.css`.

## CRUD UX Baseline
- Enforce states: loading (`AppLoading`), disabled buttons during async, delete confirmations, empty states with business copy
- Empty copy: "No hay pacientes todavía", "No hay citas hoy"
- Prevent double submit: guard clause (`if (submitting) return`), reset in `finally`, disable button in-flight
- Reuse shared primitives: `AppLoading.vue`, `EmptyIndexState.vue`, SweetAlert styles

## Quality Checklist
- Works on desktop and mobile breakpoints.
- No console errors in normal flows.
- Navigation and auth redirects behave correctly.
- Billing-required redirects compatible with app interceptors.

## Useful References
- `resources/js/app.js`, `router/index.js`, `services/api.js`
- `services/toastConfig.js`, `shared/globalHttpError.js`, `shared/httpErrors.js`
- `components/ErrorAlert.vue`, `css/app.css`, `views/BillingRequired.vue`
