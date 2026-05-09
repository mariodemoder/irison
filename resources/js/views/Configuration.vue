<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Configuración</h1>
          <div class="form-sub">Personaliza tu empresa</div>
        </div>

      </div>

      <AppLoading v-if="loading" message="Cargando configuración..." />

      <div v-else>
        <div class="profile-container">
          <div class="tabs">
            <button :class="['tab', { active: activeTab==='clinica' }]" @click="activeTab='clinica'">Clínica</button>
            <button :class="['tab', { active: activeTab==='horarios' }]" @click="activeTab='horarios'">Horarios</button>
            <button :class="['tab', { active: activeTab==='contadores' }]" @click="activeTab='contadores'">Contadores</button>
            <button :class="['tab', { active: activeTab==='factura_pdf' }]" @click="activeTab='factura_pdf'">Factura PDF</button>
            <button :class="['tab', { active: activeTab==='sesiones' }]" @click="activeTab='sesiones'">Sesiones</button>
            <button :class="['tab', { active: activeTab==='bonos' }]" @click="activeTab='bonos'">Bonos</button>
            <button :class="['tab', { active: activeTab==='subscripcion' }]" @click="activeTab='subscripcion'">Subscripción</button>
          </div>

          <div class="profile-shell">
            <div class="card-stage">
              <div class="tab-panel tab-card" v-show="activeTab==='clinica'">
                <form @submit.prevent="save" style="display:grid;gap:12px;">
                  <div>
                    <label class="label">Nombre clínica</label>
                    <input class="input" v-model="form.clinic_name" />
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                      <label class="label">Email</label>
                      <input class="input" v-model="form.clinic_email" type="email" />
                    </div>
                    <div>
                      <label class="label">Teléfono</label>
                      <input class="input" v-model="form.clinic_phone" type="text" />
                    </div>
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

                  <div>
                    <label class="label">Color de tema</label>
                    <div class="color-palette">
                      <button type="button" class="color-option" v-for="color in themeColors" :key="color" :style="{ backgroundColor: color }" :class="{ selected: form.clinic_theme_color === color }" @click="form.clinic_theme_color = color" :title="color"></button>
                    </div>
                    <div class="color-preview">Seleccionado: <span :style="{ color: form.clinic_theme_color || '#e5e7eb' }">■</span> {{ form.clinic_theme_color || '#e5e7eb' }}</div>
                  </div>

                  <div v-if="status==='blocked'" class="panel-note">
                    Tu clínica no tiene suscripción activa. Puedes activarla desde la pestaña de Subscripción.
                  </div>
                </form>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='horarios'">
                <div class="section-head">
                    <label class="label">Días de atención</label>
                  </div>
                <div class="section-copy">Define los días y horarios de atención de la clínica.</div>

                <div class="hours-table-wrap" style="margin-top:14px">
                  <table class="counter-table hours-table">
                    <thead>
                      <tr>
                        <th></th>
                        <th v-for="row in businessHours" :key="`head-${row.day}`">{{ dayLabels[row.day] }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="hours-row-label">Abierto</td>
                        <td v-for="row in businessHours" :key="`enabled-${row.day}`" class="hours-cell-center">
                          <label class="day-toggle">
                            <input v-model="row.enabled" type="checkbox" />
                            <span>{{ row.enabled ? 'Sí' : 'No' }}</span>
                          </label>
                        </td>
                      </tr>
                      <tr>
                        <td class="hours-row-label">Desde</td>
                        <td v-for="row in businessHours" :key="`start-${row.day}`" class="hours-cell-center">
                          <input class="input counter-input" type="time" step="300" v-model="row.start" :disabled="!row.enabled" />
                        </td>
                      </tr>
                      <tr>
                        <td class="hours-row-label">Hasta</td>
                        <td v-for="row in businessHours" :key="`end-${row.day}`" class="hours-cell-center">
                          <input class="input counter-input" type="time" step="300" v-model="row.end" :disabled="!row.enabled" />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="closed-days-block">
                  <div class="section-head">
                    <label class="label">Días cerrados</label>
                  </div>
                  <div class="section-copy">Selecciona fechas puntuales en las que la clínica permanecerá cerrada.</div>



                  <div class="closed-days-cal-grid">
                    <div class="closed-cal-card">
                      <label class="label">Individual</label>
                      <div class="closed-controls-row closed-controls-row-single">
                        <input v-model="newClosedDay" class="input closed-day-input" type="date" />
                        <div class="closed-card-actions">
                          <button class="btn btn-sm" type="button" @click.prevent="addClosedDay">+</button>
                          <BtnTrash :disabled="individualClosedDays.length === 0" @click="clearIndividualClosedDays" />
                        </div>
                      </div>

                      <div class="section-head">
                        <h4>Días individuales seleccionados</h4>
                      </div>
                      <div v-if="individualClosedDays.length" class="closed-days-list">
                        <button v-for="day in individualClosedDays" :key="day" type="button" class="closed-day-chip" @click.prevent="removeClosedDay(day)">
                          <span>{{ formatClosedDay(day) }}</span>
                          <span aria-hidden="true">✕</span>
                        </button>
                      </div>
                      <div v-else class="subscription-history-empty">No hay días individuales cargados.</div>
                    </div>

                    <div class="closed-cal-card">
                      <label class="label">Rango</label>
                      <div class="closed-controls-row closed-controls-row-range">
                        <input v-model="closedRangeStart" class="input closed-day-input" type="date" />
                        <input v-model="closedRangeEnd" class="input closed-day-input" type="date" />
                        <div class="closed-card-actions">
                          <button class="btn btn-sm" type="button" @click.prevent="addClosedDayRange">+</button>
                          <BtnTrash :disabled="rangeClosedDays.length === 0" @click="clearRangeClosedDays" />
                        </div>
                      </div>

                      <div class="section-head">
                        <h4>Rangos seleccionados</h4>
                      </div>
                      <div v-if="rangeClosedDays.length" class="closed-days-list">
                        <button v-for="day in rangeClosedDays" :key="day" type="button" class="closed-day-chip" @click.prevent="removeClosedDay(day)">
                          <span>{{ formatClosedDay(day) }}</span>
                          <span aria-hidden="true">✕</span>
                        </button>
                      </div>
                      <div v-else class="subscription-history-empty">No hay rangos cargados.</div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='subscripcion'">
                
                <div class="subscription-header">
                  <div>{{ subscriptionStatusDot }} {{ subscriptionState.label }}</div>
                  <div class="subscription-actions">
                    <button v-if="status !== 'active'" class="btn btn-primary allow-readonly-action" @click.prevent="beginPaidPlanFake">Activar cuenta de pago</button>
                    <button v-if="status==='blocked'" class="btn" @click.prevent="subscribe">Activar plan (Stripe)</button>
                    <div v-if="status==='active'" class="sub-menu-wrap">
                      <button class="btn sub-menu-trigger" @click.stop="showSubscriptionMenu = !showSubscriptionMenu" title="Opciones de suscripción">
                        &#8942;
                      </button>
                      <div v-if="showSubscriptionMenu" class="sub-menu-dropdown">
                        <button class="sub-menu-item sub-menu-item--danger" :disabled="cancellingSubscription" @click.prevent="openCancelSubscriptionModal(); showSubscriptionMenu = false">
                          Cancelar suscripción
                        </button>
                        <button class="sub-menu-item" @click.stop="scrollToPayments">
                          Consultar pago
                        </button>
                        <a class="sub-menu-item" href="/legal" target="_blank" @click="showSubscriptionMenu = false">
                          Legales
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
                
                  <div class="subscription-meta">
                    <div><strong>Modo de pago:</strong> {{ paymentModeLabel }}</div>
                  </div>
                <div style="margin-top:12px">
                  <div v-if="status==='trial'">
                    <div>Quedan <strong>{{ daysLeft ?? '—' }}</strong> días de demo.</div>
                    <div class="subscription-warning">
                      <div class="warning-header">
                        ⚠️ Tu suscripción está por vencer
                      </div>

                      <div class="warning-body">
                        <p>
                          Una vez finalizado el período de prueba, si no se registra el pago,
                          no podrás realizar transacciones.
                        </p>

                        <p>
                          Tus datos y tu cuenta se conservarán durante <strong>7 días adicionales</strong>.
                        </p>

                        <p>
                          Transcurrido ese plazo sin activación, la cuenta y toda la información
                          serán eliminadas de forma definitiva.
                        </p>
                      </div>

                      <div class="warning-footer">
                        No pierdas tu información. Activa tu plan para seguir operando.
                      </div>
                    </div>
                  </div>
                  <div v-else-if="status==='trial_read_only'">
                    <div>Tu periodo de prueba ha finalizado.</div>
                    <div class="subscription-warning" style="margin-top:10px">
                      Estás en una semana de acceso solo lectura. Para seguir operando con normalidad, activa tu cuenta de pago.
                    </div>
                  </div>
                  <div v-else-if="status==='active'">
                    <div>Tu suscripción está activa.</div>

                    <div class="subscription-history" id="sub-pagos" style="margin-top:14px">
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

                  

                  <div v-if="showCancelSubscriptionModal" class="confirm-modal-backdrop" @click.self="closeCancelSubscriptionModal">
                    <div class="confirm-modal" role="dialog" aria-modal="true" aria-label="Confirmar cancelación de suscripción">
                      <h3>Cancelar suscripción</h3>
                      <p>Esta acción cancelará tu suscripción activa. ¿Deseas continuar?</p>
                      <div class="confirm-modal-actions">
                        <button class="btn" :disabled="cancellingSubscription" @click.prevent="closeCancelSubscriptionModal">Volver</button>
                        <button class="btn subscription-cancel-btn" :disabled="cancellingSubscription" @click.prevent="confirmCancelSubscription">
                          {{ cancellingSubscription ? 'Cancelando...' : 'Sí, cancelar suscripción' }}
                        </button>
                      </div>
                    </div>
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

              <div class="tab-panel tab-card" v-show="activeTab==='sesiones'">
                <div class="section-head">
                  <h2>Tipos de sesiones</h2>
                  <button class="btn btn-sm plus-btn" type="button" @click.prevent="addCesionType" title="Agregar tipo" aria-label="Agregar tipo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M12 5v14"></path>
                      <path d="M5 12h14"></path>
                    </svg>
                  </button>
                </div>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Crea todos los tipos que necesites para tu clinica.
                </div>

                <div class="counter-table-wrap" style="margin-top:14px">
                  <table class="counter-table sesiones-table">
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
                      <tr v-for="item in cesionTypes" :key="item.id">
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
                            <option value="abono">Bono</option>
                          </select>
                        </td>
                        <td data-label="Acciones">
                          <BtnTrash @click.prevent="removeCesionType(item.id)">Eliminar</BtnTrash>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="sesiones-list" style="margin-top:12px">
                  <div v-if="cesionTypes.length === 0" class="subscription-history-empty">
                    Aun no hay tipos de sesiones. Usa el botón Agregar para crear el primero.
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='bonos'">
                <div class="section-head">
                  <h2>Tipos de bonos</h2>
                  <button class="btn btn-sm plus-btn" type="button" @click.prevent="addBonusType" title="Agregar bono" aria-label="Agregar bono">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M12 5v14"></path>
                      <path d="M5 12h14"></path>
                    </svg>
                  </button>
                </div>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Crea las plantillas de bono que ofreces. Podrás asignarlas directamente a pacientes.
                </div>

                <div class="counter-table-wrap" style="margin-top:14px">
                  <table class="counter-table sesiones-table">
                    <colgroup>
                      <col style="width:38%">
                      <col style="width:14%">
                      <col style="width:16%">
                      <col style="width:18%">
                      <col style="width:14%">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>Descripción</th>
                        <th>Nº sesiones</th>
                        <th>Precio</th>
                        <th>Expira</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in bonusTypes" :key="item.id ?? item._key">
                        <td data-label="Descripción">
                          <input class="input counter-input" v-model="item.description" placeholder="Ej: Bono 10 sesiones" />
                        </td>
                        <td data-label="Nº sesiones">
                          <input class="input counter-input" type="number" min="1" step="1" v-model.number="item.sessions" />
                        </td>
                        <td data-label="Precio">
                          <input class="input counter-input" type="number" min="0" step="0.01" v-model.number="item.price" />
                        </td>
                        <td data-label="Expira">
                          <input class="input counter-input" type="date" v-model="item.expires_at" />
                        </td>
                        <td data-label="Acciones">
                          <BtnTrash @click.prevent="removeBonusType(item)">Eliminar</BtnTrash>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div style="margin-top:12px">
                  <div v-if="bonusTypes.length === 0" class="subscription-history-empty">
                    Aún no hay tipos de bono. Usa el botón + para crear el primero.
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
              <div v-if="activeTab==='clinica' || activeTab==='horarios' || activeTab==='contadores' || activeTab==='sesiones' || activeTab==='bonos'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="saving" @click.prevent="save">Guardar</button>
              </div>

              <div v-else-if="activeTab==='factura_pdf'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="previewingInvoiceBackgroundPdf" @click.prevent="openPdfInNewTab()">Abrir PDF</button>
                <button class="btn btn-sm" type="button" :disabled="uploadingInvoiceBackground || !invoiceBackgroundFile" @click.prevent="uploadInvoiceBackground">Subir fondo</button>
                <BtnTrash :disabled="removingInvoiceBackground || !invoiceBackgroundUrl" @click.prevent="removeInvoiceBackground">Eliminar fondo</BtnTrash>
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
import BtnTrash from '../components/BtnTrash.vue'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import { meClinic } from '../shared/meCache'

const router = useRouter()
const toast = useToast()

const user = ref(null)
const clinic = ref(null)
const status = ref('blocked')
const trial_ends_at = ref(null)
const loading = ref(true)
const saving = ref(false)
const cancellingSubscription = ref(false)
const showCancelSubscriptionModal = ref(false)
const showSubscriptionMenu = ref(false)

function handleClickOutsideSubMenu() {
  showSubscriptionMenu.value = false
}

function scrollToPayments() {
  showSubscriptionMenu.value = false
  const el = document.getElementById('sub-pagos')
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => document.addEventListener('click', handleClickOutsideSubMenu))
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutsideSubMenu))
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
  clinic_email: '',
  clinic_phone: '',
  clinic_nif: '',
  clinic_address: '',
  clinic_locality: '',
  clinic_province: '',
  clinic_country: '',
  clinic_zip: '',
  clinic_theme_color: '',
})

