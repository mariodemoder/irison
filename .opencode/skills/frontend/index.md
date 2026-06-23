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
