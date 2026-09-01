# Reserva Online — Guía para Clínicas

| Campo | Valor |
|-------|-------|
| **Version** | 1.0.0 |
| **Fecha** | 01/09/2026 |
| **Estado** | **Implementado — disponible para clínicas** |

---

## 1. Introduccion

La **Reserva Online** es la pagina publica de tu clinica donde los pacientes reservan sus citas por internet, sin instalar nada y sin llamar por telefono.

El enlace publico tiene la forma `https://<dominio-de-tu-clinica>/booking/<identificador>` (por ejemplo `/booking/mi-clinica`). Desde la pagina publica los pacientes pueden:

- Ver los **servicios** que ofreces (duracion y precio)
- Elegir **profesional** (o "Cualquier profesional disponible")
- Escoger **dia y hora** entre los huecos libres (franjas de 15 minutos)
- Introducir sus datos de contacto y confirmar la reserva
- Recibir un **email de confirmacion** con un enlace para **cancelar** la cita si lo necesitan

**Lo que la Reserva Online NO permite:**

- Pagar online (los pagos se gestionan en la clinica)
- Ver informacion medica o datos de la historia del paciente
- Reservar fuera del horizonte configurado (por defecto 60 dias vista)

---

## 2. Flujo del paciente

```
El paciente abre el enlace /booking/<slug>
    |
    v
Elige un servicio (duracion y precio)
    |
    v
Elige profesional  o  "Cualquier profesional disponible"
    |
    v
Elige dia y hora (huecos libres de 15 min)
    |
    v
Introduce nombre, email y telefono
    |
    v
Recibe email de confirmacion (con enlace de cancelacion)
```

> **"Cualquier profesional disponible"**: si el paciente elige esta opcion, la reserva se crea sin profesional asignado y ocupa el hueco en la agenda. Un administrativo de la clinica la asigna manualmente despues (aparece bajo el owner/admin en la agenda).

### Cancelaciones

- El paciente puede cancelar desde el email de confirmacion usando su enlace personal.
- Si la reserva ya ha pasado, no se puede cancelar desde el email; debe contactar con la clinica.
- La clinica gestiona cancelaciones y cambios desde su agenda habitualmente.

---

## 3. Configuracion desde Servicios

Toda la configuracion de la Reserva Online se hace en **Servicios → pestaña "Reserva Online"**. Se organiza en tres sub-pestanas:

### 3.1 Configuracion

| Campo | Que controla | Consejo |
|-------|--------------|---------|
| **URL publica** | El identificador del enlace (`/booking/<identificador>`). Letras minusculas, numeros y guiones; debe ser unico entre todas las clinicas | Elige algo corto y facil de recordar: `/booking/mi-clinica` |
| **Titulo** | El titulo que se muestra en la cabecera de la pagina publica | Por defecto "Reserva tu cita" |
| **Estado** | **Activado** = la pagina publica funciona. **Desactivado** = la pagina no acepta reservas | Mantenlo activado salvo que quieras pausar las reservas online |
| **Horizonte maximo de reserva** | Cuantos dias hacia adelante pueden reservar los pacientes (30, 60, 90 o 180 dias) | Por defecto 60 dias |
| **Politica de cancelacion** | Horas de antelacion exigidas al paciente para cancelar desde el email (1, 12, 24, 48 o 72 horas) | Por defecto 24 horas |

Arriba a la derecha de la pestaña tienes dos botones utiles:

- **Ver pagina publica ↗**: abre la pagina de reserva en una pestana nueva.
- **Copiar enlace publico**: copia el enlace para compartirlo (WhatsApp, email, web, redes sociales).

### 3.2 Servicios

Son los servicios que los pacientes pueden reservar online:

1. Pulsa **Nuevo servicio**.
2. Rellena **Nombre** (por ejemplo "Sesion de fisioterapia").
3. **Tipo de cita**: si seleccionas un tipo de cita existente, se rellenan automaticamente duracion y precio.
4. Ajusta **Duracion** (horas y minutos) y **Precio** si lo necesitas.
5. Deja **Activo** en "Si" para que aparezca en la pagina publica ("No" lo oculta).
6. Guarda con el boton general de la pantalla de Servicios.

> Solo los servicios **activos** aparecen en la pagina publica. Un servicio inactivo no se puede reservar.

### 3.3 Profesionales

Cada profesional del equipo tiene una tarjeta:

- **Online / Offline**: controla si el profesional aparece en la pagina publica. Solo los profesionales con **Online** son reservables.
- **Horario semanal**: define desde/hasta por cada dia (los dias desactivados aparecen con "No"). 
  - Si el profesional usa el horario de Equipo, aparece el aviso **"Usando horario de Equipo"** y los horarios se sincronizan con la pestana de Equipo. Puedes personalizarlos pulsando **Guardar horarios**.
- **Excepciones / Bloqueos**: dias sueltos o rangos de horas en los que el profesional no atiende reservas online (por ejemplo vacaciones o formacion).

---

## 4. Requisitos para la clinica

1. **La cuenta empieza configurada (auto-bootstrap)**: al registrarse, el owner aparece automaticamente como profesional con reserva online activa y horario de lunes a viernes 09:00-17:00 (sabado y domingo desactivados).
2. **Horarios al dia**: los profesionales solo pueden ser reservados en los dias y horas configurados. Si un profesional no tiene horario, no saldra disponible.
3. **Aviso automatico de reserva**: cuando un paciente reserva, los owners de la clinica reciben un email con los datos de la reserva (`NewOnlineBooking`).
4. **Recordatorios automaticos**: las citas reservadas online reciben recordatorios automaticos 24 h y 2 h antes (igual que el resto de citas).
5. **La pagina publica respeta la suscripcion**: si la clinica esta en modo solo lectura (tras el fin del trial o de un periodo pagado cancelado), la reserva online se desactiva y los pacientes no pueden reservar.

---

## 5. Resultado esperado

- **Al configurar**: el enlace publico abre la pagina con el titulo, los servicios activos y los profesionales en linea con sus horarios.
- **Al reservar un paciente**: aparece en la agenda de la clinica, el paciente recibe su email de confirmacion y los owners reciben el aviso.
- **Al desactivar "Online"** a un profesional: desaparece de la pagina publica pero sigue en la agenda interna.
- **Al desactivar Estado (Configuracion)**: la pagina deja de aceptar reservas online.

---

## 6. Errores frecuentes

| Problema | Causa | Solucion |
|----------|-------|----------|
| El profesional no aparece en la pagina publica | Tiene el selector en **Offline**, o no tiene horario configurado | Ponlo en **Online** en Servicios → Reserva Online → Profesionales y revisa su horario |
| La pagina dice que el servicio no esta disponible | El servicio esta inactivo | Activa el servicio en Servicios → Reserva Online → Servicios |
| No hay huecos en un dia concreto | El profesional no trabaja ese dia o esta bloqueado | Revisa el horario semanal y las excepciones/bloqueos |
| El enlace no es unico o da error | El identificador (slug) ya lo usa otra clinica o no es valido | Usa letras minusculas, numeros y guiones; elige otro identificador |
| El paciente no puede cancelar su cita | La cita ya ha pasado, o esta fuera de la politica de cancelacion | Gestiona la anulacion desde la agenda de la clinica |
| Los pacientes no pueden reservar | La clinica esta en modo solo lectura (trial finalizado o suscripcion pausada) | Activa la cuenta de pago en la pantalla de suscripcion |

> **Importante:** el identificador de la Reserva Online (`/booking/<identificador>`) es **distinto** e independiente del del Portal del Paciente (`/patient/login?clinic=<identificador>`). Cambiar uno no afecta al otro.

---

*Ultima revision: 01/09/2026*