const themeColors = [
  '#FFB3BA', // pastel pink
  '#FFD6BA', // pastel peach
  '#FFFFBA', // pastel yellow
  '#BAE1BA', // pastel green
  '#BAC8FF', // pastel blue
  '#E1BAFF', // pastel purple
]

const dayLabels = {
  monday: 'Lunes',
  tuesday: 'Martes',
  wednesday: 'Miércoles',
  thursday: 'Jueves',
  friday: 'Viernes',
  saturday: 'Sábado',
  sunday: 'Domingo',
}

function defaultBusinessHours() {
  return [
    { day: 'monday', enabled: false, start: '09:00', end: '18:00' },
    { day: 'tuesday', enabled: false, start: '09:00', end: '18:00' },
    { day: 'wednesday', enabled: false, start: '09:00', end: '18:00' },
    { day: 'thursday', enabled: false, start: '09:00', end: '18:00' },
    { day: 'friday', enabled: false, start: '09:00', end: '18:00' },
    { day: 'saturday', enabled: false, start: '09:00', end: '14:00' },
    { day: 'sunday', enabled: false, start: '09:00', end: '14:00' },
  ]
}

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
const bonusTypes = ref([])
const businessHours = ref(defaultBusinessHours())
const closedDays = ref([])
const newClosedDay = ref('')
const closedRangeStart = ref('')
const closedRangeEnd = ref('')
const activeTab = ref('clinica')

