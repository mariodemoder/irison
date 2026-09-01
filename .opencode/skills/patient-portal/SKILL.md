---
name: patient-portal
description: Use when working on the Portal del Paciente: clinic-patient auth (forgot/reset/login by clinics.slug), patient branding public routes, patient_portal settings endpoints, or slugs (clinics.slug vs booking_pages.slug). Load before touching patient portal flows.
---

# Skill: Portal del Paciente

## Cuándo usar este skill

Cuando el usuario pregunte o reporte problemas sobre el Portal del Paciente: login/forgot/reset de paciente, branding público (`/api/patient/public/branding/{slug}`), ajustes del portal (`GET/PUT /api/patient-portal/settings`, `GET /api/patient-portal/slug-check`), o los slugs.

## Reglas críticas

### 1. Reset de password es por clinic-patient (no por email)
Un mismo email puede existir en varias clínicas. `Patient::getEmailForPasswordReset()` devuelve `(string) $this->id`, de modo que el token vive en la tabla dedicada `patient_password_reset_tokens.email = patientId` (broker `patients`) y **cada paciente tiene su propio token**. `forgot`/`reset`/`login` escopan por `clinics.slug` (credencial Closure en el broker + `whereHas('clinic', slug)`). La URL del email de reset lleva el **email real** (para pre-rellenar el formulario) + `name={nombre apellido}` (para el saludo), no el patient id. El nombre de la clínica se muestra como **título centrado** en el header de todos los emails a paciente y en los formularios del portal. Sin esto, un email compartido enviaba branding de la clínica equivocada y cambiaba el password del paciente de menor id.

### 2. Clínicas sin `clinics.slug` (NULL) quedan fuera de todo el circuito del portal
No hay backfill automático; se activa asignando el slug desde Servicios → Portal del Paciente. Gate centralizado en `Patient::canUsePortal()` (`status === 'active' && !empty(clinic.slug)`); enforcement en `PatientAuthService` + middleware `patient.auth`.

### 3. Dos slugs independientes por clínica
`clinics.slug` (Portal del Paciente, auto-generado al registrarse en `RegisterController`, editable desde Servicios → Portal del Paciente) y `booking_pages.slug` (Reserva Online, generado en `bootstrapBooking`). **No sincronizar**: son identificadores separados. `clinics.slug` alimenta el branding público (`/api/patient/public/branding/{slug}`), el enlace copiable del portal y el `?clinic=` de los emails de reset al paciente.

## Documentación de referencia

- Detalle completo: `docs/backend/patient-portal.md` (§2, §3, §12, §13).
- Spec original: `docs/specs/patient-portal.md`.
- Guías de paciente: `docs/cliente/pacientes/portal-paciente.md`.
- Tests QA portal: `docs/qa/patient-portal.md`.