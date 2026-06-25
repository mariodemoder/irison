# Agenda Diaria — Migración a `<table>` HTML

## Índice

1. [Problema original](#1-problema-original)
2. [Solución: Tabla HTML](#2-solución-tabla-html)
3. [Archivo modificado](#3-archivo-modificado)
4. [Estructura del template](#4-estructura-del-template)
5. [Funciones nuevas en script](#5-funciones-nuevas-en-script)
6. [CSS: cambios clave](#6-css-cambios-clave)
7. [Responsive](#7-responsive)
8. [Gap rows (huecos libres)](#8-gap-rows-huecos-libres)
9. [Casos borde](#9-casos-borde)

---

## 1. Problema original

El layout usaba `div` con `display: grid` separados para:

- `.list-header` (cabecera de columnas)
- `.appointment-row` (cada fila de cita)

Ambos tenían el mismo `grid-template-columns`, pero:

- `border-left-width: 4px` en las filas desplazaba el contenido 4px respecto a la cabecera
- `padding`, `border-radius` y `gap` diferentes entre cabecera y filas rompía la alineación vertical
- En pantallas estrechas el grid se deformaba

## 2. Solución: Tabla HTML

Se reemplazó el sistema de grids por una `<table>` semántica:

- `<thead>` con `<th>` para la cabecera
- `<tbody>` con `<tr>` para cada cita (y cada hueco libre)
- `<td>` para cada celda

Beneficios:

- Alineación perfecta por definición del algoritmo de tabla (`table-layout`)
- Scroll horizontal automático en el wrapper
- Cabecera sticky (<1KB de CSS vs lógica JS)
- Código más mantenible y semántico

## 3. Archivo modificado

`resources/js/views/appointments/AgendaDay.vue`

## 4. Estructura del template

**Antes:**

```html
<div class="list-header">   <!-- grid con 7 divs -->
  <div>Horario</div>
  <div class="row-left">Paciente</div>
  ...
</div>
<div class="list">           <!-- flex column -->
  <div class="appointment-row" :style="appointmentRowStyle(item)">  <!-- grid con 7 divs -->
    <div :class="['row-col','time', ...]">...</div>
    <div class="row-left"><div class="row-name">...</div></div>
    ...
  </div>
</div>
```

**Ahora:**

```html
<div class="list-wrapper">    <!-- overflow-x:auto -->
  <table class="agenda-table">
    <thead>                   <!-- sticky top -->
      <tr>
        <th>Horario</th>
        <th>Paciente</th>
        <th>Profesional</th>
        <th>Notas</th>
        <th>Estado</th>
        <th>Pago</th>
        <th class="action-col"></th>
      </tr>
    </thead>
    <tbody>
      <tr v-if="item._type === 'gap'" class="gap-tr">
        <td colspan="7"><div class="gap-row">...</div></td>
      </tr>
      <tr v-else class="appointment-row" :style="appointmentRowBg(item)">
        <td :style="appointmentBorder(item)" :class="['time', timeClass(item.status)]">...</td>
        <td><div class="row-name">...</div></td>
        <td class="time">...</td>
        <td class="note">...</td>
        <td><span class="status">...</span></td>
        <td><span class="payment-status">...</span></td>
        <td class="row-action"><router-link ... /></td>
      </tr>
    </tbody>
  </table>
</div>
```

## 5. Funciones nuevas en script

La anterior `appointmentRowStyle(item)` devolvía un solo objeto con `backgroundColor` + `borderLeft`. Se partió en dos funciones para aplicar en distintos elementos:

```js
// Se aplica al <tr> — pinta el fondo de toda la fila
function appointmentRowBg(item) {
  const color = item.appointment_type?.color
  return color ? { backgroundColor: color } : {}
}

// Se aplica al primer <td> — la banda lateral de 4px
function appointmentBorder(item) {
  const color = item.appointment_type?.color
  return color ? { borderLeft: '4px solid ' + color } : {}
}
```

**Por qué separadas:** Los navegadores no renderizan `border-left` en `<tr>` con `border-collapse`. Se aplica al `<td>:first-child` que sí lo soporta.

## 6. CSS: cambios clave

| Antes | Ahora | Motivo |
|-------|-------|--------|
| `.list { display:flex; flex-direction:column; gap:8px; overflow-x:auto }` | `.list-wrapper { overflow-x: auto }` | El scroll lo da el wrapper; la tabla maneja el layout |
| `.list-header { display:grid; ... }` | `table.agenda-table th { ... }` | Cabecera nativa de tabla |
| `.appointment-row { display:grid; grid-template-columns: ... ; border-left-width:4px; min-width:920px; border-radius:10px; padding:12px 14px; background:#f8fbfe; ... }` | `tr.appointment-row { cursor:pointer }` + `tr.appointment-row:hover td { background:rgba(0,0,0,0.02) }` | La fila ya no necesita grid; el fondo se aplica vía `:style` |
| `.appointment-row > div { text-align:left }` | `td { text-align:left; vertical-align:middle }` | Más directo |
| `.appointment-row:hover { box-shadow: ...; transform: translateY(-2px) }` | `tr.appointment-row:hover td { background:rgba(0,0,0,0.02) }` | Hover sutil, no interfiere con colores de tipo |
| `.row-col` | Eliminado | Las celdas `<td>` usan `vertical-align:middle` nativo |
| `.row-left` | Eliminado | Ya no se necesita un wrapper flex en celdas de tabla |
| `.row-action { display:flex; ... }` | `td.row-action { text-align:center; width:80px }` | Acción centrada en la celda |
| `@media (max-width:480px) { .appointment-row { grid-template-columns: ... } }` | `@media (max-width:480px) { .agenda-table { min-width:700px } }` | La tabla scrolla horizontalmente en vez de deformarse |

### Estructura completa del nuevo CSS

```
.list-wrapper              → overflow-x: auto
.agenda-table              → width:100%; min-width:920px; border-collapse:collapse
.agenda-table thead        → position:sticky; top:0
.agenda-table th           → padding:10px 8px; font-weight:600; color:#6b7280; border-bottom:2px solid
.agenda-table th.action-col → width:80px; text-align:center
.agenda-table td           → padding:10px 8px; font-size:13px; text-align:left; vertical-align:middle; border-bottom:1px solid
.agenda-table td:first-child → border-left:4px solid transparent; padding-left:12px
tr.appointment-row         → cursor:pointer
tr.appointment-row:hover td → background:rgba(0,0,0,0.02)
```

## 7. Responsive

- **≥920px**: Tabla completa, todas las columnas visibles
- **<920px**: Aparece scroll horizontal en `.list-wrapper` (la tabla tiene `min-width:920px`)
- **<480px**: `min-width` se reduce a 700px para que columnas angostas (Estado, Pago) ocupen menos espacio

No hay cambios de layout ni ocultamiento de columnas por breakpoint — todo scrolla.

## 8. Gap rows (huecos libres)

Los huecos libres (franjas sin citas) se renderizan como `<tr class="gap-tr">` con `colspan="7"` para ocupar todo el ancho. El contenido es el mismo `.gap-row` de antes (verde, con icono, duración y botón "Nueva cita").

El `colspan` debe coincidir siempre con la cantidad de `<th>` en `<thead>`. Si se agrega/elimina una columna, actualizar ambos.

## 9. Casos borde

| Caso | Comportamiento |
|------|---------------|
| Sin citas, con filtros | `<td colspan="7">` con el mensaje "No hay resultados" |
| Sin citas, sin filtros | `<td colspan="7">` con `<EmptyIndexState>` |
| Cita sin color de tipo | `appointmentRowBg` devuelve `{}` → fondo transparente |
| Cita sin profesional | Muestra `clinicOwnerName` (dueño de la clínica) |
| Overlap de citas | Se muestran una debajo de otra en orden de `start_time` |
| Modo "Todos los días" | Misma tabla, misma estructura. La columna Horario incluye fecha. |
| Carga inicial | `<AppLoading>` dentro de `<tbody>` (reemplaza al anterior, que estaba fuera) |
