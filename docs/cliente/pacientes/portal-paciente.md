# Gestionar el acceso de un paciente al Portal del Paciente

| Campo | Valor |
|-------|-------|
| **Version** | 1.1.0 |
| **Fecha** | 30/08/2026 |
| **Audiencia** | Clinica (owner, admin, gestor, recepcion) |

---

## Para que sirve

El **Portal del Paciente** permite a tus pacientes consultar online sus citas, bonos, pagos, consentimientos y documentos (guia completa: `../patient-portal.md`). Para que un paciente pueda entrar, primero debes **activar su acceso** desde su ficha.

Tambien puedes **desactivarlo** cuando quieras (por ejemplo, si el paciente se da de baja o el email quedo obsoleto): al desactivar, sus sesiones activas se cierran y deja de poder entrar.

---

## Quien puede hacerlo

Cualquier persona del equipo con acceso operativo a la aplicacion (owner, admin, gestor y recepcion).

> **Nota:** los profesionales con perfil de solo consulta no ven los controles de activacion/desactivacion.

---

## Antes de empezar

El paciente debe tener un **email valido y funcional** en su ficha. Si el email es incorrecto o inaccesible, el paciente no podra crear su contrasena (la creacion se hace por email con la opcion "He olvidado mi contrasena").

---

## Pasos

### Activar el acceso

1. Ve a **Pacientes** y abre la ficha del paciente.
2. Localiza la tarjeta **Portal del Paciente**.
3. Pulsa **Activar acceso**.
4. El estado cambia a **Acceso activo**.

### Informar al paciente

5. Envia al paciente el **enlace de acceso** de su portal: `https://<dominio-de-tu-clinica>/patient/login?clinic=<slug-de-la-clinica>` (el enlace aparece en la tarjeta **Portal del Paciente**, con boton para copiarlo). Incluye `?clinic=...` para que el portal muestre el nombre/logo de la clinica antes de iniciar sesion.
6. El paciente crea su contrasena con **"He olvidado mi contrasena"**: el portal **no envia credenciales automaticamente**. El email de creacion de contrasena llega con el nombre de la clinica como remitente y enlaza directo al portal.

---

## Configurar la URL del portal (slug)

El enlace del portal incluye un identificador propio de tu clinica (el `slug`, por ejemplo `clinica-portal-test`). Ese identificador se genera automaticamente al registrar la clinica, pero puedes **cambiarlo** cuando quieras desde **Servicios → Portal del Paciente**:

1. Ve a **Servicios** y abre la pestana **Portal del Paciente**.
2. Revisa el campo **URL del portal**. Si la clinica aun no tenia identificador, aparece una sugerencia (basada en el nombre) que puedes editar.
3. Escribe el identificador que prefieras (letras minusculas, numeros y guiones; debe ser unico entre todas las clinicas).
4. Pulsa **Guardar** (el boton general de la pantalla de Servicios).

Al guardar, el enlace real del portal (el que ves y copias en la ficha del paciente) cambia al nuevo identificador. Comparte siempre el enlace actualizado con tus pacientes.

> **Importante:** el identificador de la Reserva Online (`/booking/<slug>`) es **distinto** e independiente de este. Cambiar el del portal no afecta a la reserva online y viceversa.

### Desactivar el acceso

1. Abre la ficha del paciente → tarjeta **Portal del Paciente**.
2. Pulsa **Desactivar acceso** y confirma.
3. El estado cambia a **Acceso inactivo** y las sesiones abiertas del paciente se cierran.

### Verificar

- **Estado del acceso**: activo o inactivo, visible en la tarjeta.
- **Ultima conexion**: fecha y hora del ultimo inicio de sesion (o "El paciente aun no ha entrado").
- Puedes pedir al paciente que confirme que ha podido entrar.

---

## Resultado esperado

- **Al activar**: el paciente puede entrar en `https://<dominio-de-tu-clinica>/patient/login?clinic=<slug>` con su email y una contrasena que el mismo crea.
- **Al desactivar**: el paciente deja de poder entrar de inmediato (sus sesiones se cierran en todos los dispositivos).
- La activacion y la desactivacion quedan registradas en el historial de actividad de la clinica.

---

## Errores frecuentes

| Problema | Causa | Solucion |
|----------|-------|----------|
| El paciente no recibe el email para crear contrasena | Email incorrecto en la ficha, o el email cayo en spam | Comprueba el email en la ficha; revisa la carpeta de spam; corrige el email si es necesario |
| El paciente no puede entrar al portal | Su acceso esta inactivo | Activa el acceso desde la ficha del paciente |
| El portal dice "usuario o contrasena incorrectos" | La contrasena introducida no es la del paciente | El paciente usa "He olvidado mi contrasena" para crear una nueva |
| Quieres que un paciente deje de entrar de inmediato | El paciente tiene sesiones abiertas | Desactiva el acceso: se cierran todas sus sesiones |
| El paciente quiere cambiar su email de acceso | El email no se cambia desde el portal | Actualiza el email en su ficha; si el acceso estaba activo, sigue activo con el nuevo email |

---

*Ultima revision: 31/08/2026*