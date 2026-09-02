# Data Import CSV — Specification (PRO/Enterprise)

| Field | Value |
|-------|-------|
| **Version** | 1.0.0 |
| **Date** | 2026-09-02 |
| **Status** | **Implementado — pruebas pendientes de ejecutar** |
| **Module** | `modules/DataImport/` |
| **Gating** | Planes **PRO y Enterprise** (solapa oculta para Basic) |

> **Estado:** La implementación inicial está completa (backend + frontend + pricing
> + tests creados). **Los tests NO se han ejecutado todavía** y aún faltan los pasos
> de build/verificación listados en la sección 9. Retomamos aquí mañana.

---

## 1. Objetivo

Permitir a la clínica (usuario del SPA Irison) **importar datos en masa desde CSV**
en el plan PRO/Enterprise: pacientes, productos, tipos de sesión (cesiones),
tipos de bono (plantillas), historias clínicas (como cita inicial) e imágenes de
paciente (CSV + ZIP).

Es una funcionalidad 100% del **SPA del suscriptor** (no del backoffice interno
ni del portal del paciente).

## 2. Alcance y decisiones aprobadas

| Decisión | Resolución |
|----------|------------|
| "Bonos" importables | **Tipos de bono (plantillas)** con sus líneas de sesión (`linea_1..linea_9`, formato `TipoSesion|cantidad|precio`). No packs por paciente. |
| Imágenes | **CSV + ZIP**: el CSV referencia nombres de archivo (`imagen_1..imagen_n`) que deben existir en el ZIP. |
| Historias clínicas | Crean la **cita inicial** de cada paciente: `status='completed'`, `price=0`, `booking_source='import'`, historia completa en `appointment.notes` (así la renderiza `ClinicalHistory.vue`). Columna `fecha` opcional (defecto hoy). **1 cita importada por paciente** (idempotencia). |
| Matching de pacientes | Por **NIF o email**; 1 fila = 1 paciente; duplicados se saltan con warning (nunca se duplican). |
| Procesamiento | **Síncrono** con reporte por filas (created/skipped/errors/warnings). |
| Acceso | SPA del suscriptor, **solapa "Importar Datos" dentro de `/settings`** (`Configuration.vue`). No hay ruta nueva ni ítem de menú. |
| Pricing | Añadir "Importación de datos CSV" a las features del plan PRO. |

Fuera de alcance (esta fase): importar packs/bonos por paciente, pagos, documentos,
BD/LDAP, o cualquier importación desde el backoffice interno.

## 3. Arquitectura del módulo

Mini-DDD (`módulo pequeño y focalizado`), sin subagentes:

```
modules/DataImport/
├── Config/
│   └── dataimport.php                  # límites y defaults
├── Domain/
│   ├── Contracts/
│   │   └── ImporterInterface.php       # import(array $rows, array $context): ImportResult
│   ├── Entities/
│   │   └── CsvRow.php                  # fila + get()/first()/hasAny()
│   ├── Exceptions/
│   │   ├── InvalidCsvException.php
│   │   └── InvalidImportHeadersException.php
│   └── Services/
│       ├── CsvParser.php               # BOM, ISO-8859-1→UTF-8, delimitador auto, headers canónicos
│       ├── RowSanitizer.php            # string/int/float(es-ES)/dateYmd/NIF/email/color
│       └── ValidatesImportHeaders.php  # trait ensureHeaders() con alias por columna
├── Application/
│   ├── DTOs/
│   │   └── ImportResult.php            # reporte fluido (errors/warnings por fila)
│   └── UseCases/
│       ├── ImportPatientsCommand.php
│       ├── ImportProductsCommand.php
│       ├── ImportSessionTypesCommand.php
│       ├── ImportBonusTypesCommand.php
│       ├── ImportClinicalHistoriesCommand.php
│       └── ImportPatientImagesCommand.php
├── Infrastructure/
│   ├── Controllers/
│   │   └── ImportController.php        # registry ENTITY_IMPORTERS + TEMPLATES
│   ├── Requests/
│   │   └── ImportCsvRequest.php        # file (CSV) + zip (solo patient-images)
│   ├── Services/
│   │   └── ZipImageExtractor.php       # anti path-traversal, valida extensiones/mime
│   └── Providers/
│       └── DataImportServiceProvider.php
├── Routes/
│   └── api.php                         # POST/GET /api/imports/{entity}[/template]
└── Tests/
    └── Feature/
        └── DataImportTest.php          # 18 tests (creados, NO ejecutados)
```

