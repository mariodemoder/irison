# Protocolo: Botones de acción en listados CRUD (Frontend)

Las clases están definidas en `resources/css/app.css` y son globales. No es necesario agregar CSS local.

## Botón "Añadir" o "Nuevo"

Usar el wrapper `.crud-add-wrap` + `.btn.btn-sm` estándar.

```html
<div class="crud-add-wrap">
  <button class="btn btn-sm" @click="addItem">+ Añadir ítem</button>
</div>
```

| Clase | Función |
|---|---|
| `.crud-add-wrap` | Flex container, `justify-content: flex-end` |
| `.btn.btn-sm` | Botón estándar (padding 6px 12px, borde azul) |

## Botón "Eliminar" (icono papelera)

Usar el componente `<BtnTrash>` dentro de un `.crud-row`.

```html
<div class="crud-row">
  <span>Nombre del elemento</span>
  <span>Detalle opcional</span>
  <BtnTrash @click="deleteItem" />
</div>
```

| Clase | Función |
|---|---|
| `.crud-row` | Flex row, empuja el último hijo a la derecha con `margin-left: auto` |
| `BtnTrash` | Componente que renderiza `<button class="btn btn-sm btn-trash">` |

## Botón "Aceptar/Cancelar" en formularios inline

Usar `.btn.btn-sm.small` (compacto: padding 6px 10px).

```html
<button class="btn btn-sm small" @click="save">✓</button>
<button class="btn btn-sm small warning" @click="cancel">×</button>
```

## Regla general

Todo botón de acción en listados CRUD debe usar `.btn.btn-sm` estándar y alinearse a la derecha mediante `.crud-add-wrap` o `.crud-row`. No forzar paddings ni borders con CSS local.

## Confirmación de borrado

Toda operación de eliminar usa el popup genérico `confirmDelete()` (único estilo de la app, naranja).
Detalle y norma completa: [`docs/frontend/confirmacion-eliminar.md`](confirmacion-eliminar.md)
