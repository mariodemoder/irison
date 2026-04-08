<template>
  <MainLayout>
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h1>Mi cuenta</h1>
        <div class="sub-banner">
          <div class="meta">
            <div style="font-weight:600">{{ user?.name ?? '—' }}</div>
            <div class="small">{{ clinic?.name ?? '—' }}</div>
          </div>

          <div style="display:flex;align-items:center;gap:8px">
            <div style="font-size:13px">{{ subscriptionStatusDot }} {{ subscriptionState.label }}</div>
          </div>

          <div style="margin-left:12px">
            <button class="btn btn-sm" @click.prevent="logoutAction">Cerrar sesión</button>
          </div>
        </div>
      </div>

      <AppLoading v-if="loading" message="Cargando perfil..." />

      <div v-else>
        <div class="profile-container">
          <div class="tabs">
            <button :class="['tab', { active: activeTab==='datos' }]" @click="activeTab='datos'">Datos</button>
            <button :class="['tab', { active: activeTab==='contadores' }]" @click="activeTab='contadores'">Contadores</button>
            <button :class="['tab', { active: activeTab==='factura_pdf' }]" @click="activeTab='factura_pdf'">Factura PDF</button>
              <button :class="['tab', { active: activeTab==='cesiones' }]" @click="activeTab='cesiones'">Cesiones</button>
            <button :class="['tab', { active: activeTab==='seguridad' }]" @click="activeTab='seguridad'">Seguridad</button>
            <button :class="['tab', { active: activeTab==='subscripcion' }]" @click="activeTab='subscripcion'">Subscripción</button>
          </div>

          <div class="profile-shell">
            <div class="card-stage">
              <div class="tab-panel tab-card" v-show="activeTab==='datos'">
                <form @submit.prevent="save" style="display:grid;gap:12px;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label class="label">Nombre</label>
                  <input class="input" v-model="form.name" />
                </div>
                <div>
                  <label class="label">Email</label>
                  <input class="input" v-model="form.email" />
                </div>
              </div>

              <div>
                <label class="label">Nombre clínica</label>
                <input class="input" v-model="form.clinic_name" />
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label class="label">NIF</label>
                  <input class="input" v-model="form.clinic_nif" />
                </div>
                <div>
                  <label class="label">Código postal</label>
                  <input class="input" v-model="form.clinic_zip" />
                </div>
              </div>

              <div>
                <label class="label">Dirección</label>
                <input class="input" v-model="form.clinic_address" />
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div>
                  <label class="label">Localidad</label>
                  <input class="input" v-model="form.clinic_locality" />
                </div>
                <div>
                  <label class="label">Provincia</label>
                  <input class="input" v-model="form.clinic_province" />
                </div>
                <div>
                  <label class="label">País</label>
                  <input class="input" v-model="form.clinic_country" />
                </div>
              </div>

                  <div v-if="status==='blocked'" class="panel-note">
                    Tu clínica no tiene suscripción activa. Puedes activarla desde la pestaña de Subscripción.
                  </div>
                </form>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='seguridad'">
                <h2>Cambiar contraseña</h2>
                <div v-if="pwMessage" class="field-error">{{ pwMessage }}</div>
                <form @submit.prevent="changePassword" style="display:grid;gap:12px">
                  <div>
                    <label class="label">Contraseña actual</label>
                    <input class="input" type="password" v-model="pw.current_password" />
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                      <label class="label">Nueva contraseña</label>
                      <input class="input" type="password" v-model="pw.password" />
                    </div>
                    <div>
                      <label class="label">Confirmar contraseña</label>
                      <input class="input" type="password" v-model="pw.password_confirmation" />
                    </div>
                  </div>
                </form>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='subscripcion'">
                <h2>Subscripción</h2>
                <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
                  <div>{{ subscriptionStatusDot }} {{ subscriptionState.label }}</div>
                </div>
                <div style="margin-top:12px">
                  <div v-if="status==='trial'">
                    <div>Quedan <strong>{{ daysLeft ?? '—' }}</strong> días de demo.</div>
                  </div>
                  <div v-else-if="status==='active'">
                    <div>Tu suscripción está activa.</div>

                    <div class="subscription-history" style="margin-top:14px">
                      <div class="subscription-history-title">Pagos realizados</div>
                      <div v-if="subscriptionPayments.length === 0" class="subscription-history-empty">
                        No hay pagos registrados.
                      </div>
                      <div v-else class="subscription-history-list">
                        <div class="subscription-history-head">
                          <div>Fecha</div>
                          <div>Número</div>
                          <div>Importe</div>
                        </div>
                        <div
                          v-for="payment in subscriptionPayments"
                          :key="payment.id"
                          class="subscription-history-row"
                        >
                          <div>{{ formatDateTime(payment.created_at) }}</div>
                          <div>{{ payment.counter || '—' }}</div>
                          <div>{{ formatBillingAmount(payment.amount, payment.currency) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-else>
                    <div>No tienes suscripción activa.</div>
                  </div>

                  <div class="subscription-actions">
                    <button v-if="status !== 'active'" class="btn btn-primary" @click.prevent="beginPaidPlanFake">Comenzar plan pago</button>
                    <button v-if="status==='blocked'" class="btn" @click.prevent="subscribe">Activar plan (Stripe)</button>
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='contadores'">
                <h2>Contadores</h2>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Formato final: <strong>PREFIJO-000001</strong> (prefijo de 1 a 4 caracteres)
                </div>

                <div class="counter-table-wrap" style="margin-top:12px">
                  <table class="counter-table">
                    <thead>
                      <tr>
                        <th>Tipo</th>
                        <th>Prefijo</th>
                        <th>Último número</th>
                        <th>Siguiente</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in counters" :key="row.table_type">
                        <td data-label="Tipo">{{ counterTypeLabels[row.table_type] || row.table_type }}</td>
                        <td data-label="Prefijo">
                          <input class="input counter-input" maxlength="4" v-model="row.prefix" />
                        </td>
                        <td data-label="Último número">
                          <input class="input counter-input" type="number" min="0" v-model.number="row.last_number" />
                        </td>
                        <td data-label="Siguiente">
                          <input class="input counter-input" :value="previewCounter(row)" disabled />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='cesiones'">
                <div class="section-head">
                  <h2>Tipos de cesiones</h2>
                  <button class="btn btn-sm plus-btn" type="button" @click.prevent="addCesionType" title="Agregar tipo">+</button>
                </div>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Crea todos los tipos que necesites para tu clinica.
                </div>

                <div class="counter-table-wrap" style="margin-top:14px">
                  <table class="counter-table cesiones-table">
                    <colgroup>
                      <col class="cesion-col-description">
                      <col class="cesion-col-time">
                      <col class="cesion-col-price">
                      <col class="cesion-col-payment">
                      <col class="cesion-col-actions">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>Descripcion</th>
                        <th>Tiempo estimado</th>
                        <th>Precio</th>
                        <th>Tipo de pago</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, index) in cesionTypes" :key="item.id">
                        <td data-label="Descripcion">
                          <input class="input counter-input" v-model="item.description" placeholder="Ej: Sesion individual" />
                        </td>
                        <td data-label="Tiempo estimado">
                          <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px">
                            <div>
                              <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:2px">Horas</label>
                              <input class="input counter-input" type="number" min="0" step="1" v-model.number="item.estimated_hours" style="font-size:13px; padding:6px" />
                            </div>
                            <div>
                              <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:2px">Min</label>
                              <input class="input counter-input" type="number" min="0" max="59" step="1" v-model.number="item.estimated_minutes" style="font-size:13px; padding:6px" />
                            </div>
                          </div>
                        </td>
                        <td data-label="Precio">
                          <input class="input counter-input" type="number" min="0" step="0.01" v-model.number="item.price" />
                        </td>
                        <td data-label="Tipo de pago">
                          <select class="input counter-input" v-model="item.payment_type">
                            <option value="simple">Simple</option>
                            <option value="abono">Abono</option>
                          </select>
                        </td>
                        <td data-label="Acciones">
                          <button class="btn btn-sm" type="button" @click.prevent="removeCesionType(item.id)">Eliminar</button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="cesiones-list" style="margin-top:12px">
                  <div v-if="cesionTypes.length === 0" class="subscription-history-empty">
                    Aun no hay tipos de cesiones. Pulsa + para agregar.
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='factura_pdf'">
                <h2>Fondo de factura</h2>
                <div class="invoice-bg-help">
                  Sube una imagen para usarla como fondo en tus facturas PDF. Formatos: JPG, PNG o WEBP. Tamaño máximo: 5MB.<br>
                  La imagen se ajustará al tamaño A4 (210x297mm) manteniendo su proporción. Si la imagen no es A4, se centrará y se le aplicarán márgenes para llenar el espacio restante. Para mejores resultados, se recomienda usar una imagen con proporción cercana a A4 (aprox. 1:1.41) y al menos 1240x1754 píxeles de resolución.
                </div>

                <div style="margin-top:12px">
                  <label class="label">Seleccionar imagen</label>
                  <input class="input" type="file" accept=".jpg,.jpeg,.png,.webp,image/*" @change="onInvoiceBackgroundPicked" />
                </div>

                <div class="invoice-pdf-preview-wrap" style="margin-top:12px">
                  <div v-if="previewingInvoiceBackgroundPdf" class="invoice-bg-empty">Generando preview PDF...</div>
                  <iframe
                    v-else-if="profilePreviewPdfUrl"
                    class="invoice-pdf-preview-frame"
                    :src="profilePreviewPdfUrl"
                    title="Preview PDF factura"
                  ></iframe>
                  <div v-else class="invoice-bg-empty">No se pudo cargar la vista previa del PDF.</div>
                </div>

                <div class="invoice-preview-actions">
                  <button
                    type="button"
                    class="pdf-btn"
                    title="Vista previa factura demo"
                    :disabled="previewingInvoiceBackgroundPdf"
                    @click.prevent="previewAndOpen()"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pdf-icon">
                      <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                      <circle cx="12" cy="12" r="2.5"></circle>
                    </svg>
                  </button>
                  <span class="invoice-preview-label">Preview PDF real (demo con datos fake)</span>
                </div>
              </div>
            </div>

            <div class="action-plane">
              <div v-if="activeTab==='datos' || activeTab==='contadores' || activeTab==='cesiones'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="saving" @click.prevent="save">Guardar</button>
              </div>

              <div v-else-if="activeTab==='factura_pdf'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="previewingInvoiceBackgroundPdf" @click.prevent="refreshPreview()">Actualizar preview</button>
                <button class="btn btn-sm" type="button" :disabled="previewingInvoiceBackgroundPdf" @click.prevent="openPdfInNewTab()">Abrir PDF</button>
                <button class="btn btn-sm" type="button" :disabled="uploadingInvoiceBackground || !invoiceBackgroundFile" @click.prevent="uploadInvoiceBackground">Subir fondo</button>
                <button class="btn btn-sm" type="button" :disabled="removingInvoiceBackground || !invoiceBackgroundUrl" @click.prevent="removeInvoiceBackground">Eliminar fondo</button>
              </div>

              <div v-else-if="activeTab==='seguridad'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="pwSaving" @click.prevent="changePassword">Cambiar contraseña</button>
                <button class="btn btn-sm" type="button" @click.prevent="pwReset">Limpiar</button>
              </div>

              <div v-else class="action-row action-row-empty"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, onBeforeUnmount, watch } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '../layouts/MainLayout.vue'
