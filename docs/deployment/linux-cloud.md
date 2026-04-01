# Linux/Cloud Deployment Guide

Esta guía es la referencia oficial para desplegar Dueleahi en servidores Linux/Cloud.

## Objetivo

- Estandarizar despliegues.
- Reducir errores manuales.
- Dejar trazabilidad de cambios operativos.

## Alcance

Incluye:

- Comandos Linux de instalación y operación.
- Configuración de Nginx/Apache, PHP, DB y permisos.
- SSL, cron, colas y procesos.
- Manejo de `storage` y archivos públicos.

## Requisitos base (Ubuntu/Debian)

- PHP 8.2+ con extensiones necesarias para Laravel.
- Composer 2.x.
- Node.js 20+ y npm.
- MySQL 8+ o MariaDB equivalente.
- Nginx (recomendado) o Apache.
- Supervisor (para workers de cola).

## Variables de entorno

Configurar al menos:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://tu-dominio.com`
- `APP_TIMEZONE=Europe/Madrid`
- Variables de DB, mail y servicios externos.

## Flujo de deploy recomendado

1. Actualizar código

```bash
cd /var/www/dueleahi
git pull origin main
```

2. Dependencias backend/frontend

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

3. Laravel setup

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. Permisos

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

5. Reinicio de servicios

```bash
php artisan queue:restart
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart all
sudo systemctl reload nginx
```

## Nginx (plantilla mínima)

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    root /var/www/dueleahi/public;

    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

## Scheduler y Queue

### Cron (Scheduler)

```bash
* * * * * cd /var/www/dueleahi && php artisan schedule:run >> /dev/null 2>&1
```

### Supervisor (queue worker)

```ini
[program:dueleahi-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dueleahi/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/dueleahi/storage/logs/worker.log
```

## Storage y miniaturas

- Las imágenes de paciente se guardan en:
  - `storage/app/public/patients/{patient_id}/images`
- URL pública esperada:
  - `/storage/patients/{patient_id}/images/...`
- Requisito:
  - Ejecutar `php artisan storage:link`

## Operación diaria

- Revisar logs:

```bash
tail -f storage/logs/laravel.log
```

- Revisar workers:

```bash
sudo supervisorctl status
```

- Reiniciar colas tras deploy:

```bash
php artisan queue:restart
```

## Bitacora de cambios

Toda modificación operativa Linux/Cloud debe registrarse aquí.

Formato sugerido:

```md
### YYYY-MM-DD HH:MM (TZ)
- Autor: <nombre>
- Entorno: <staging|production>
- Cambio: <descripcion breve>
- Archivos/config tocados: <paths>
- Comandos ejecutados:
  - `comando 1`
  - `comando 2`
- Resultado: <ok/fallo + resumen>
- Rollback: <si/no + como>
```

### 2026-04-01 00:00 (Europe/Madrid)
- Autor: Equipo
- Entorno: Documentacion
- Cambio: Se crea esta guia base de Linux/Cloud y se define bitacora obligatoria.
- Archivos/config tocados: `README.md`, `docs/deployment/linux-cloud.md`
- Comandos ejecutados:
  - `N/A`
- Resultado: OK
- Rollback: No aplica

### 2026-04-01 00:10 (Europe/Madrid)
- Autor: Equipo
- Entorno: Local (Windows)
- Cambio: Se crea enlace publico de storage para servir archivos desde `/storage`.
- Archivos/config tocados: `public/storage` (symlink), `storage/app/public`
- Comandos ejecutados:
  - `php artisan storage:link`
- Resultado: OK (exit code 0)
- Rollback: `php artisan storage:unlink` (si se requiere recrear enlace)