## 4. Backend — API

### 4.1 Rutas (se registran vía `DataImportServiceProvider`)

```
POST /api/imports/{entity}            -> importar CSV (y ZIP en patient-images)
GET  /api/imports/{entity}/template   -> plantilla CSV descargable (BOM + fila ejemplo)
```

Middleware (igual que Activity/Finance): `auth:sanctum, clinic, check.subscription,
pro.access`. Autorización de rol en el controlador: `owner` o perfil `admin`/`manager`
(patrón `CompanyServicesController::authorizeAccess()`).

`{entity}` válido: `patients`, `products`, `session-types`, `bonus-types`,
`clinical-histories`, `patient-images` (otro → 404).

### 4.2 Contrato de respuestas

| Código | Cuerpo |
|--------|--------|
| `200` | `{ "data": { "entity", "total", "created", "skipped", "errors": [{row,message}], "warnings": [{row,message}] } }` |
| `422` | `{ "message": "..." }` — CSV vacío/ilegible, columnas obligatorias faltantes, límite de filas superado |
| `403` | Sin plan PRO/Enterprise (`pro.access`) o rol sin permisos |
| `404` | Entidad de importación/plantilla desconocida |

Cada importación exitosa registra un `activity_logs` con evento `dataimport.completed`
(tenand_id, user_id, metadata con totales).

### 4.3 Columnas de cada plantilla