const individualClosedDays = computed(() => closedDays.value.filter((item) => !item.includes('..')))
const rangeClosedDays = computed(() => closedDays.value.filter((item) => item.includes('..')))

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
  if (status.value === 'trial_read_only') return { color: 'red', label: 'Trial finalizado — solo lectura (7 días)' }
  if (status.value === 'canceled' || status.value === 'cancelled') return { color: 'red', label: 'Suscripción cancelada — solo lectura' }
  return { color: 'red', label: 'Sin suscripción' }
})

const subscriptionStatusDot = computed(() => {
  if (status.value === 'trial') return '🟠'
  if (status.value === 'active' || status.value === 'activa') return '🟢'
  if (status.value === 'canceled' || status.value === 'cancelled' || status.value === 'blocked') return '🔴'
  return '🔴'
})

    const paymentModeLabel = computed(() => {
      const provider = String(clinic.value?.subscription_provider || '').trim().toLowerCase()
      if (provider === 'stripe') return 'Stripe'
      if (provider === 'fake') return 'Fake (desarrollo)'
      return 'No configurado'
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
    form.value.clinic_email = clinic.value?.email ?? ''
    form.value.clinic_phone = clinic.value?.phone ?? ''
    form.value.clinic_nif = clinic.value?.nif ?? ''
    form.value.clinic_address = clinic.value?.address ?? ''
    form.value.clinic_locality = clinic.value?.locality ?? ''
    form.value.clinic_province = clinic.value?.province ?? ''
    form.value.clinic_country = clinic.value?.country ?? ''
    form.value.clinic_zip = clinic.value?.zip ?? ''
    form.value.clinic_theme_color = clinic.value?.theme_color ?? ''
    businessHours.value = sanitizeBusinessHours(clinic.value?.business_hours)
    closedDays.value = sanitizeClosedDays(clinic.value?.closed_days)

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

    const incomingsesiones = Array.isArray(res.data.cesiones) ? res.data.cesiones : []
    cesionTypes.value = incomingsesiones.map((item) => sanitizeCesionType(item))

    const incomingBonusTypes = Array.isArray(res.data.bonus_types) ? res.data.bonus_types : []
    bonusTypes.value = incomingBonusTypes.map((item) => sanitizeBonusType(item))

    if (activeTab.value === 'factura_pdf') {
      await refreshPreview()
    }
  } catch (e) {
    console.error('Error cargando /me', e)
    const status = e?.response?.status
    const message = e?.response?.data?.message
    toast.error((status === 402 || status === 403) && message ? `Error cargando configuración - ${message}` : 'Error cargando configuración')
  } finally {
    loading.value = false
  }
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
        email: form.value.clinic_email,
        phone: form.value.clinic_phone,
        nif: form.value.clinic_nif,
        address: form.value.clinic_address,
        locality: form.value.clinic_locality,
        province: form.value.clinic_province,
        country: form.value.clinic_country,
        zip: form.value.clinic_zip,
        theme_color: form.value.clinic_theme_color || null,
        business_hours: businessHours.value.map((item) => ({
          day: item.day,
          enabled: Boolean(item.enabled),
          start: item.enabled ? item.start : null,
          end: item.enabled ? item.end : null,
        })),
        closed_days: closedDays.value,
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
      })),
      bonus_types: bonusTypes.value.map((item) => ({
        description: item.description,
        sessions: item.sessions,
        price: item.price,
        expires_at: item.expires_at || null,
      })),
    }

    const res = await api.put('/me', payload)
    toast.success('Configuración guardada')
    user.value = res.data.user ?? user.value
    clinic.value = res.data.clinic ?? clinic.value
    meClinic.value = clinic.value
    businessHours.value = sanitizeBusinessHours(clinic.value?.business_hours)
    closedDays.value = sanitizeClosedDays(clinic.value?.closed_days)
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
    const incomingsesiones = Array.isArray(res.data.cesiones) ? res.data.cesiones : []
    cesionTypes.value = incomingsesiones.map((item) => sanitizeCesionType(item))

    const incomingBonusTypesSave = Array.isArray(res.data.bonus_types) ? res.data.bonus_types : []
    bonusTypes.value = incomingBonusTypesSave.map((item) => sanitizeBonusType(item))
  } catch (e) {
    console.error('Error guardando configuración', e)
    const msg = e.response?.data?.message || 'Error guardando datos'
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

function makeCesionType() {
  return {
    description: '',
    estimated_hours: 1,
    estimated_minutes: 0,
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
      : 0,
    price: Number.isFinite(Number(item?.price))
      ? Math.max(Number(item.price), 0)
      : 0,
    payment_type: item?.payment_type === 'abono' ? 'abono' : 'simple',
  }
}

function sanitizeBusinessHours(items) {
  const defaults = defaultBusinessHours()
  const incoming = Array.isArray(items) ? items : []

  return defaults.map((base) => {
    const found = incoming.find((item) => item?.day === base.day)
    return {
      day: base.day,
      enabled: Boolean(found?.enabled),
      start: normalizeTimeValue(found?.start || base.start),
      end: normalizeTimeValue(found?.end || base.end),
    }
  })
}

function sanitizeClosedDays(items) {
  if (!Array.isArray(items)) return []
  return Array.from(new Set(items
    .map((item) => String(item || '').trim())
    .filter((item) => /^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/.test(item))))
    .sort()
}

function normalizeTimeValue(value) {
  const text = String(value || '').trim()
  return /^\d{2}:\d{2}$/.test(text) ? text : '09:00'
}

function addClosedDay() {
  const value = String(newClosedDay.value || '').trim()
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    toast.error('Selecciona una fecha válida')
    return
  }

  if (!closedDays.value.includes(value)) {
    closedDays.value = [...closedDays.value, value].sort()
  }
  newClosedDay.value = ''
}

