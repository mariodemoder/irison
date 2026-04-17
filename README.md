# 🏥 Irison – SaaS Multi-Tenant para Clínicas

Aplicación web para gestión de clínicas con arquitectura multi-tenant por `clinic_id`, backend Laravel API y frontend Vue 3.

## 🚀 Stack

- Backend: Laravel 11
- Frontend: Vue 3 + Vite
- Autenticación: Laravel Sanctum
- Base de datos: PostgreSQL (desarrollo) / SQLite in-memory (tests)

## ✅ Estado actual (resumen)

- CRUD principal de pacientes y citas.
- Gestión de pagos, bonos y crédito.
- Aislamiento por clínica en capa de aplicación.
- Vistas SPA en `resources/js/views`.

## 🛠 Instalación local

1. Instalar dependencias:

```bash
composer install
npm install
```

2. Crear y configurar entorno:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configurar credenciales de base de datos en `.env`.

Ejemplo para PostgreSQL local:

```bash
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=irison
DB_USERNAME=postgres
DB_PASSWORD=tu_password
```

4. Ejecutar migraciones:

```bash
php artisan migrate
```

5. Levantar entorno de desarrollo:

```bash
php artisan serve
npm run dev
```

## 🧪 Tests (entorno seguro)

Los tests están aislados para no tocar datos reales:

- Archivo de entorno: `.env.testing`
- DB de tests: `sqlite` en memoria (`:memory:`)

Comandos útiles:

```bash
php artisan test
php artisan test --filter=ExampleTest
vendor\bin\phpunit
```

## 📁 Estructura relevante

- API y dominio: `app/`
- Rutas: `routes/`
- Vistas frontend: `resources/js/views/`
- Componentes frontend: `resources/js/components/`
- Migraciones: `database/migrations/`
- Tests: `tests/`

## 📝 Notas

- Si haces cambios de esquema, ejecuta `php artisan migrate`.
- Si cambias frontend, reinicia `npm run dev` si Vite no refleja cambios.

## ☁️ Despliegue Linux/Cloud

- Guía principal de servidor: `docs/deployment/linux-cloud.md`
- Toda modificación de comandos Linux, configuración de servicios, permisos, SSL, colas, cron, storage o despliegue debe registrarse en la sección **Bitácora de cambios** de ese documento.
