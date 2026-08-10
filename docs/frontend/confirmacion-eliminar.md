# Protocolo: Confirmación de borrado (popup genérico)

> **NORMA OBLIGATORIA del frontend.** Toda operación de borrado en la app DEBE
> pedir confirmación usando el popup genérico `confirmDelete()`.
> No se crean popups de confirmación de borrado nuevos, ni estilos propios por vista.

## Regla

1. **Todo borrado lleva confirmación.** Si una acción elimina datos de forma
   persistente (cualquier `api.delete`, revocar consentimiento, etc.), primero
   se muestra `confirmDelete()` y solo si el usuario confirma se ejecuta.
2. **Un único estilo.** El popup de borrado tiene un único diseño en toda la
   app: tarjeta naranja de advertencia, icono naranja, botón "Sí, eliminar"
   naranja y botón "Cancelar" outline. No se definen clases ni variantes en
   `<style>` de las vistas para este popup.
3. **No usar `confirm()` nativo del navegador** ni `Swal.fire` directo para
   confirmaciones de borrado.

## Cómo usarlo

Importar el helper y envolver el borrado:

```js
import { confirmDelete } from '../../shared/confirmDelete'

async function removeExpense(expense) {
  const confirmed = await confirmDelete({
    title: 'Eliminar gasto',
    text: `¿Eliminar el gasto "${expense.concept}"? Esta acción no se puede deshacer.`,
  })
  if (!confirmed) return

  // ... api.delete + toast + recarga
}
```

### Firma

`confirmDelete({ title, text, confirmButtonText?, cancelButtonText?, iconColor? }) → Promise<boolean>`

| Parámetro | Tipo | Default | Descripción |
|---|---|---|---|
| `title` | `string` | `'Eliminar'` | Título del popup (p.ej. `'Eliminar usuario'`) |
| `text` | `string` | `'Esta acción no se puede deshacer.'` | Mensaje. Incluir el nombre del elemento y la advertencia de irreversibilidad, salvo excepción real (p.ej. paciente = soft delete reversible) |
| `confirmButtonText` | `string` | `'Sí, eliminar'` | Texto del botón de confirmar. Ajustar por acción (p.ej. `'Sí, revocar'`) |
| `cancelButtonText` | `string` | `'Cancelar'` | Texto del botón de cancelar |
| `iconColor` | `string` | `'#f97316'` | Color del icono de advertencia (no cambiar sin motivo) |

Devuelve `true` si el usuario confirmó, `false` si canceló.

### Archivos

- Helper: `resources/js/shared/confirmDelete.js`
- Estilos globales: `resources/css/app.css` (`.swal-popup-warning-card`,
  `.app-btn-warning`, `.app-btn-muted`, `.swal-actions`) — **no duplicar en vistas**.

## Checklist para futuras funcionalidades de eliminar

Cuando añadas una funcionalidad de borrado:

- [ ] La acción de borrado llama a `confirmDelete()` antes de ejecutar el `api.delete` (o la acción destructiva correspondiente).
- [ ] Se importa `confirmDelete` desde `../../shared/confirmDelete` (ajustando la ruta según la profundidad).
- [ ] No se usa `confirm()` nativo ni `Swal.fire` para la confirmación.
- [ ] No se añade CSS propio del popup en la vista.
- [ ] Si la acción no es un borrado literal (p.ej. revocar, desactivar), se pasa `confirmButtonText` acorde.
- [ ] Se respetan las validaciones de negocio previas (p.ej. no eliminar bono facturado, permisos `isFullAccess`/`isProfessional`).

## Dónde NO aplica (excepciones)

- Borrados locales de formularios sin guardar (líneas de factura, filas de
  adjuntos, excepciones de horario, servicios de booking pendientes de guardar):
  no son borrados persistentes y NO llevan popup.
- Resets de marca en Configuración (logo, fondo de factura): se mantienen sin popup.
- "Cancelar cita": no es un borrado; conserva su propia confirmación
  (`appointmentHelpers.js confirmAndCancel`).

## Uso actual

Migrados al popup genérico: pacientes (Index y Show), plantillas de
consentimientos, usuarios y profesiones (Equipo), productos, bonos
(PatientBonuses), tipos de sesión y tipos de bono **persistidos**
(Servicios de empresa — el borrado real se aplica al guardar; los
borradores locales sin guardar se eliminan de la lista sin popup, como
el resto de borrados locales), gastos y categorías (Finanzas), revocar
consentimiento (PatientConsents).