function addClosedDayRange() {
  const start = String(closedRangeStart.value || '').trim()
  const end = String(closedRangeEnd.value || '').trim()

  if (!/^\d{4}-\d{2}-\d{2}$/.test(start) || !/^\d{4}-\d{2}-\d{2}$/.test(end)) {
    toast.error('Selecciona un rango válido')
    return
  }

  if (start > end) {
    toast.error('La fecha Desde no puede ser mayor que Hasta')
    return
  }

  const next = new Set(closedDays.value)
  next.add(`${start}..${end}`)
  closedDays.value = Array.from(next).sort()
  closedRangeStart.value = ''
  closedRangeEnd.value = ''
}

function removeClosedDay(value) {
  closedDays.value = closedDays.value.filter((item) => item !== value)
}

function clearIndividualClosedDays() {
  closedDays.value = closedDays.value.filter((item) => item.includes('..'))
}

function clearRangeClosedDays() {
  closedDays.value = closedDays.value.filter((item) => !item.includes('..'))
}

function formatClosedDay(value) {
  if (value.includes('..')) {
    const [fromRaw, toRaw] = value.split('..')
    const from = new Date(`${fromRaw}T00:00:00`)
    const to = new Date(`${toRaw}T00:00:00`)
    if (Number.isNaN(from.getTime()) || Number.isNaN(to.getTime())) return value
    const fromText = from.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
    const toText = to.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
    return `Desde ${fromText} hasta ${toText}`
  }

  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function addCesionType() {
  cesionTypes.value.push(makeCesionType())
}

let _bonusKey = 0
function makeBonusType() {
  return { _key: ++_bonusKey, description: '', sessions: 1, price: 0, expires_at: '' }
}

function sanitizeBonusType(item) {
  const expiresRaw = (item?.expires_at ?? '').toString().trim()
  const expiresAt = /^\d{4}-\d{2}-\d{2}$/.test(expiresRaw)
    ? expiresRaw
    : (/^\d{4}-\d{2}-\d{2}T/.test(expiresRaw) ? expiresRaw.slice(0, 10) : '')

  return {
    id: item?.id,
    description: (item?.description ?? '').toString(),
    sessions: Number.isFinite(Number(item?.sessions)) ? Math.max(Number(item.sessions), 1) : 1,
    price: Number.isFinite(Number(item?.price)) ? Math.max(Number(item.price), 0) : 0,
    expires_at: expiresAt,
  }
}

function addBonusType() {
  bonusTypes.value.push(makeBonusType())
}

function removeBonusType(item) {
  if (item.id != null) {
    bonusTypes.value = bonusTypes.value.filter((b) => b !== item)
  } else {
    bonusTypes.value = bonusTypes.value.filter((b) => b !== item)
  }
}

function removeCesionType(id) {
  if (id == null) {
    cesionTypes.value.pop()
    return
  }
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

    const res = await api.post('/me/invoice-background/preview-pdf', formData, {
      responseType: 'blob',
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    const blob = new Blob([res.data], { type: 'application/pdf' })
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

function openCancelSubscriptionModal() {
  if (cancellingSubscription.value) return
  showCancelSubscriptionModal.value = true
}

function closeCancelSubscriptionModal() {
  if (cancellingSubscription.value) return
  showCancelSubscriptionModal.value = false
}

async function confirmCancelSubscription() {
  if (cancellingSubscription.value) return

  cancellingSubscription.value = true
  try {
    await api.post('/billing/cancel')
    toast.success('Suscripción cancelada correctamente')
    showCancelSubscriptionModal.value = false
    await load()
  } catch (e) {
    const message = e?.response?.data?.message || 'No se pudo cancelar la suscripción'
    toast.error(message)
  } finally {
    cancellingSubscription.value = false
  }
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
.section-copy { margin-top:8px; color:#6b7280; font-size:13px }

.profile-container {
  width: 100%;
  max-width: none;
}
.profile-shell { display:grid; gap:14px }
.card-stage { min-height:560px }
.tabs {
  display:grid;
  grid-template-columns:repeat(7, minmax(0, 1fr));
  gap:6px;
  margin-bottom:12px;
}
.tab {
  width: 100%;
  text-align: center;
  padding:8px 10px;
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
.subscription-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-top:4px }
.subscription-actions { display:flex; gap:8px; margin-top:0; margin-left:auto; flex-wrap:wrap }
  .subscription-meta { margin-top:10px; color:#374151; font-size:14px }
  .subscription-cancel-btn { background:#fff; border:1px solid #ef4444; color:#b91c1c }
  .subscription-cancel-btn:hover { background:#fef2f2 }
  .sub-menu-wrap { position:relative; display:inline-block }
  .sub-menu-trigger {
    padding:0;
    width:32px;
    height:32px;
    font-size:18px;
    line-height:1;
    background:#f9fafb;
    border:1px solid #d1d5db;
    color:#6b7280;
    border-radius:6px;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
  }
  .sub-menu-trigger:hover { background:#f3f4f6; color:#374151 }
  .sub-menu-dropdown {
    position:absolute;
    top:calc(100% + 4px);
    left:0;
    min-width:170px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:8px;
    box-shadow:0 4px 12px rgba(0,0,0,.1);
    z-index:100;
    overflow:hidden;
  }
  .sub-menu-item {
    display:block;
    width:100%;
    padding:9px 14px;
    font-size:13px;
    color:#374151;
    background:none;
    border:none;
    text-align:left;
    cursor:pointer;
    text-decoration:none;
  }
  .sub-menu-item:hover { background:#f3f4f6 }
  .sub-menu-item--danger { color:#b91c1c }
  .sub-menu-item--danger:hover { background:#fef2f2 }
  .confirm-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(17, 24, 39, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1200;
  }
  .confirm-modal {
    width: min(520px, calc(100vw - 32px));
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    padding: 16px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.22);
  }
  .confirm-modal h3 {
    margin: 0;
    font-size: 18px;
    color: #111827;
  }
  .confirm-modal p {
    margin: 10px 0 0;
    color: #4b5563;
    font-size: 14px;
    line-height: 1.45;
  }
  .confirm-modal-actions {
    margin-top: 14px;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
  }
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
.sesiones-table .cesion-col-description { width: 42%; }
.sesiones-table .cesion-col-time { width: 16%; }
.sesiones-table .cesion-col-price { width: 14%; }
.sesiones-table .cesion-col-payment { width: 18%; }
.sesiones-table .cesion-col-actions { width: 10%; }
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.hours-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}
.hours-table {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
}
.hours-table th,
.hours-table td {
  padding: 7px 8px;
  font-size: 12px;
}
.hours-table th:first-child,
.hours-table td:first-child {
  width: 90px;
}
.hours-row-label {
  font-weight: 700;
  color: #374151;
  white-space: nowrap;
}
.hours-cell-center {
  text-align: center;
}
.hours-table .counter-input {
  width: 100%;
  min-width: 0;
  max-width: none;
  padding: 7px 8px;
  font-size: 12px;
}
.day-toggle {
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-size:12px;
  color:#374151;
}
.closed-days-block {
  margin-top: 20px;
  display: grid;
  gap: 12px;
}
.closed-days-mode-row {
  display:flex;
  gap:8px;
  align-items:center;
}
.closed-days-mode {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:4px 10px;
  border:1px solid #dbeafe;
  color:#1d4ed8;
  background:#eff6ff;
  border-radius:9999px;
  font-size:12px;
  font-weight:600;
}
.closed-days-cal-grid {
  display:grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap:10px;
}
.closed-cal-card {
  border:1px solid #e5e7eb;
  border-radius:10px;
  padding:10px;
  display:grid;
  gap:8px;
  background:#fff;
}
.closed-range-grid {
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:8px;
}
.closed-controls-row {
  display:grid;
  gap:8px;
  align-items:end;
}
.closed-controls-row-single {
  grid-template-columns: minmax(0, 1fr) auto;
}
.closed-controls-row-range {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
}
.closed-card-actions {
  display:grid;
  grid-template-columns: repeat(2, minmax(42px, 1fr));
  gap:8px;
}
.closed-card-actions .btn {
  width:100%;
  min-width:42px;
}
.closed-days-picker-row {
  display:flex;
  gap:10px;
  align-items:center;
  flex-wrap:wrap;
}
.closed-day-input {
  width:100%;
  min-width: 0;
}
.closed-days-selected-block {
  display:grid;
  gap:8px;
}
.closed-days-list {
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.closed-day-chip {
  display:inline-flex;
  align-items:center;
  gap:8px;
  border:1px solid #dbeafe;
  background:#eff6ff;
  color:#1d4ed8;
  border-radius:9999px;
  padding:8px 12px;
  font-size:13px;
  cursor:pointer;
}
.closed-day-chip:hover {
  background:#dbeafe;
}
.plus-btn {
  min-width: 36px;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 1px solid #93c5fd;
  background: #eff6ff;
  color: #1d4ed8;
  border-radius: 9999px;
}
.plus-btn svg {
  width: 16px;
  height: 16px;
}
.plus-btn:hover {
  background: #dbeafe;
  border-color: #60a5fa;
}
.sesiones-list {
  display: grid;
  gap: 12px;
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
  .hours-table thead {
    display: table-header-group;
  }
  .hours-table,
  .hours-table tbody,
  .hours-table tr {
    display: table;
    width: 100%;
  }
  .hours-table th,
  .hours-table td {
    display: table-cell;
    width: auto;
  }
  .hours-table td::before {
    content: none;
  }
  .closed-day-input {
    width: 100%;
    min-width: 0;
  }
  .closed-days-cal-grid {
    grid-template-columns: 1fr;
  }
  .closed-range-grid {
    grid-template-columns: 1fr;
  }
  .closed-controls-row-single,
  .closed-controls-row-range {
    grid-template-columns: 1fr;
  }
  .closed-card-actions {
    grid-template-columns: 1fr 1fr;
  }
}

.color-palette {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}

.color-option {
  width: 100%;
  aspect-ratio: 1;
  border: 3px solid transparent;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.color-option:hover {
  transform: scale(1.08);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.color-option.selected {
  border-color: #111827;
  box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.1), 0 4px 12px rgba(0, 0, 0, 0.15);
}

.color-preview {
  font-size: 13px;
  color: #6b7280;
}

.color-preview span {
  font-size: 20px;
  margin: 0 4px;
}
</style>