import AppLoading from '../components/AppLoading.vue'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import logout from '../utils/logout'

const router = useRouter()
const toast = useToast()

const user = ref(null)
const clinic = ref(null)
const status = ref('blocked')
const trial_ends_at = ref(null)
const loading = ref(true)
const saving = ref(false)
const subscriptionPayments = ref([])
const invoiceBackgroundUrl = ref(null)
const invoiceBackgroundFile = ref(null)
const uploadingInvoiceBackground = ref(false)
const removingInvoiceBackground = ref(false)
const previewingInvoiceBackgroundPdf = ref(false)
const profilePreviewPdfUrl = ref(null)

const form = ref({
  name: '',
  email: '',
  clinic_name: '',
  clinic_nif: '',
  clinic_address: '',
  clinic_locality: '',
  clinic_province: '',
  clinic_country: '',
  clinic_zip: '',
})

const counterTypeLabels = {
  documents: 'Facturación',
  payout: 'Abonos',
  bonuses: 'Bonos',
  payments: 'Pagos',
  patients: 'Pacientes',
}

function defaultCounters() {
  return [
    { table_type: 'documents', prefix: 'FR', last_number: 0 },
    { table_type: 'payout', prefix: 'AB', last_number: 0 },
    { table_type: 'bonuses', prefix: 'B0', last_number: 0 },
    { table_type: 'payments', prefix: 'PA', last_number: 0 },
    { table_type: 'patients', prefix: 'PC', last_number: 0 },
  ]
}

