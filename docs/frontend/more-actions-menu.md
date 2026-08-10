# Protocolo: Menú de acciones "⋮" (MoreActionsMenu)

Componente reutilizable para agrupar 2+ acciones por entidad en un menú desplegable.
Sustituye los patrones locales que existían antes (sub-menu con `&#8942;` y
quick-actions con SVG de 3 círculos).

## Componente

`resources/js/components/MoreActionsMenu.vue` — registrado globalmente en
`resources/js/app.js` (no requiere import en cada vista).

## API

| Prop | Tipo | Default | Descripción |
|---|---|---|---|
| `align` | `'right' \| 'left'` | `'right'` | Lado donde se abre el menú respecto al trigger |
| `disabled` | `Boolean` | `false` | Deshabilita el trigger |
| `ariaLabel` | `String` | `'Acciones'` | `aria-label` del trigger y del menú |
| `closeOnItemClick` | `Boolean` | `true` | Cierra el menú al hacer click en un item |
| `triggerClass` | `String` | `''` | Clases extra para el botón trigger |

Slots:

- **default** — items del menú. Cada item es un `<button class="ma-item">`
  (o `class="ma-item ma-item--danger"` para acciones destructivas).
- **#trigger** — reemplaza el trigger por defecto (SVG 3 círculos).

Comportamiento:

- Cierra con click fuera (listener global `document`) y con `Escape` (devuelve el
  foco al trigger).
- El cierre por click en un item se escucha **en fase de captura**, así que
  funciona incluso en items con `@click.stop` (necesario en filas clickables).
- El trigger usa `@click.stop` internamente: en filas de tabla clickables no hace
  falta propagar, y el click en el trigger no dispara la navegación de la fila.

## Clases útiles

- `.ma-item` / `.ma-item--danger` — items del menú.
- `.more-actions--push-right` — empuja el menú a la derecha (`margin-left: auto`).
- Trigger por defecto (`.ma-trigger--default`) — cuadrado de 32px, usado en filas.
- En cabeceras con botón "Volver", usar
  `trigger-class="muted quick-trigger menu-right-btn"` para la píldora discreta.

## Uso

```html
<MoreActionsMenu aria-label="Acciones del gasto">
  <button type="button" class="ma-item" @click="openExpenseModal(expense)">Editar</button>
  <button type="button" class="ma-item ma-item--danger" @click="removeExpense(expense)">Eliminar</button>
</MoreActionsMenu>
```

En filas clickables (rol `button` en el `<tr>`), los items deben usar
`@click.stop` para no disparar la navegación de la fila:

```html
<MoreActionsMenu aria-label="Acciones del documento">
  <button type="button" class="ma-item" @click.stop="previewPdf(doc)">Vista previa PDF</button>
  <button type="button" class="ma-item" @click.stop="downloadPdf(doc)">Descargar PDF</button>
</MoreActionsMenu>
```

## Dónde se usa

Vistas Show (header; las acciones viven aquí, nunca en las tablas):

- `resources/js/views/patients/Show.vue` — Editar, Historia Clínica, Imágenes, Eliminar.
- `resources/js/views/products/Show.vue` — Editar, Eliminar.
- `resources/js/views/payments/Show.vue` — Editar (los pagos no se eliminan).
- `resources/js/views/appointments/Show.vue` — Editar, Reprogramar, Cancelar Cita.
- `resources/js/views/invoices/Show.vue` — Ver PDF, Descargar PDF, acción de documento (read-only).
- `resources/js/views/notifications/Show.vue` — Reenviar (log, sin editar/eliminar).
- `resources/js/views/team/Team.vue` — Profesiones.
- `resources/js/views/Configuration.vue` — Cancelar suscripción, Consultar pago, Legales.

Tablas de listado **fuera de alcance** (sin vista Show todavía):

- `resources/js/views/bonuses/Index.vue` — PDF / Facturar (con ⋮ `MoreActionsMenu`).
- `resources/js/views/finance/Index.vue` — Editar / Eliminar (gastos; botones directos `EditButton` + `BtnTrash`, sin ⋮).
- `resources/js/views/consents/ConsentTemplatesIndex.vue` — Editar / Eliminar (botones directos `EditButton` + `BtnTrash`, sin ⋮).

## Regla general

1. **Las tablas de entidades no llevan botones de acción** (ni Editar, ni Eliminar, ni acciones rápidas, ni ⋮).
2. Clic en cualquier parte de la fila → navega al **modo Show** (`role="button"` + `@click` + `@keydown.enter`).
3. Todas las acciones viven en el **⋮ del header del Show** (`MoreActionsMenu` con `trigger-class="muted quick-trigger menu-right-btn"` dentro de `.back-menu-group`, tras "Volver").
4. El ⋮ del Show incluye **Editar** y **Eliminar** como items, respetando los permisos que tenían esas acciones (`isProfessional`/`isFullAccess`/estado de la entidad). Si una entidad no tiene editar/eliminar (facturas, notificaciones), solo muestra sus acciones reales.
5. **Eliminar se muestra como texto rojo** (`<button class="ma-item ma-item--danger">Eliminar</button>`) — sin icono de papelera (`BtnTrash` queda solo para filas de formularios inline).
