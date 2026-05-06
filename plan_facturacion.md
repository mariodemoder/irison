# 📋 Plan de Implementación: Pago Completo Stripe + Suscripciones

## 🔍 ESTADO ACTUAL

### ✅ Backend (Completado 60%)
- **BillingController**: Tiene endpoints funcionales
  - POST /billing/checkout → Crea BillingPayment + Checkout con proveedor
  - POST /billing/webhook → Maneja webhooks del proveedor
  - POST /billing/confirm → Valida sesión Stripe y actualiza suscripción
  - POST /billing/fake-success → Para testing local

- **Modelos**:
  - BillingPayment: campos bien definidos (clinic_id, amount, currency, status, provider, provider_ref)
  - Subscription: Modelo simplificado (status, trial_ends_at, current_period_end, stripe_customer_id, stripe_subscription_id)

- **PaymentProvider**:
  - Interfaz: PaymentProviderInterface
  - Implementaciones: FakePaymentProvider, StripePaymentProvider
  - Resolver: Resuelve automáticamente según config

### ❌ Frontend (Completado 10%)
- **Ausente**: Componente de checkout con Stripe Elements
- **Ausente**: Integración de Stripe.js en Vue
- **Ausente**: Captura de tarjeta y envío de payment_method
- **Ausente**: Flujo visual de pago

### ❌ Testing (Completado 0%)
- No hay tests del flujo completo usuario → clínica → suscripción

---

## 📅 PLAN AJUSTADO

### 🟢 VIERNES (1.5h) — Frontend + Integración Stripe

#### Bloque 1: Preparación (30 min)
1. Crear componente StripeCheckout.vue en resources/js/views/billing/
2. Instalar Stripe.js: npm install @stripe/stripe-js
3. Configurar Stripe en env (stripe_publishable_key)

#### Bloque 2: Componente (1h)
1. Integrar Stripe Elements (Card element)
2. Capturar tarjeta al hacer submit
3. Crear payment_method con Stripe.js
4. Enviar payment_method + monto al endpoint /billing/confirm-payment

**Resultado**: ✔️ Usuario ve formulario de tarjeta y puede capturar datos

---

### 🟡 SÁBADO (3h) — Flujo Completo + Testing

#### Bloque 1: API completa (1.5h)

**Nuevo endpoint: POST /billing/confirm-payment**
- Recibe: payment_method, amount
- Valida payment_method con Stripe
- Crea o actualiza BillingPayment con status 'paid'
- Crea/actualiza Subscription con status 'active'
- Retorna: { status: 'active', subscription_id }

**Test E2E (con seeders)**:
- Crear usuario + clínica
- Hacer POST /billing/checkout con amount
- Capturar payment_method fake
- Hacer POST /billing/confirm-payment
- Verificar:
  ✔️ BillingPayment.status = 'paid'
  ✔️ Subscription.status = 'active'
  ✔️ clinic.subscribed_at = now()
  ✔️ clinic.subscription_provider = 'stripe'

#### Bloque 2: Estados coherentes (1.5h)

**Actualizar subscription_status**:
- Agregar método a Clinic: subscriptionStatus(): string
- Posibles valores: 'active', 'trial', 'expired', 'canceled'
- Lógica:
  `
  trial → if trial_ends_at > now() and status = 'trial'
  active → if status = 'active' and current_period_end > now()
  expired → if current_period_end <= now() and status != 'canceled'
  canceled → if status = 'canceled'
  `

**Verificar coherencia en tabla**:
- phpunit test que valide transiciones de estado
- Verificar que solo una suscripción puede estar 'active' por clínica

---

## 📊 CHECKLIST

### Viernes
- [ ] Crear StripeCheckout.vue
- [ ] Instalar @stripe/stripe-js
- [ ] Renderizar Card element
- [ ] Implementar captura de payment_method
- [ ] Enviar al backend

### Sábado Bloque 1
- [ ] Endpoint /billing/confirm-payment
- [ ] Crear BillingPayment con payment_method
- [ ] Actualizar/crear Subscription
- [ ] Test E2E completo

### Sábado Bloque 2
- [ ] Método subscriptionStatus() en Clinic
- [ ] Migraciones si faltan campos
- [ ] Tests de transiciones de estado
- [ ] Validar coherencia en tabla

---

## 🔗 REFERENCIAS IMPORTANTES

- BillingController: app/Http/Controllers/BillingController.php
- Subscription Model: app/Models/Subscription.php
- BillingPayment Model: app/Models/BillingPayment.php
- PaymentProvider: app/Services/PaymentProvider/
- Rutas: routes/api.php (buscar 'billing' y 'stripe')
- Env: config('services.stripe.secret'), config('billing.provider')
