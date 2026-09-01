# Portal del Paciente — Guía para Clínicas

| Campo | Valor |
|-------|-------|
| **Version** | 1.3.0 |
| **Fecha** | 25/08/2026 (actualizado 30/08/2026) |
| **Estado** | **Implementado — disponible para clínicas** |

---

## 1. Introduccion

El **Portal del Paciente** es una herramienta online que permite a sus pacientes acceder a su informacion de forma segura, desde cualquier dispositivo (ordenador, movil o tablet).

A traves del portal, sus pacientes pueden:

- Ver y gestionar sus citas
- Consultar sus bonos y paquetes contratados
- Revisar su historial de pagos
- Firmar consentimientos informados digitalmente
- Ver documentos compartidos por la clinica
- Recibir notificaciones importantes
- Actualizar sus datos de contacto

**El portal NO permite:**

- Realizar pagos online (los pagos se gestionan en la clinica)
- Ver historial clinico completo
- Acceder a datos de otros pacientes

---

## 2. Capacidades del Portal

### 2.1 Dashboard (Panel Principal)

Al iniciar sesion, el paciente ve un resumen rapido de:

- **Proxima cita**: fecha, hora, profesional y servicio
- **Bonos activos**: numero de sesiones disponibles
- **Pagos pendientes**: importe total pendiente
- **Consentimientos pendientes**: documentos que necesita firmar
- **Notificaciones**: mensajes sin leer de la clinica

### 2.2 Citas

| Accion | Descripcion |
|--------|-------------|
| **Ver proximas citas** | Lista de citas futuras con estado (programada, confirmada) |
| **Ver historial** | Citas pasadas con filtros por fecha, estado y profesional |
| **Solicitar cita** | Enviar una solicitud de cita (la clinica debe confirmarla) |
| **Cancelar cita** | Cancelar una cita con al menos 24 horas de antelacion |
| **Reprogramar cita** | Solicitar cambio de fecha/hora |

> **Nota importante**: Las cancelaciones con menos de 24 horas de antelacion no estan permitidas a traves del portal. En ese caso, el paciente debe contactar directamente con la clinica.

### 2.3 Bonos y Paquetes

El paciente puede ver:

- Todos sus bonos (activos, agotados y expirados)
- Sesiones totales y restantes de cada bono
- Fecha de compra y fecha de caducidad
- Desglose por tipo de servicio (si el bono tiene sesiones por tipo)

### 2.4 Pagos

| Seccion | Contenido |
|---------|-----------|
| **Historial** | Lista de todos los pagos realizados con fecha, concepto, importe y estado |
| **Pendientes** | Pagos que la clinica ha registrado pero aun no estan marcados como completados |

> Los pagos se gestionan manualmente por el personal de la clinica. El portal es solo de consulta.

### 2.5 Consentimientos

- **Pendientes**: consentimientos que el paciente necesita firmar
- **Firmados**: consentimientos ya firmados con fecha y firma digital
- El paciente dibuja su firma en la pantalla y la envia digitalmente
- Una vez firmado, el consentimiento no puede modificarse

### 2.6 Documentos

- Documentos compartidos por la clinica (facturas, recibos, etc.)
- Descarga segura de archivos
- Solo los documentos asignados al paciente son visibles

### 2.7 Notificaciones

Tipos de notificaciones que puede recibir el paciente:

| Tipo | Ejemplo |
|------|---------|
| Cita confirmada | "Su cita del 1 de septiembre ha sido confirmada" |
| Recordatorio | "Le recordamos su cita manana a las 10:00" |
| Consentimiento pendiente | "Tiene un consentimiento pendiente de firma" |
| Pago pendiente | "Tiene un pago pendiente de 45,00 EUR" |
| Cita cancelada | "Su cita del 5 de septiembre ha sido cancelada" |

### 2.8 Perfil

El paciente puede actualizar:

- Nombre y apellidos
- Numero de telefono
- Direccion, codigo postal, ciudad, provincia y pais

**NO puede actualizar** sin aprobacion de la clinica:

- Email
- Fecha de nacimiento
- NIF/DNI

---

## 3. Autenticacion

### 3.1 Como accede el paciente

1. La clinica crea el paciente con un **email valido**
2. Se activa el acceso al portal desde la ficha del paciente (ver manual `pacientes/portal-paciente.md`)
3. La clinica informa al paciente con el enlace de acceso y los pasos de primer acceso (ver 5.5)
4. El paciente pulsa **"He olvidado mi contrasena"** para crear su propia contrasena
5. El paciente accede al portal con su email y la contrasena que ha creado