const counters = ref(defaultCounters())
const cesionTypes = ref([])

// pestañas: 'datos' | 'seguridad' | 'subscripcion' | 'contadores'
const activeTab = ref('datos')

// password change
const pw = ref({ current_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwMessage = ref('')

const daysLeft = computed(() => {
  if (!trial_ends_at.value) return null
  const end = new Date(trial_ends_at.value)
  const now = new Date()
  const diff = end.getTime() - now.getTime()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const subscriptionState = computed(() => {
  if (status.value === 'active') return { color: 'green', label: 'Suscripción activa' }
  if (status.value === 'trial') return { color: 'yellow', label: `Prueba — quedan ${daysLeft.value ?? '—'} días` }
  return { color: 'red', label: 'Sin suscripción' }
})

const subscriptionStatusDot = computed(() => {
  if (status.value === 'trial') return '🟠'
  if (status.value === 'active' || status.value === 'activa') return '🟢'
  if (status.value === 'canceled' || status.value === 'cancelled' || status.value === 'blocked') return '🔴'
  return '🔴'
})

// indica si el trial está vencido
const trialExpired = computed(() => {
  return status.value === 'trial' && daysLeft.value !== null && daysLeft.value <= 0
})

onMounted(async () => {
  await load()
})

watch(activeTab, async (tab) => {
  if (tab === 'factura_pdf' && !profilePreviewPdfUrl.value) {
    await refreshPreview()
  }
})

async function load() {
  loading.value = true
  try {
    const res = await api.get('/me')
    user.value = res.data.user
    clinic.value = res.data.clinic
    status.value = res.data.status || status.value
    trial_ends_at.value = res.data.trial_ends_at || null
    subscriptionPayments.value = Array.isArray(res.data.subscription_payments) ? res.data.subscription_payments : []
    invoiceBackgroundUrl.value = res.data.clinic_invoice_background_url || null

    form.value.name = user.value?.name ?? ''
    form.value.email = user.value?.email ?? ''
    form.value.clinic_name = clinic.value?.name ?? ''
    form.value.clinic_nif = clinic.value?.nif ?? ''
    form.value.clinic_address = clinic.value?.address ?? ''
    form.value.clinic_locality = clinic.value?.locality ?? ''
    form.value.clinic_province = clinic.value?.province ?? ''
    form.value.clinic_country = clinic.value?.country ?? ''
    form.value.clinic_zip = clinic.value?.zip ?? ''

    const incomingCounters = Array.isArray(res.data.counters) ? res.data.counters : []
    if (incomingCounters.length > 0) {
      counters.value = defaultCounters().map((base) => {
        const found = incomingCounters.find((item) => item.table_type === base.table_type)
        return {
          table_type: base.table_type,
          prefix: (found?.prefix ?? base.prefix ?? '').toString().toUpperCase(),
          last_number: Number.isFinite(Number(found?.last_number)) ? Math.max(Number(found?.last_number), 0) : 0,
        }
      })
    } else {
      counters.value = defaultCounters()
    }

    // Cargar cesiones desde el servidor
    const incomingCesiones = Array.isArray(res.data.cesiones) ? res.data.cesiones : []
    cesionTypes.value = incomingCesiones.map((item) => sanitizeCesionType(item))

    if (activeTab.value === 'factura_pdf') {
      await refreshPreview()
    }
  } catch (e) {
    console.error('Error cargando /me', e)
    toast.error('Error cargando datos de usuario')
  } finally {
    loading.value = false
  }
}

function logoutAction() {
  logout(router)
}

async function save() {
  saving.value = true
  try {
    cesionTypes.value = cesionTypes.value.map((item) => sanitizeCesionType(item))

    const payload = {
      name: form.value.name,
      email: form.value.email,
      clinic: {
        name: form.value.clinic_name,
        nif: form.value.clinic_nif,
        address: form.value.clinic_address,
        locality: form.value.clinic_locality,
        province: form.value.clinic_province,
        country: form.value.clinic_country,
        zip: form.value.clinic_zip,
      },
      counters: counters.value.map((item) => ({
        table_type: item.table_type,
        prefix: (item.prefix ?? '').toString().trim().toUpperCase(),
        last_number: Number.isFinite(Number(item.last_number)) ? Math.max(Number(item.last_number), 0) : 0,
      })),
      cesiones: cesionTypes.value.map((item) => ({
        description: item.description,
        estimated_hours: item.estimated_hours,
        estimated_minutes: item.estimated_minutes,
        price: item.price,
        payment_type: item.payment_type,
      }))
    }
    // Intentamos PUT a /me (backend debe aceptar actualización parcial)
    const res = await api.put('/me', payload)
    toast.success('Datos guardados')
    // actualizar estado local
    user.value = res.data.user ?? user.value
    clinic.value = res.data.clinic ?? clinic.value
    if (Array.isArray(res.data.counters) && res.data.counters.length > 0) {
      counters.value = defaultCounters().map((base) => {
        const found = res.data.counters.find((item) => item.table_type === base.table_type)
        return {
          table_type: base.table_type,
          prefix: (found?.prefix ?? base.prefix ?? '').toString().toUpperCase(),
          last_number: Number.isFinite(Number(found?.last_number)) ? Math.max(Number(found?.last_number), 0) : 0,
        }
      })
    }
    // Cargar cesiones actualizadas desde el servidor
    const incomingCesiones = Array.isArray(res.data.cesiones) ? res.data.cesiones : []
    cesionTypes.value = incomingCesiones.map((item) => sanitizeCesionType(item))
  } catch (e) {
    console.error('Error guardando perfil', e)
    const msg = e.response?.data?.message || 'Error guardando datos'
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

function reload() { load() }

function makeCesionType() {
  return {
    description: '',
    estimated_hours: 0,
    estimated_minutes: 60,
    price: 0,
    payment_type: 'simple',
  }
}

function sanitizeCesionType(item) {
  return {
    id: item?.id,
    description: (item?.description ?? '').toString(),
    estimated_hours: Number.isFinite(Number(item?.estimated_hours))
      ? Math.max(Number(item.estimated_hours), 0)
      : 0,
    estimated_minutes: Number.isFinite(Number(item?.estimated_minutes))
      ? Math.min(Math.max(Number(item.estimated_minutes), 0), 59)
      : 60,
    price: Number.isFinite(Number(item?.price))
      ? Math.max(Number(item.price), 0)
      : 0,
    payment_type: item?.payment_type === 'abono' ? 'abono' : 'simple',
  }
}

function addCesionType() {
  cesionTypes.value.push(makeCesionType())
}

function removeCesionType(id) {
  cesionTypes.value = cesionTypes.value.filter((item) => item.id !== id)
}

function onInvoiceBackgroundPicked(event) {
  const files = event?.target?.files
  invoiceBackgroundFile.value = files && files.length > 0 ? files[0] : null

  refreshPreview()
}

async function uploadInvoiceBackground() {
  if (!invoiceBackgroundFile.value) {
    toast.error('Seleccioná una imagen antes de subir')
    return
  }

  uploadingInvoiceBackground.value = true
  try {
    const formData = new FormData()
    formData.append('image', invoiceBackgroundFile.value)

    const res = await api.post('/me/invoice-background', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    invoiceBackgroundUrl.value = res.data?.invoice_background_url || null
    invoiceBackgroundFile.value = null
    await refreshPreview()
    toast.success('Fondo de factura actualizado')
  } catch (e) {
    console.error('Error subiendo fondo de factura', e)
    const msg = e.response?.data?.message || 'No se pudo subir el fondo'
    toast.error(msg)
  } finally {
    uploadingInvoiceBackground.value = false
  }
}

async function removeInvoiceBackground() {
  removingInvoiceBackground.value = true
  try {
    await api.delete('/me/invoice-background')
    invoiceBackgroundUrl.value = null
    invoiceBackgroundFile.value = null
    await refreshPreview()
    toast.success('Fondo de factura eliminado')
  } catch (e) {
    console.error('Error eliminando fondo de factura', e)
    const msg = e.response?.data?.message || 'No se pudo eliminar el fondo'
    toast.error(msg)
  } finally {
    removingInvoiceBackground.value = false
  }
}

async function refreshPreview() {
  previewingInvoiceBackgroundPdf.value = true
  try {
    const formData = new FormData()
    if (invoiceBackgroundFile.value) {
      formData.append('image', invoiceBackgroundFile.value)
    }

    const res = await api.post('/me/invoice-background/preview-pdf?format=html', formData, {
      responseType: 'text',
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const blob = new Blob([res.data], { type: 'text/html' })
    const fileUrl = URL.createObjectURL(blob)

    if (profilePreviewPdfUrl.value) {
      URL.revokeObjectURL(profilePreviewPdfUrl.value)
    }
    profilePreviewPdfUrl.value = fileUrl
  } catch (e) {
    console.error('Error generando preview demo', e)
    const msg = e.response?.data?.message || 'No se pudo generar la vista previa de factura'
    toast.error(msg)
  } finally {
    previewingInvoiceBackgroundPdf.value = false
  }
}

async function openPdfInNewTab() {
  previewingInvoiceBackgroundPdf.value = true
  try {
    const formData = new FormData()
    if (invoiceBackgroundFile.value) {
      formData.append('image', invoiceBackgroundFile.value)
    }

    const res = await api.post('/me/invoice-background/preview-pdf', formData, {
      responseType: 'blob',
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    window.open(fileUrl, '_blank', 'noopener,noreferrer')
  } catch (e) {
    console.error('Error generando PDF demo', e)
    const msg = e.response?.data?.message || 'No se pudo generar la vista previa de factura'
    toast.error(msg)
  } finally {
    previewingInvoiceBackgroundPdf.value = false
  }
}

async function previewAndOpen() {
  await refreshPreview()
  await openPdfInNewTab()
}

function pwReset() {
  pw.value.current_password = ''
  pw.value.password = ''
  pw.value.password_confirmation = ''
  pwMessage.value = ''
}

async function changePassword() {
  pwSaving.value = true
  pwMessage.value = ''
  try {
    await api.post('/me/password', { ...pw.value })
    pwReset()
    const toast = useToast()
    toast.success('Contraseña actualizada')
  } catch (e) {
    console.error('Error cambiando contraseña', e)
    if (e.response && e.response.status === 422) {
      const errs = e.response.data.errors || {}
      // Mostrar primer error encontrado
      const first = Object.values(errs)[0]
      pwMessage.value = Array.isArray(first) ? first[0] : String(first)
    } else {
      pwMessage.value = e.response?.data?.message || 'Error cambiando contraseña'
    }
  } finally {
    pwSaving.value = false
  }
}

async function subscribe() {
  try {
    const res = await api.post('/stripe/checkout')
    window.location.href = res.data.url
  } catch (e) {
    console.error('Error creando checkout', e)
    toast.error('Error iniciando subscripción')
  }
}

function beginPaidPlanFake() {
  router.push('/billing/required')
}

function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('es-ES')
}

function formatBillingAmount(amountInCents, currency = 'EUR') {
  const cents = Number(amountInCents || 0)
  const amount = Number.isFinite(cents) ? cents / 100 : 0
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'EUR' }).format(amount)
}

function previewCounter(row) {
  const prefix = (row?.prefix ?? '').toString().trim().toUpperCase().slice(0, 4)
  const value = Number.isFinite(Number(row?.last_number)) ? Math.max(Number(row.last_number) + 1, 1) : 1
  return `${prefix || '---'}-${String(value).padStart(6, '0')}`
}

onBeforeUnmount(() => {
  if (profilePreviewPdfUrl.value) {
    URL.revokeObjectURL(profilePreviewPdfUrl.value)
  }
})
</script>

<style scoped>
.label { display:block; font-weight:600; margin-bottom:6px }
.input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb }
.sub-banner { display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.9); padding:8px 10px; border-radius:10px; box-shadow: 0 6px 18px rgba(2,6,23,0.06) }
.sub-banner .meta { display:flex; flex-direction:column }
.sub-banner .small { font-size:12px; color:var(--text-muted,#6b7280) }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }

.profile-container {
  width: 100%;
  max-width: 1180px;
}
.profile-shell { display:grid; gap:14px }
.card-stage { min-height:560px }
.tabs {
  display:grid;
  grid-template-columns:repeat(6, minmax(0, 1fr));
  gap:8px;
  margin-bottom:12px;
}
.tab {
  width: 100%;
  text-align: center;
  padding:8px 12px;
  border-radius:8px;
  background:transparent;
  border:1px solid transparent;
  cursor:pointer;
}
.tab.active { background:#eef2ff; border-color:#c7d2fe; font-weight:600 }
.tab-panel { background:transparent }
.tab-card {
  min-height:560px;
  width: 100%;
  padding:20px;
  border:1px solid #e5e7eb;
  border-radius:16px;
  background:#fff;
  box-shadow: 0 10px 30px rgba(2,6,23,0.06);
}
.action-plane {
  position:sticky;
  bottom:16px;
  padding:12px 16px;
  border:1px solid #e5e7eb;
  border-radius:16px;
  background:rgba(255,255,255,0.96);
  box-shadow: 0 12px 28px rgba(2,6,23,0.08);
  backdrop-filter: blur(8px);
}
.action-row { display:flex; gap:8px; min-height:38px; align-items:center }
.action-row-empty { justify-content:flex-end }
.panel-note {
  margin-top:auto;
  padding:12px 14px;
  border-radius:12px;
  background:#fff7ed;
  color:#9a3412;
  font-size:13px;
}
.subscription-actions { display:flex; gap:8px; margin-top:16px; flex-wrap:wrap }
.subscription-history-title { font-size:13px; font-weight:700; color:#111827; margin-bottom:8px }
.subscription-history-empty { color:#6b7280; font-size:13px; padding:10px; border:1px dashed #d1d5db; border-radius:8px }
.subscription-history-list { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden }
.subscription-history-head,
.subscription-history-row {
  display:grid;
  grid-template-columns:1.4fr 1fr 1fr;
  gap:10px;
  padding:8px 10px;
  font-size:13px;
  align-items:center;
}
.subscription-history-head { background:#f9fafb; color:#6b7280; font-weight:600 }
.subscription-history-row { border-top:1px solid #f3f4f6 }
.counter-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}
.counter-table {
  width: 100%;
  border-collapse: collapse;
}
.counter-table th,
.counter-table td {
  padding: 10px;
  border-bottom: 1px solid #f3f4f6;
  text-align: left;
  font-size: 13px;
}
.counter-table th {
  background: #f9fafb;
  color: #6b7280;
  font-weight: 600;
}
.counter-table tbody tr:last-child td {
  border-bottom: 0;
}
.counter-input {
  min-width: 120px;
}
.cesiones-table .cesion-col-description { width: 42%; }
.cesiones-table .cesion-col-time { width: 16%; }
.cesiones-table .cesion-col-price { width: 14%; }
.cesiones-table .cesion-col-payment { width: 18%; }
.cesiones-table .cesion-col-actions { width: 10%; }
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.plus-btn {
  min-width: 34px;
  width: 34px;
  height: 34px;
  padding: 0;
  font-size: 20px;
  line-height: 1;
}
.cesiones-list {
  display: grid;
  gap: 12px;
}
.cesion-item {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px;
  background: #f9fafb;
}
.cesion-item-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.cesion-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1fr;
  gap: 10px;
  align-items: end;
}
.invoice-bg-help {
  margin-top: 8px;
  color: #4b5563;
  font-size: 13px;
}
.invoice-pdf-preview-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #f8fafc;
  aspect-ratio: 210 / 297;
  overflow: hidden;
}
.invoice-pdf-preview-frame {
  width: 100%;
  height: 100%;
  border: 0;
  background: #ffffff;
}
.invoice-bg-empty {
  margin: 0;
  height: 100%;
  display: grid;
  place-items: center;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  padding: 12px;
  color: #6b7280;
  font-size: 13px;
}

@media (max-width: 980px) {
  .tabs {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .tabs {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .tab-card { min-height:auto; }
  .card-stage { min-height:auto; }
  .action-plane {
    position:static;
    bottom:auto;
  }
  .counter-table thead {
    display: none;
  }
  .counter-table,
  .counter-table tbody,
  .counter-table tr,
  .counter-table td {
    display: block;
    width: 100%;
  }
  .counter-table tr {
    border-bottom: 1px solid #e5e7eb;
    padding: 8px 0;
  }
  .counter-table td {
    border-bottom: 0;
    padding: 6px 10px;
  }
  .counter-table td::before {
    content: attr(data-label);
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
  }
  .subscription-history-head,
  .subscription-history-row {
    grid-template-columns:1fr;
  }
  .invoice-pdf-preview-wrap {
    min-height: 440px;
    aspect-ratio: auto;
  }
}
</style>
