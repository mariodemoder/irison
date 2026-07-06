# Menu Routing — MainLayout.vue

## NavItems

Array plano en `MainLayout.vue:184-198`. Cada item tiene `{ path, label }`.

```js
{ path: '/appointments', label: 'Agenda' },
{ path: '/settings', label: 'Configuración' },
{ path: '/settings/subscription', label: 'Suscripción' },
```

No hay anidamiento explícito. La activación visual se resuelve en tiempo real.

## isActive(item)

En `MainLayout.vue:371`. Recibe el objeto item del menú, no solo el path.

### Reglas

1. **Path exacto** (`p === item.path`) → activo siempre.
2. **Prefijo** (`p.startsWith(item.path + '/')`) → activo SOLO si ningún **otro** item del menú tiene `path` exactamente igual a `p`.
3. **Resto** → inactivo.

### Casos

| Ruta actual | `/appointments` (Agenda) | `/settings` (Config) | `/settings/subscription` (Susc) |
|---|---|---|---|
| `/appointments/day` | ✅ activa (no hay item exacto para `/appointments/day`) | ❌ | ❌ |
| `/settings` | ❌ | ✅ exact match | ❌ |
| `/settings/subscription` | ❌ | ❌ hay item exacto `/settings/subscription` | ✅ exact match |
| `/patients/123` | ❌ | ❌ | ❌ (activa Pacientes) |

### Por qué no usar startsWith simple

Con `p.startsWith('/settings/')`, `/settings/subscription` activaría Configuración. La regla 2 lo impide: si existe un item con `path === p`, el padre no se activa.

### Añadir un nuevo item

- Si su path es subruta de otro item del menú (ej. `/settings/subscription` es subruta de `/settings`), agregarlo al array sin modificar `isActive()` — funciona automáticamente.
- La regla de hijo exacto aplica a **cualquier** item cuya ruta actual coincida exactamente con otro item del menú.