### 3.2 Restablecer contrasena

Si el paciente olvida su contrasena:

1. Hace clic en "He olvidado mi contrasena" en la pagina de login
2. Introduce su email
3. Recibe un email con un enlace para crear una nueva contrasena
4. Hace clic en el enlace y establece una nueva contrasena
5. Ya puede iniciar sesion con la nueva contrasena

---

## 4. Flujo de Uso

### 4.1 Login y Dashboard

```
Login (email + contrasena)
    |
    v
Dashboard (resumen)
    |
    +-- Proxima cita -> Detalle de cita
    +-- Bonos activos -> Lista de bonos
    +-- Pagos pendientes -> Lista de pagos
    +-- Consentimientos pendientes -> Firmar consentimiento
```

### 4.2 Navegacion Principal

El portal tiene una barra de navegacion inferior (en movil) o lateral (en escritorio) con acceso rapido a:

- **Inicio**: Dashboard con resumen
- **Citas**: Lista de proximas y historial
- **Bonos**: Paquetes y sesiones
- **Perfil**: Datos personales

---

## 5. Requisitos para la Clinica

### 5.1 Crear pacientes con email valido

Para que un paciente pueda acceder al portal, es necesario:

1. Crear el paciente en el sistema con un **email valido y funcional**
2. Activar el acceso al portal del paciente (estado **"Activo"**) desde la ficha del paciente, en la tarjeta **Portal del Paciente** (ver manual `pacientes/portal-paciente.md`)
3. Informar al paciente con el enlace de acceso y los pasos para crear su contrasena (ver 5.5)
4. El paciente crea su contrasena con la opcion "He olvidado mi contrasena" — **el portal no envia emails automaticos de credenciales**

### 5.2 Gestionar solicitudes de cita

Cuando un paciente solicita una cita a traves del portal:

1. La clinica recibe una notificacion
2. Debe **confirmar o rechazar** la solicitud
3. El paciente recibe la confirmacion por email

### 5.3 Compartir documentos

Para que el paciente vea documentos en el portal:

1. Asignar el documento al paciente desde la clinica
2. El documento aparecera automaticamente en el portal del paciente

### 5.4 Marcar pagos como completados

Los pagos aparecen como pendientes hasta que el personal de la clinica los marca como completados. El portal es solo de consulta; no permite realizar pagos.

### 5.5 Como informar al paciente (onboarding al portal)

El portal **no envia credenciales automaticamente**: la clinica es quien comunica al paciente el enlace de acceso y los pasos para entrar por primera vez.

> **Nota de estado:** la activacion/desactivacion del acceso de un paciente se gestiona **desde la clinica**, en la ficha del paciente (tarjeta **Portal del Paciente**). Al desactivar el acceso se cierran las sesiones activas del paciente. Manual completo: `pacientes/portal-paciente.md`.

**Cuando informar al paciente:**

- Justo despues de activar su acceso al portal
- Cuando el paciente no haya entrado nunca (recordatorio a los pocos dias)
- Si se restablece el acceso o se recupera la contrasena

**Modelo de mensaje corto (WhatsApp / SMS):**

```
Hola [Nombre],

Ya puedes acceder a tu area privada de [Nombre de la clinica] para ver tus citas, bonos, pagos, consentimientos y documentos.

Pasos para entrar por primera vez:
1. Entra en: https://<dominio-de-tu-clinica>/patient/login
2. Pulsa "He olvidado mi contrasena" e introduce tu email: [email del paciente]
3. Abre el email que recibiras y pulsa el enlace para crear tu contrasena
4. Inicia sesion con tu email y la contrasena que acabes de crear

Para cualquier duda, contacta con nosotros en [telefono] o [email de la clinica].
```

**Modelo de email formal:**

