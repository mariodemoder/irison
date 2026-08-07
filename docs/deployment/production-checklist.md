# Production Checklist (MVP Seguro)

Checklist operativo para preparar y desplegar Irison en producción con riesgo controlado.

## 1) Pre-Deploy

### 1.1 Entorno

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` y `APP_FRONTEND_URL` con dominio HTTPS real
- [ ] `APP_TIMEZONE=Europe/Madrid`
- [ ] `APP_KEY` definida

### 1.2 Base de datos

- [ ] `DB_CONNECTION` correcto (`pgsql` recomendado)
- [ ] `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` reales de producción
- [ ] Conectividad validada a la base

### 1.3 Mail

- [ ] `MAIL_MAILER` real (`smtp`, `ses`, `postmark`)
- [ ] `MAIL_FROM_ADDRESS` con dominio real
- [ ] SPF/DKIM del dominio verificados
- [ ] Prueba de envío realizada

### 1.4 Stripe y billing

- [ ] `STRIPE_KEY` live (`pk_live_...`)
- [ ] `STRIPE_SECRET` live (`sk_live_...`)
- [ ] `STRIPE_WEBHOOK_SECRET` real (`whsec_...`)
- [ ] `STRIPE_PRICE_ID` válido
- [ ] `BILLING_PROVIDER=stripe`
- [ ] Webhook apuntando a `POST /api/billing/webhook` (webhook único; se verifica firma en `StripeWebhookHandler`)

### 1.5 Queue y scheduler

- [ ] `QUEUE_CONNECTION` validado
- [ ] Worker con Supervisor configurado y activo
- [ ] Cron instalado para `php artisan schedule:run` cada minuto

## 2) Deploy

```bash
cd /var/www/Irison
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## 3) Post-Deploy Checks

- [ ] `GET /up` responde 200
- [ ] Login responde códigos esperados (422/401 en credenciales inválidas)
- [ ] `POST /api/billing/webhook` responde error de firma inválida (no 401)
- [ ] `php artisan schedule:list` muestra tareas esperadas
- [ ] `supervisorctl status` muestra workers `RUNNING`
- [ ] Sin errores críticos en logs

## 4) Comandos de validación rápida

```bash
php artisan schedule:list
php artisan migrate:status
php artisan tinker
```

Dentro de tinker:

```php
DB::table('jobs')->count();
DB::table('failed_jobs')->count();
```

## 5) Rollback rápido

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan queue:restart
```

Si hubo migraciones problemáticas:

```bash
php artisan migrate:rollback --force
```

## 6) Bitácora mínima por despliegue

- Fecha/hora
- Autor
- Entorno
- Cambios aplicados
- Resultado
- Rollback aplicado (sí/no)
