# 🏥 SaaS Multi-Tenant – Gestión de Clínicas

> Base sólida para un SaaS profesional, seguro y escalable, orientado a clínicas y centros de salud.

---

## ✨ Descripción

Este proyecto es la base de un **SaaS multi-tenant para la gestión de clínicas**, diseñado desde el inicio con un enfoque **API-first**, seguro y preparado para escalar sin refactors estructurales.

Cada clínica opera de forma **totalmente aislada**, garantizando que los usuarios solo puedan acceder a los datos de su propia organización. El sistema gestiona entidades clave como **pacientes** y **citas**, y deja preparado el terreno para funcionalidades futuras como agenda avanzada, facturación, pagos e integraciones externas.

Principios clave:
- Seguridad por defecto
- Separación clara de responsabilidades
- Código mantenible y extensible
- Preparado para frontend moderno (Vue)

---

## 🧱 Arquitectura

- **Framework:** Laravel 11
- **Estilo:** API REST
- **Autenticación:** Laravel Sanctum
- **Multi-tenant:** Aislamiento por clínica (`clinic_id`)
- **ORM:** Eloquent
# 🏥 SaaS Multi-Tenant – Gestión de Clínicas

Resumen del estado actual del proyecto (actualizado):

## 🚧 Estado general

- Backend: Laravel (API) con soporte multi-tenant por `clinic_id` (middleware, global scope y traits).
- Frontend: SPA en Vue 3 (views + components) consumiendo la API.
- Desarrollo en marcha: UI básica, CRUD de pacientes, listado paginado y funcionalidad de autenticación con Sanctum.

## ✅ Funcionalidades implementadas relevantes

- Pacientes
  - Listado moderno en `resources/js/views/patients/Index.vue`: búsqueda local, fila de títulos, acciones separadas por fila (`Historial`, `Datos`).
  - Formulario de paciente en `resources/js/views/patients/Form.vue`: creación y edición (misma vista para ambos modos).
  - Validación de `nif`: `nullable` o único. En creación/actualización la API responde con 409 y `{ existing: { id } }` cuando el NIF ya existe para otro paciente — el frontend muestra un aviso con opción “Ir al paciente existente”.
  - Enrutado: `/patients` (lista), `/patients/create` (nuevo), `/patients/:id` (ficha / show), `/patients/:id/edit` (editar).

- Ficha de paciente (`Show.vue`)
  - Muestra datos principales (nombre, NIF, teléfono, email, notas).
  - Secciones históricas: `Citas`, `Bonos`, `Pagos` como cards; cuando están vacías se muestran placeholders visuales.
  - Botón “Editar” que entra al formulario en modo edición.

- API
  - `GET /api/patients` paginado (meta + data)
  - `GET /api/patients/{id}` ahora garantiza devolver relaciones como arrays (posiblemente vacíos): `appointments`, `packs`, `payments`, `clinical_records` (future-proof).
  - `POST /api/patients` y `PUT /api/patients/{id}` con validaciones y manejo claro de errores (422 para validación, 409 para NIF duplicado con payload que indica el id existente).

## 🧩 UX / Frontend details

- Barra lateral (`MainLayout.vue`) ahora es sticky (permanece visible al hacer scroll).
- Lista de pacientes: acciones `Historial` (va a `Show.vue`) y `Datos` (va al formulario en modo edición con query `from=list`).
- El formulario respeta `route.query.from` para que el botón `Cancelar` vuelva al origen correcto (`list` o `show`).
- Manejo de errores mejorado en `Form.vue`: muestra errores 422, 409 y errores generales.

## 🗂 Migraciones / DB

- Se añadió columna `nif` nullable en la tabla `patients` y un índice único (migraciones en `database/migrations/2026_*`). Ejecuta `php artisan migrate` si no lo hiciste.

## 🛠 Cómo ejecutar (recordatorio rápido)

1. Instala dependencias PHP y JS:
   ```bash
   composer install
   npm install
   ```
2. Configura `.env` y la DB
3. Ejecuta migraciones:
   ```bash
   php artisan migrate
   ```
4. Inicia backend y dev frontend (si usas Vite):
   ```bash
   php artisan serve
   npm run dev
   ```

> Nota: durante desarrollo Vite puede mostrar errores WebSocket para HMR si la configuración de host no coincide con `localhost`/`127.0.0.1`. Eso no impide las llamadas API; para eliminar los warnings ajusta `vite.config.js` o inicia Vite con `--host`.

## 🔜 Próximos pasos sugeridos

- Paginación/filtrado del lado del servidor para la búsqueda (actualmente es local sobre la página cargada).
- Implementar creación directa de `Citas` / `Bonos` desde la ficha del paciente (modales o rutas dedicadas).
- Añadir conteos y paginación en relaciones grandes (appointments/packs/payments).
- Tests automatizados para endpoints críticos (nif único, multi-tenant scope).

---

Si quieres, actualizo el README con más detalles (comandos útiles, endpoints completos o screenshots del frontend). ¿Deseas que agregue instrucciones rápidas para desarrollo con Docker o scripts locales?
   - Base de datos

   - Sanctum