```
Asunto: Acceso a tu area privada — [Nombre de la clinica]

Hola [Nombre],

Te informamos de que ya puedes acceder a tu area privada del Portal del Paciente
de [Nombre de la clinica].

Que puedes hacer en el portal:
- Consultar y gestionar tus citas (ver proximas, historial, solicitar y cancelar con +24h de antelacion)
- Revisar tus bonos y sesiones disponibles
- Consultar tu historial y pagos pendientes
- Firmar tus consentimientos informados digitalmente
- Ver los documentos que compartimos contigo
- Recibir notificaciones importantes (citas, pagos, consentimientos)

Acceso por primera vez:
1. Entra en https://<dominio-de-tu-clinica>/patient/login
2. Pulsa "He olvidado mi contrasena" y escribe tu email: [email del paciente]
3. Recibiras un email con un enlace para crear tu contrasena
4. Inicia sesion con tu email y tu nueva contrasena

Tu contrasena es personal e intransferible. No la compartas con nadie y cierra
sesion al terminar, especialmente en dispositivos compartidos.

Un saludo,
[Nombre de la clinica] — [telefono] — [email]
```

**Hoja de instrucciones para el paciente** (para entregar en papel o adjuntar):

1. Abra el enlace del portal en su movil, tablet u ordenador (no necesita instalar ninguna aplicacion)
2. Pulsa **"He olvidado mi contrasena"** en la pantalla de acceso
3. Introduzca el email que tiene registrado en la clinica
4. Revise su bandeja de entrada (y la carpeta de spam) y abra el email de Irison
5. Pulsa el enlace del email y escriba la contrasena que quiera usar
6. Vuelva al portal e inicie sesion con su email y su nueva contrasena

**Checklist para la clinica:**

- [ ] El paciente tiene un email valido y funcional en el sistema
- [ ] El acceso al portal esta activado para el paciente (tarjeta **Portal del Paciente** en su ficha)
- [ ] Se ha enviado el mensaje con el enlace y los pasos de primer acceso
- [ ] El paciente confirma que ha podido entrar

---

## 6. Seguridad

### 6.1 Aislamiento de datos

- Cada paciente **solo puede ver sus propios datos**
- Los datos de un paciente **nunca son visibles** para otro paciente
- La informacion esta aislada por clinica

### 6.2 Proteccion de contrasenas

- Las contrasenas se almacenan encriptadas (nunca en texto plano)
- Ni siquiera el personal de la clinica puede ver la contrasena del paciente
- Si un paciente olvida su contrasena, puede restablecerla por email

### 6.3 Sesiones seguras

- El paciente debe **cerrar sesion al terminar**: actualmente las sesiones no expiran automaticamente por inactividad, por lo que es importante cerrar sesion en dispositivos compartidos
- El paciente puede cerrar sesion en cualquier momento
- Cada inicio de sesion es independiente (iniciar sesion en un dispositivo no cierra la sesion de otro)

### 6.4 Consentimientos firmados

- Los consentimientos firmados no pueden ser modificados
- Se almacena la firma digital, la fecha, la hora y la direccion IP
- Se genera un PDF firmado como registro permanente

---

## 7. Preguntas Frecuentes (FAQ)

### Puedo acceder al portal desde el movil?

Si. El portal esta disenado para funcionar en movil, tablet y ordenador. No es necesario instalar ninguna aplicacion.

### Que hago si olvido mi contrasena?

Haga clic en "He olvidado mi contrasena" en la pagina de login. Recibira un email con instrucciones para crear una nueva contrasena.

### Puedo cambiar mi email desde el portal?

No. Si necesita cambiar su email, contacte con la clinica para que actualice sus datos.

### Puedo cancelar una cita con poca antelacion?

Las cancelaciones con menos de 24 horas de antelacion no estan permitidas a traves del portal. En ese caso, contacte directamente con la clinica por telefono.

### Donde veo mis pagos pendientes?

En la seccion "Pagos" del portal vera tanto su historial de pagos como los pagos que estan pendientes de liquidacion.

### Puedo firmar consentimientos desde el movil?

Si. El portal incluye un recuadro de firma digital compatible con pantallas tactiles. Dibuje su firma con el dedo y envie el consentimiento.

### Mis datos estan seguros?

Si. Sus datos estan protegidos con encriptacion y solo usted puede acceder a ellos. La clinica no puede ver su contrasena y sus datos nunca se comparten con otros pacientes.

### Que documentos puedo ver?

Puede ver los documentos que la clinica ha compartido especificamente con usted, como facturas, recibos u otros documentos relevantes.

---

## 8. Soporte

Si tiene problemas para acceder al portal o necesita ayuda:

1. **Problemas de acceso**: Contacte con la clinica directamente
2. **Olvido de contrasena**: Use la opcion "He olvidado mi contrasena"
3. **Errores tecnicos**: Contacte con el soporte tecnico de Irison a traves de soporte@irison.com

---

*Ultima revision: 30/08/2026*
