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

### 📁 Estructura principal

```text
app/
 ├─ Domain/                # Modelos de dominio (Clinic, Patient, Appointment, User)
 ├─ Http/
 │   ├─ Controllers/
 │   │   └─ Api/           # Controladores REST (API-first)
 │   └─ Middleware/        # EnsureClinic
 ├─ Models/
 │   └─ Scopes/            # ClinicScope
 ├─ Traits/                # BelongsToClinic
 └─ Services/              # Lógica de negocio (futuro)
```

---

## 🔐 Multi-Tenant (núcleo del sistema)

El aislamiento entre clínicas se garantiza mediante **tres capas complementarias**, diseñadas para evitar fugas de datos incluso por error humano.

### 1️⃣ Middleware `EnsureClinic`
- Verifica que el usuario autenticado tenga una clínica asignada
- Inyecta la clínica activa en el contenedor (`app('clinic')`)
- Protege todas las rutas de la API

### 2️⃣ Global Scope `ClinicScope`
- Aplica automáticamente `WHERE clinic_id = ?` a todas las queries
- Evita accesos cruzados entre clínicas

### 3️⃣ Trait `BelongsToClinic`
- Asigna automáticamente el `clinic_id` al crear registros
- Garantiza consistencia incluso en procesos internos

> Resultado: **seguridad por defecto**, sin depender del controlador.

---

## 🔄 API REST

La aplicación está diseñada como **API-first**.

### Recursos disponibles
- `GET /api/patients`
- `POST /api/patients`
- `PUT /api/patients/{id}`
- `DELETE /api/patients/{id}`

- `GET /api/appointments`
- `POST /api/appointments`
- `PUT /api/appointments/{id}`
- `DELETE /api/appointments/{id}`

Todos los endpoints están protegidos por:
- `auth:sanctum`
- `EnsureClinic`

---

## ▶️ Cómo ejecutar el proyecto

1. Clonar el repositorio
2. Instalar dependencias:
   ```bash
   composer install
   ```
3. Configurar el archivo `.env`
   - Base de datos
   - Sanctum
4. Ejecutar migraciones y seeds:
   ```bash
   php artisan migrate --seed
   ```
5. Iniciar el servidor:
   ```bash
   php artisan serve
   ```
6. Probar la API con Postman o Insomnia

---

## 🧠 Decisiones de diseño

- **API-first:** el frontend (Vue) consume la API directamente
- **Multi-tenant por código:** no por base de datos
- **Global scopes:** seguridad estructural
- **Domain-driven:** modelos de negocio separados de infraestructura
- **Preparado para crecer:** facturación, pagos, roles, integraciones

---

## 🏷️ Versionado

- **v0.1** — Semana 1
  - Base multi-tenant estable
  - CRUD de pacientes y citas
  - Arquitectura limpia y documentada

---

## 🚀 Estado del proyecto

🟢 Base sólida lista para continuar con:
- Agenda visual
- Frontend en Vue
- Roles y permisos
- Facturación y pagos