| Entidad | Columnas (formato; las filas usan `;` por defecto pero se auto-detecta `,`/tab) |
|---------|------------------------------------------------------------------------------|
| `patients` | `nombre`, `nif`, `email`, `telefono`, `fecha_nacimiento`, `direccion`, `cp`, `poblacion`, `provincia`, `pais`, `observaciones` — **obligatorio al menos NIF o email**; nombre obligatorio; NIF validado por `ValidateNIFFormat`, teléfono por `ValidatePhoneFormat`; dedupe por NIF/email (fichero + DB) |
| `products` | `referencia`, `nombre`, `precio_venta`, `precio_compra`, `iva_venta`, `iva_compra`, `familia`, `lote` — dedupe por referencia |
| `session-types` | `nombre`, `horas_estimadas`, `minutos_estimados`, `precio`, `color` (#RGB/#RRGGBB) — dedupe por descripción; defaults `horas=0`, `minutos=60`, `precio=0` |
| `bonus-types` | `nombre`, `sesiones`, `precio`, `expira_el`, `linea_1..linea_9` (`TipoSesion|cantidad|precio`; sin precio se usa el del tipo de sesión) — dedupe por descripción; error si el tipo de sesión no existe en la clínica |
| `clinical-histories` | `nif_o_email`, `fecha` (opcional, DD/MM/AAAA), `historia` (obligatoria) — crea cita `completed`; idempotente (1 cita `booking_source=import` por paciente) |
| `patient-images` | `nif_o_email`, `imagen_1..imagen_6` — nombres de archivo dentro del ZIP; máx 6 imágenes y 200 KB por paciente (mismas reglas que `PatientImageController`); validación "todo o nada" por fila |

Alias de columnas tolerados por el parser (ver `ValidatesImportHeaders` en cada
UseCase): p.ej. `dni/documento` → nif, `correo` → email, `poblacion/ciudad` → city,
`linea1` → `linea_1`.

### 4.4 Configuración (`config/dataimport.php`)

| Clave | Default | Descripción |
|-------|---------|-------------|
| `max_rows` | 2000 | Máx filas de datos por petición (import síncrono) |
| `max_csv_kb` | 5120 | Tamaño máx del CSV |
| `max_zip_kb` | 10240 | Tamaño máx del ZIP de imágenes |
| `patient_status_default` | `active` | Estado del paciente al importar |
| `clinical_history.default_status` | `completed` | Estado de la cita inicial |
| `clinical_history.booking_source` | `import` | Marcador de origen (idempotencia) |
| `clinical_history.price` | 0 | Precio de la cita inicial |
| `images.max_per_patient` | 6 | Límite de imágenes por paciente |
| `images.max_kb` | 200 | Tamaño máx por imagen |
| `images.allowed_extensions` | jpg/jpeg/png/webp/gif | Extensiones aceptadas |
| `images.allowed_mimes` | image/jpeg/png/webp/gif | Mime válidos (finfo) |

## 5. Frontend

- **Nuevo:** `resources/js/components/imports/ImportDataTab.vue` — 6 tarjetas
  ordenadas: Tipos de sesión → Tipos de bono → Pacientes → Historias clínicas →
  Imágenes → Productos. Cada tarjeta: descarga de **plantilla** (blob), selector
  de CSV (y ZIP en imágenes), botón **Importar** con loading, y informe inline
  (creados/omitidos/errores/avisos por fila). Uso de `api` axios y `useToast`.
- **Editado:** `resources/js/views/Configuration.vue`:
  - Botón de solapa "Importar Datos" con `v-if="showImportTab"` (visible solo
    PRO/Enterprise) — entre "Factura PDF" y "Subscripción".
  - Panel `<ImportDataTab />` con `v-show="activeTab==='importar'"`.
  - La solapa **no** entra en el bloque del `SaveButton` (action-plane) — incluye
    el `v-else` vacío.
- No hay cambios en `router/index.js` ni `MainLayout.vue`.
- En modo solo lectura el interceptor axios de `api.js` bloquea los POST (correcto:
  la importación es una escritura).

## 6. Pricing

- `config/pricing.php` → features del plan `pro`: añadida `'Importación de datos CSV'`.
- `resources/js/components/pricing/FeatureComparisonTable.vue` → fila
  `{ label: 'Importación de datos CSV', basic: false, pro: true, enterprise: true }`.

## 7. Tests (creados, NO ejecutados)

`modules/DataImport/Tests/Feature/DataImportTest.php` — 18 tests siguiendo el patrón
de `modules/Activity/Tests/Feature/ActivityApiTest.php`
(`Tests\TestCase` + `RefreshDatabase` + `withoutMiddleware` para `EnsureClinic`,
`EnsureClinicIsActive`, `CheckSubscriptionAccess`; **`EnsureProAccess` se mantiene**;
clínica `plan='pro'`).

Cubren: import OK por entidad, dedupe (fichero + DB), errores por fila (falta
NIF/email, NIF inválido), idempotencia de historias, imágenes con ZIP (éxito,
archivo ausente, path traversal), columnas faltantes → 422, CSV vacío → 422,
límite de filas → 422, plan basic → 403, recepcionista → 403, entidad desconocida
→ 404, plantilla descargable, activity log.

Helpers de subida (importante): el CSV se sube con un `UploadedFile` real creado
con `tempnam()` + `file_put_contents()` + `new UploadedFile($path, $name, 'text/plain', null, true)`
(**no** `UploadedFile::fake()` — el parser lee el contenido real). El ZIP se crea
con `ZipArchive` real.

## 8. Registros de integración ya aplicados

| Archivo | Cambio |
|---------|--------|
| `bootstrap/providers.php` | `Modules\DataImport\Infrastructure\Providers\DataImportServiceProvider::class` |
| `composer.json` | `"Modules\\DataImport\\Tests\\": "modules/DataImport/Tests/"` en `autoload-dev` |
| `phpunit.xml` | testsuite `DataImport` → `modules/DataImport/Tests` |

## 9. Pendientes para mañana (checklist de retomada)

- [ ] `composer dump-autoload` (para cargar el namespace nuevo de tests/dev).
- [ ] Ejecutar `php artisan test --testsuite=DataImport` y corregir lo que falle.
      Riesgos conocidos a vigilar:
      - Fixtures de NIF: los dígitos de control deben ser correctos (varios
        ya verificados: `12345678Z`, `X1234567L`, `11111111H`, `22222222J`,
        `33333333P`, `44444444A`, `99999999R`).
      - `mimes:csv,txt` / `mimes:zip`: dependen del sniffing de contenido de
        Symfony (texto → `txt`; ZIP → binario `PK`); si falla, revisar el
        helper `csvFile()`.
      - `Getter` de `currentClinicId()`: en tests se inyecta con
        `app()->instance('activeClinic', $clinic)` (patrón ActivityApiTest).
- [ ] `npm run build` y comprobar la solapa "Importar Datos" en `/settings`
      (visible solo con plan PRO/Enterprise) + descarga de plantilla + una
      importación manual de cada entidad en dev.
- [ ] Probar en la clínica de demo: pacientes con duplicados, historia con
      idempotencia, imágenes con ZIP, tipo de bono con línea errónea.
- [ ] (Opcional) Añadir `Importación de datos CSV` también a la página pública
      de precios si hubiera otra fuente (hoy solo `FeatureComparisonTable.vue`).

## 10. Notas y pitfalls

- **Sin librería de CSV**: se usa `fgetcsv` nativo (no hay dependencia en composer).
- `Appointment.payment_status` usa enum `pending|partially_paid|paid|covered_by_pack` —
  la cita inicial usa `pending` (corregido durante implementación; no `unpaid`).
- La importación es por **tenant** (siempre `clinic_id` filtrado); nunca se toca
  `admin_users` ni tablas globales.
- ZipImageExtractor rechaza entradas con `..`, rutas absolutas y directorios
  (solo se procesan basenames; colisión de nombres → gana la última entrada).
- La idempotencia de historias/comparte pacientes con el resto del módulo: no
  hay contadores manuales (el `CounterService` del modelo asigna `counter` al crear).

## 11. Fixtures de demostración y comando artisan

### Archivos de fixtures

Ubicación: `tests/fixtures/import/`

| Archivo | Entidad | Registros | Notas |
|---|---|---|---|
| `patients.csv` | Pacientes | 10 | NIFs con checksum válido (`TRWAGMYFPDXBNJZSQVHLCKE`), emails/teléfonos únicos |
| `products.csv` | Productos | 10 | Referencias `DEMO-REF-001..010`, precios con coma decimal (`35,50`) |
| `session-types.csv` | Tipos de sesión | 10 | Nombres únicos (no collides con tipos existentes), colores `#RRGGBB` |
| `bonus-types.csv` | Tipos de bono | 10 | Líneas referencian session-types del CSV (`TipoSesion\|cantidad\|precio`) |
| `clinical-histories.csv` | Historias clínicas | 10 | Referencian NIFs de `patients.csv` |
| `patient-images.csv` + `.zip` | Imágenes de paciente | 10 | ZIP con 20 PNGs reales (70 bytes c/u, 2 por paciente) |

**Formato**: delimitador `;`, UTF-8, nombres con acentos para probar codificación.

**Orden de dependencia** (debe respetarse al importar):
1. `session-types` → 2. `patients` → 3. `products` → 4. `bonus-types` → 5. `clinical-histories` → 6. `patient-images`

### Plantillas descargables con datos de fixtures

El endpoint `GET /api/imports/{entity}/template` ya no emite una única fila de ejemplo
hardcodeada. Ahora lee el fixture de demostración correspondiente (`TEMPLATE_FIXTURES`
en `ImportController`) y la plantilla descargable incluye **las 10 filas reales** de los
fixtures como datos de ejemplo listos para importar (BOM UTF-8, delimitador `;`).

- Alineación automática: las filas se reordenan según las columnas de `TEMPLATE_COLUMNS`.
- Adecuada para importar: descargar y subir directamente crea los 10 registros demo.
- Si el fixture no existe o no es legible, se devuelve una fila en blanco (fallback) para
  que la plantilla siga siendo descargable.

Comprobado por el test `DataImportTest::test_template_includes_10_fixture_rows`.

### Comando `dataimport:demo`

```bash
# Importar todo contra clínica 7
php artisan dataimport:demo 7

# Importar sin imágenes
php artisan dataimport:demo 7 --skip-images

# Dry-run (solo parsea, no escribe)
php artisan dataimport:demo 7 --dry-run
```

**Comportamiento**:
- Valida que la clínica exista y sea PRO/Enterprise.
- Usa el `owner` de la clínica como user de auditoría.
- Ejecuta los importers en orden de dependencia (misma lógica que `ImportController`).
- Registra 6 eventos `dataimport.completed` en `activity_logs` (visibles en el SPA).
- **Idempotente**: re-ejecutar salta registros existentes (dedup por NIF/referencia/descripción).
- `--skip-images` evita importar imágenes (requiere ZIP).
- `--dry-run` solo parsea los CSVs y valida formato (no escribe).

### Test de fixtures

`modules/DataImport/Tests/Feature/DataImportFixturesTest.php` (7 tests, 129 assertions):
- Tests individuales por entidad (autocontenidos: importan prerequisitos primero).
- Test `test_full_fixture_pipeline_creates_all_entities` ejecuta los 6 importers en orden y verifica totales.

```bash
php artisan test --filter=DataImportFixturesTest
```

### Bug fixes heredados (DataImportTest)

Los asserts del test original usaban `assertSame(N, $response->json('data.errors'))` donde `data.errors` es un **array**, no un entero. Corregidos a `assertCount(N, ...)`. El test de duplicados tenía un email `"-"` que causaba error de validación antes del dedup; corregido a `"juan@example.com"`. Estos tests nunca fueron ejecutados antes (confirmado en §9).