<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header" style="display:flex;justify-content:space-between;align-items:start">
          <div>
            <h1>Paciente e Historial</h1>
          </div>
          <div class="header-actions">
            <button v-if="!isProfessional" class="edit-btn" @click.prevent="goEdit">Editar</button>
            <div class="back-menu-group">
              <button class="muted back-btn" @click.prevent="goBack">Volver</button>
              <div class="quick-actions" ref="quickActionsRef">
                <button
                  type="button"
                  class="muted quick-trigger menu-right-btn"
                  @click="toggleQuickActions"
                  aria-label="Acciones"
                  title="Acciones"
                >
                  <svg class="quick-trigger-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="12" cy="5" r="1.8" fill="currentColor" />
                    <circle cx="12" cy="12" r="1.8" fill="currentColor" />
                    <circle cx="12" cy="19" r="1.8" fill="currentColor" />
                  </svg>
                </button>
                <div v-if="quickActionsOpen" class="quick-menu">
                  <button
                    type="button"
                    class="quick-item"
                    @click.prevent="runViewHistory"
                  >
                    Historia Clinica
                  </button>
                  <button
                    type="button"
                    class="quick-item"
                    @click.prevent="runAttachImages"
                  >
                    Imagenes
                  </button>
                  <BtnTrash v-if="!isProfessional" class="quick-item danger" :disabled="deletingPatient" @click.prevent="runDelete">{{ deletingPatient ? 'Eliminando...' : 'Eliminar' }}</BtnTrash>
                </div>
              </div>
            </div>
          </div>
        </div>
        <br>
        <div class="grid-display">
          <div class="card">
            <div class="card-row"><strong>Nombre: </strong>{{ patient?.counter ? `${patient.counter} · ` : '' }}{{ patient?.name ?? '—' }}</div>
            <div v-if="activeBonusCount > 0" class="mini-badge">{{ activeBonusCount }} bono(s) activo(s)</div>
          
          </div>

          <div class="card">
            <div class="card-row"><strong>NIF: </strong>{{ patient?.nif ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Teléfono: </strong>{{ patient?.phone ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Email: </strong>{{ patient?.email ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Edad: </strong>{{ patientAgeLabel }}</div>
          </div>

          <div class="card full">
            <div class="card-row"><strong>Notas</strong></div>
            <div class="card-row">{{ patient?.notes ?? '—' }}</div>
          </div>
        </div>

        <div class="history-grid">
          <div class="history-card">
            <div class="history-title" style="display:flex;justify-content:space-between;align-items:center">
              <div>Citas</div>
              <div style="display:flex;gap:8px;align-items:center">
                <button
                  class="toggle-canceled-btn"
                  @click.prevent="toggleCanceledVisibility"
                  :title="showCanceledAppointments ? 'Ver solo citas pendientes' : 'Ver todas las citas'"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.3-4.3"></path>
                  </svg>
                </button>
                <button v-if="!isProfessional" class="primary" @click.prevent="createAppointment" style="padding:6px 10px;font-size:13px">Crear</button>
              </div>
            </div>
            <div v-if="filteredAppointments && filteredAppointments.length"> 
              <ul>
                  <li v-for="a in filteredAppointments" :key="a.id" role="button" tabindex="0" @click.prevent="goToAppointment(a.id)" @keydown.enter.prevent="goToAppointment(a.id)" style="cursor:pointer">
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:nowrap">
                      <strong style="white-space:nowrap">{{ formatDateShort(a.start_time) }} {{ formatTime(a.start_time) }}</strong>
                      <span style="font-size:12px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ a.appointment_type?.description ?? '—' }}</span>
                      <span style="font-size:12px;color:#6b7280;white-space:nowrap">{{ a.professional?.name ?? '—' }}</span>
                      <span class="status" :class="a.status" style="flex-shrink:0;margin-left:auto">{{ statusLabel(a.status) }}</span>
                    </div>
                  </li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin citas</div>
          </div>

          <div class="history-card">
            <PatientBonuses v-if="patient && patient.id" :patientId="patient.id" @active-bonus-count="v => activeBonusCount = v" />
          </div>

          <div class="history-card">
            <div class="history-title" style="display:flex;justify-content:space-between;align-items:center">
              <div>Pagos</div>
              <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end">
                <button
                  class="toggle-canceled-btn"
                  @click.prevent="toggleCompletedPaymentsVisibility"
                  :title="showCompletedPayments ? 'Ver solo pagos a favor (anticipos)' : 'Ver todos los pagos'"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.3-4.3"></path>
                  </svg>
                </button>
                <button v-if="!isProfessional" class="primary" @click.prevent="createPayment" style="padding:6px 10px;font-size:13px">Crear</button>
              </div>
            </div>
            <div v-if="sortedPayments && sortedPayments.length">
              <div class="payments-total">Total: {{ formatPaymentAmount(totalPaymentsAmount) }}</div>
              <ul>
                <li v-for="pay in sortedPayments" :key="pay.id">
                  ({{ formatPaymentDate(pay.paid_at || pay.created_at) }}) - {{ formatPaymentAmount(pay.amount) }} {{ paymentMethodLabel(pay.method) }} {{ paymentConceptLabel(pay.concept) }} 
                </li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin pagos</div>
          </div>

          <div class="history-card">
            <PatientConsents v-if="patient && patient.id" :patientId="patient.id" />
          </div>
        </div>
      </div>

      <div v-if="attachImagesModalOpen" class="modal-backdrop" @click.self="closeAttachImagesModal">
        <div class="attach-modal" role="dialog" aria-modal="true" aria-label="Adjuntar archivos">
          <div class="attach-modal-head">
            <h3>Adjuntar archivos</h3>
            <button type="button" class="attach-modal-close" @click="closeAttachImagesModal" :disabled="uploadingImages">✕</button>
          </div>

          <p class="attach-modal-sub">Puedes cargar imágenes o PDF de hasta 200 KB por archivo, con un máximo total de 6 archivos por paciente.</p>

          <div v-if="attachImagesError" class="attach-modal-error">{{ attachImagesError }}</div>

          <div class="attach-section-title">Archivos cargados</div>
          <AppLoading v-if="loadingExistingImages" compact message="Cargando archivos..." />
          <div v-else-if="existingImages.length === 0" class="attach-empty">No hay archivos cargados.</div>
          <div v-else class="existing-images-grid">
            <div v-for="img in existingImages" :key="img.id" class="existing-image-tile">
              <div class="existing-image-preview-wrap">
                <img
                  v-if="isImageFile(img) && img.url"
                  :src="img.url"
                  alt="Archivo clínico"
                  class="existing-image-preview"
                  role="button"
                  tabindex="0"
                  title="Ver archivo"
                  @click="openImagePreview(img)"
                  @keydown.enter.prevent="openImagePreview(img)"
                />
                <button
                  v-else
                  type="button"
                  class="existing-file-fallback"
                  title="Ver archivo"
                  @click="openImagePreview(img)"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="existing-file-icon">
                    <path d="M7 3h8l4 4v14H7z"></path>
                    <path d="M15 3v4h4"></path>
                    <path d="M10 12h4"></path>
                    <path d="M10 16h6"></path>
                  </svg>
                  <span class="existing-file-kind">PDF</span>
                </button>
                <div class="existing-image-overlay">
                  <div class="existing-image-caption">{{ img.description || 'Sin descripción' }}</div>
                  <div class="existing-image-actions">
                    <button
                      type="button"
                      class="icon-action-btn overlay-btn"
                      :disabled="!img.url"
                      title="Ver archivo"
                      @click="openImagePreview(img)"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icon-action-svg">
                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                        <circle cx="12" cy="12" r="2.5"></circle>
                      </svg>
                    </button>
                    <button
                      type="button"
                      class="icon-action-btn danger overlay-btn"
                      :disabled="uploadingImages || deletingExistingImageId === img.id"
                      :title="deletingExistingImageId === img.id ? 'Eliminando...' : 'Eliminar archivo'"
                      @click="deleteExistingImage(img)"
                    >
                      <span v-if="deletingExistingImageId === img.id">…</span>
                      <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icon-action-svg">
                        <path d="M3 6h18"></path>
                        <path d="M8 6V4h8v2"></path>
                        <path d="M19 6l-1 14H6L5 6"></path>
                        <path d="M10 11v6M14 11v6"></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="attach-section-title" style="margin-top:12px">Cargar nuevos</div>
          <div class="attach-limit-note">{{ existingImages.length }} de {{ MAX_ATTACHMENTS_TOTAL }} archivos usados</div>

          <div class="attach-items">
            <div v-for="(row, index) in attachImageRows" :key="row.id" class="attach-row">
              <div class="attach-field">
                <label>Descripción</label>
                <input
                  v-model="row.description"
                  type="text"
                  class="attach-input"
                  placeholder="Ej.: Radiografía inicial"
                  :disabled="uploadingImages"
                />
              </div>

              <div class="attach-field">
                <label>Archivo</label>
                <input
                  :id="`patient-image-file-${row.id}`"
                  type="file"
                  class="attach-file-input"
                  accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf"
                  :disabled="uploadingImages"
                  @change="onAttachFileChange($event, index)"
                />
                <label
                  :for="`patient-image-file-${row.id}`"
                  class="attach-file-trigger"
                  :class="{ disabled: uploadingImages }"
                >
                  Seleccionar archivo
                </label>
                <div class="attach-file-name">{{ row.fileName || 'Sin archivo seleccionado' }}</div>
              </div>

              <BtnTrash class="attach-remove" :disabled="uploadingImages || attachImageRows.length === 1" @click="removeAttachRow(index)" title="Eliminar fila">Eliminar</BtnTrash>
            </div>
          </div>

          <div class="attach-actions">
            <button
              type="button"
              class="muted"
              :disabled="uploadingImages || remainingAttachSlots <= attachImageRows.length"
              @click="addAttachRow"
            >
              + Agregar archivo
            </button>
            <button type="button" class="primary" :disabled="uploadingImages" @click="submitAttachImages">
              {{ uploadingImages ? 'Subiendo...' : 'Subir archivos' }}
            </button>
          </div>
        </div>
      </div>

      <div v-if="imagePreviewOpen && selectedImage" class="image-preview-backdrop" @click.self="closeImagePreview">
        <div class="image-preview-modal" role="dialog" aria-modal="true" aria-label="Vista previa de archivo">
          <div class="image-preview-head">
            <div class="image-preview-title">{{ selectedImage.description || 'Archivo clínico' }}</div>
            <button type="button" class="attach-modal-close" @click="closeImagePreview">✕</button>
          </div>

          <div class="image-preview-body">
            <img v-if="isImageFile(selectedImage)" :src="selectedImage.url" :alt="selectedImage.description || 'Archivo clínico'" class="image-preview-full" />
            <iframe v-else :src="selectedImage.url" title="Vista previa PDF" class="pdf-preview-frame"></iframe>
          </div>

          <div class="image-preview-actions">
            <button type="button" class="preview-action-btn" title="Descargar archivo" @click="downloadSelectedImage">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="preview-action-icon">
                <path d="M12 4v11"></path>
                <path d="M8.5 11.5L12 15l3.5-3.5"></path>
                <path d="M5 19h14"></path>
              </svg>
            </button>
            <button type="button" class="preview-action-btn" title="Imprimir archivo" @click="printSelectedImage">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="preview-action-icon">
                <path d="M7 9V4h10v5"></path>
                <rect x="4" y="9" width="16" height="8" rx="2"></rect>
                <path d="M7 14h10v6H7z"></path>
                <path d="M16 12h.01"></path>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '../../layouts/MainLayout.vue'
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import PatientBonuses from '../../components/PatientBonuses.vue'
import PatientConsents from '../consents/PatientConsents.vue'
import { formatTime, formatDateShort, statusLabel } from '../../shared/appointmentHelpers'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import { goBackWithStack } from '../../shared/navigationHelpers'
import BtnTrash from '../../components/BtnTrash.vue'
import { isProfessional } from '../../shared/meCache'

const route = useRoute()
const router = useRouter()
const patient = ref(null)
const activeBonusCount = ref(0)
const appointments = ref([])
const packs = ref([])
const payments = ref([])
const loading = ref(false)
const deletingPatient = ref(false)
const showCanceledAppointments = ref(false)
const showCompletedPayments = ref(false)
const quickActionsOpen = ref(false)
const quickActionsRef = ref(null)
const attachImagesModalOpen = ref(false)
const attachImagesError = ref('')
const uploadingImages = ref(false)
const loadingExistingImages = ref(false)
const deletingExistingImageId = ref(null)
const existingImages = ref([])
const attachImageRows = ref([{ id: 1, description: '', file: null, fileName: '' }])
const imagePreviewOpen = ref(false)
const selectedImage = ref(null)
const MAX_ATTACHMENTS_TOTAL = 6
const MAX_FILE_SIZE_BYTES = 200 * 1024
const ALLOWED_UPLOAD_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf']

const existingAttachmentsCount = computed(() => existingImages.value.length)

const remainingAttachSlots = computed(() => Math.max(0, MAX_ATTACHMENTS_TOTAL - existingAttachmentsCount.value))

const filteredAppointments = computed(() => {
  if (showCanceledAppointments.value) return appointments.value
  return appointments.value.filter(a => a.status === 'scheduled' || a.status === 'rescheduled')
})

const filteredPayments = computed(() => {
  if (showCompletedPayments.value) return payments.value
  return payments.value.filter(p => p.concept === 'credit')
})

const sortedPayments = computed(() => {
  if (!filteredPayments.value || filteredPayments.value.length === 0) return []

  const toMs = (pay) => {
    const raw = pay?.paid_at || pay?.created_at
    if (!raw) return 0
    const ts = new Date(raw).getTime()
    return Number.isNaN(ts) ? 0 : ts
  }

  return [...filteredPayments.value].sort((a, b) => toMs(b) - toMs(a))
})

const totalPaymentsAmount = computed(() => {
  return sortedPayments.value.reduce((sum, pay) => sum + Number(pay?.amount || 0), 0)
})

const patientAgeLabel = computed(() => {
  const birthDate = patient.value?.birth_date
  if (!birthDate) return '—'

  const parsedBirthDate = new Date(`${birthDate}T00:00:00`)
  if (Number.isNaN(parsedBirthDate.getTime())) return '—'

  const today = new Date()
  let age = today.getFullYear() - parsedBirthDate.getFullYear()
  const hasHadBirthdayThisYear =
    today.getMonth() > parsedBirthDate.getMonth() ||
    (today.getMonth() === parsedBirthDate.getMonth() && today.getDate() >= parsedBirthDate.getDate())

  if (!hasHadBirthdayThisYear) age -= 1
  return age >= 0 ? `${age} años` : '—'
})

async function loadPatient() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/patients/${id}`)
    patient.value = res.data
    appointments.value = res.data.appointments || []
    packs.value = res.data.packs || []
    payments.value = res.data.payments || []
  } catch (e) {
    console.error('Error cargando paciente', e)
    // si 404, volver al listado
    if (e.response && e.response.status === 404) router.push('/patients')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPatient()
  document.addEventListener('click', handleClickOutsideQuickActions)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutsideQuickActions)
})

watch(() => route.params.id, (id) => {
  if (id) loadPatient()
})

function goEdit() {
  if (patient.value && patient.value.id) {
    router.push({ path: `/patients/${patient.value.id}/edit`, query: { from: 'show' } })
  }
}

function viewHistory() {
  if (patient.value && patient.value.id) {
    router.push({ path: `/patients/${patient.value.id}/history` })
  }
}

function attachImages() {
  if (!patient.value || !patient.value.id) return
  attachImagesError.value = ''
  attachImageRows.value = [createAttachRow()]
  attachImagesModalOpen.value = true
  loadPatientImages()
}

function createAttachRow() {
  return { id: Date.now() + Math.floor(Math.random() * 1000), description: '', file: null, fileName: '' }
}

function isPdfFile(fileLike) {
  return String(fileLike?.mime_type || fileLike?.file?.type || '').toLowerCase() === 'application/pdf'
}

function isImageFile(fileLike) {
  return String(fileLike?.mime_type || fileLike?.file?.type || '').toLowerCase().startsWith('image/')
}

function closeAttachImagesModal() {
  if (uploadingImages.value) return
  attachImagesModalOpen.value = false
  closeImagePreview()
}

function openImagePreview(image) {
  if (!image?.url) return
  selectedImage.value = image
  imagePreviewOpen.value = true
}

function closeImagePreview() {
  imagePreviewOpen.value = false
  selectedImage.value = null
}

function selectedImageDownloadName() {
  const description = String(selectedImage.value?.description || '').trim()
  const extension = isPdfFile(selectedImage.value) ? 'pdf' : 'jpg'
  if (description) {
    return `${description.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_-]/g, '') || 'archivo_clinico'}.${extension}`
  }

  const id = selectedImage.value?.id
  return id ? `archivo_clinico_${id}.${extension}` : `archivo_clinico.${extension}`
}

function downloadSelectedImage() {
  if (!selectedImage.value?.url) return
  const link = document.createElement('a')
  link.href = selectedImage.value.url
  link.download = selectedImageDownloadName()
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

function printSelectedImage() {
  if (!selectedImage.value?.url) return

  if (isPdfFile(selectedImage.value)) {
    const pdfWindow = window.open(selectedImage.value.url, '_blank')
    if (!pdfWindow) return
    pdfWindow.onload = () => {
      pdfWindow.focus()
      pdfWindow.print()
    }
    return
  }

  const printWindow = window.open('', '_blank', 'width=980,height=700')
  if (!printWindow) return

  const safeTitle = String(selectedImage.value?.description || 'Archivo clínico').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  const safeUrl = String(selectedImage.value.url).replace(/"/g, '&quot;')

  printWindow.document.write(`<!doctype html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>${safeTitle}</title>
    <style>
      body { margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #fff; }
      img { max-width: 100vw; max-height: 100vh; object-fit: contain; }
      @media print {
        body { margin: 0; }
      }
    </style>
  </head>
  <body>
    <img src="${safeUrl}" alt="${safeTitle}" />
  </body>
</html>`)
  printWindow.document.close()
  printWindow.focus()

  printWindow.onload = () => {
    printWindow.print()
  }
}

function addAttachRow() {
  if (remainingAttachSlots.value <= attachImageRows.value.length) return
  attachImageRows.value.push(createAttachRow())
}

function removeAttachRow(index) {
  if (attachImageRows.value.length === 1) return
  attachImageRows.value.splice(index, 1)
}

function onAttachFileChange(event, index) {
  const file = event?.target?.files?.[0] || null
  if (!file) {
    attachImageRows.value[index].file = null
    attachImageRows.value[index].fileName = ''
    return
  }

  attachImageRows.value[index].file = file
  attachImageRows.value[index].fileName = file.name
}

async function loadPatientImages() {
  if (!patient.value?.id) return

  loadingExistingImages.value = true
  try {
    const res = await api.get(`/patients/${patient.value.id}/images`)
    existingImages.value = Array.isArray(res?.data?.data) ? res.data.data : []

    if (remainingAttachSlots.value <= 0) {
      attachImageRows.value = [createAttachRow()]
    } else if (attachImageRows.value.length > remainingAttachSlots.value) {
      attachImageRows.value = attachImageRows.value.slice(0, remainingAttachSlots.value)
    }
  } catch (e) {
    existingImages.value = []
    attachImagesError.value = 'No se pudieron cargar los archivos existentes.'
  } finally {
    loadingExistingImages.value = false
  }
}

async function deleteExistingImage(image) {
  if (!patient.value?.id || !image?.id) return

  attachImagesError.value = ''
  deletingExistingImageId.value = image.id

  try {
    const confirm = await Swal.fire({
      title: 'Eliminar archivo',
      text: '¿Seguro que deseas eliminar este archivo?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
      customClass: { popup: 'swal-popup-warning-card' },
    })

    if (!confirm.isConfirmed) return

    await api.delete(`/patients/${patient.value.id}/images/${image.id}`)

    const toast = useToast()
    toast.success('Archivo eliminado correctamente', {
      toastClassName: 'toast-delete',
      progressClassName: 'toast-delete-progress',
    })

    await loadPatientImages()
  } catch (e) {
    attachImagesError.value = e?.response?.data?.message || 'No se pudo eliminar el archivo.'
  } finally {
    deletingExistingImageId.value = null
  }
}

function validateAttachRows() {
  if (attachImageRows.value.length === 0) {
    return 'Debes agregar al menos un archivo.'
  }

  if (existingAttachmentsCount.value + attachImageRows.value.length > MAX_ATTACHMENTS_TOTAL) {
    return 'Solo se permiten hasta 6 archivos por paciente.'
  }

  for (const row of attachImageRows.value) {
    const description = String(row.description || '').trim()
    if (!description) return 'Todos los archivos requieren descripción.'

    if (!row.file) return 'Todas las filas requieren un archivo.'

    if (!ALLOWED_UPLOAD_MIME_TYPES.includes(String(row.file.type || '').toLowerCase())) {
      return 'Solo se permiten imágenes o archivos PDF.'
    }

    if (Number(row.file.size || 0) > MAX_FILE_SIZE_BYTES) {
      return 'Cada archivo debe pesar como máximo 200 KB.'
    }
  }

  return ''
}

async function submitAttachImages() {
  if (!patient.value?.id) return

  const validationMessage = validateAttachRows()
  if (validationMessage) {
    attachImagesError.value = validationMessage
    return
  }

  attachImagesError.value = ''
  uploadingImages.value = true

  try {
    const formData = new FormData()

    attachImageRows.value.forEach((row, index) => {
      formData.append(`items[${index}][description]`, String(row.description || '').trim())
      formData.append(`items[${index}][file]`, row.file)
    })

    const res = await api.post(`/patients/${patient.value.id}/images`, formData)
    const toast = useToast()
    toast.success(res?.data?.message || 'Archivos adjuntados correctamente')
    attachImageRows.value = [createAttachRow()]
    await loadPatientImages()
  } catch (e) {
    attachImagesError.value = e?.response?.data?.message || 'No se pudieron adjuntar los archivos.'
  } finally {
    uploadingImages.value = false
  }
}

function toggleQuickActions() {
  quickActionsOpen.value = !quickActionsOpen.value
}

function closeQuickActions() {
  quickActionsOpen.value = false
}

function handleClickOutsideQuickActions(event) {
  if (!quickActionsOpen.value) return
  if (!quickActionsRef.value) return
  if (!quickActionsRef.value.contains(event.target)) {
    closeQuickActions()
  }
}

function runViewHistory() {
  closeQuickActions()
  viewHistory()
}

function runAttachImages() {
  closeQuickActions()
  attachImages()
}

function runDelete() {
  if (deletingPatient.value) return
  closeQuickActions()
  confirmDelete()
}

function goBack() {
  goBackWithStack(router, '/patients')
}

function goToAppointment(id) {
  if (!id) return
  router.push(`/appointments/${id}`)
}

function createAppointment() {
  if (!patient.value || !patient.value.id) return
  router.push({ path: '/appointments/create', query: { patient_id: patient.value.id } })
}

function toggleCanceledVisibility() {
  showCanceledAppointments.value = !showCanceledAppointments.value
}
function toggleCompletedPaymentsVisibility() {
  showCompletedPayments.value = !showCompletedPayments.value
}

function createPayment() {
  if (!patient.value || !patient.value.id) return
  router.push({ path: '/payments/create', query: { patient_id: patient.value.id } })
}

function paymentMethodLabel(method) {
  const map = {
    cash: 'efectivo',
    card: 'tarjeta',
    transfer: 'transferencia',
  }

  return map[method] || 'método no definido'
}

function paymentConceptLabel(concept) {
  const map = {
    credit: 'Anticipo',
    package: 'Bono',
    appointment: 'Simple',
  }

  return map[concept] || 'Motivo no definido'
}

function formatPaymentAmount(amount) {
  const n = Number(amount || 0)
  if (!Number.isFinite(n)) return '0€'
  if (Number.isInteger(n)) return `${n}€`
  return `${n.toFixed(2)}€`
}

function formatPaymentDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yy = String(d.getFullYear()).slice(-2)
  return `${dd}/${mm}/${yy}`
}

async function confirmDelete() {
  if (!patient.value || deletingPatient.value) return

  const res = await Swal.fire({
    title: `Eliminar paciente`,
    text: `¿Eliminar al paciente "${patient.value.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    customClass: { popup: 'swal-popup-warning-card' },
  })

  if (!res.isConfirmed) return

  deletingPatient.value = true
  try {
    await api.delete(`/patients/${patient.value.id}`)
    const toast = useToast()
    toast.success('Paciente eliminado', {
      toastClassName: 'toast-delete',
      progressClassName: 'toast-delete-progress',
    })
    // ir al listado
    router.push('/patients')
  } catch (e) {
    const msg = e.response?.data?.message || 'Error eliminando paciente'
    const toast = useToast()
    toast.error(msg)
  } finally {
    deletingPatient.value = false
  }
}
</script>

<style scoped>
/* Reusar estilos del formulario y mejorar visual */
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:960px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.header-actions { display:flex; gap:8px; align-items:center }
.quick-trigger { padding:11px 12px; display:inline-flex; align-items:center; justify-content:center }
.quick-trigger-icon { width:18px; height:18px; color:#4b5563 }
.quick-actions { position:relative }
.quick-menu { position:absolute; right:0; top:calc(100% + 6px); min-width:200px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 24px rgba(2,6,23,0.10); padding:6px; display:flex; flex-direction:column; gap:4px; z-index:30 }
.quick-item { text-align:left; padding:8px 10px; border:1px solid transparent; background:#fff; border-radius:8px; font-size:14px; color:#111827 }
.quick-item:hover { background:#f9fafb }
.quick-item.danger { color:#b91c1c }

.grid-display { display:grid; grid-template-columns: repeat(2,1fr); gap:12px }
.card { background:#fafafa; padding:5px; border-radius:10px; border:1px solid #eef2ff22 }
.card.full { grid-column:1 / -1 }
.card-row { margin-bottom:6px }

.history-grid { margin-top:18px; display:grid; grid-template-columns:repeat(2,1fr); gap:12px }
.history-card { background:#fff; padding:14px; border-radius:10px; border:1px solid #eef2ff; box-shadow: 0 6px 18px rgba(2,6,23,0.04) }
.history-title { font-weight:700; margin-bottom:8px }
.empty-card { padding:18px; border-radius:8px; border:2px dashed #e6e6e6; color:#6b7280; text-align:center; min-height:72px; display:flex; align-items:center; justify-content:center }

.history-card ul { list-style:none; padding:0; margin:0 }
.history-card li { padding:6px 0; border-bottom:1px dashed #f1f5f9; font-size:12px; color:#334155 }
.history-card li:last-child { border-bottom: none }
.payments-total { font-size:12px; font-weight:700; color:#0f172a; margin-bottom:6px }

.history-card .status { padding:4px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.canceled { background:#fff4f4; color:#da7a7a }
.status.scheduled { background:#eef2ff; color:#1e3a8a }
.status.rescheduled { background:#fffbeb; color:#b45309 }
.status.reprogrammed { background:#fffbeb; color:#b45309 }
.status.completed { background:#dcfce7; color:#166534 }

.primary { padding:8px 14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:600 }
.primary:hover { background:#eff6ff }

.toggle-canceled-btn {
  width:32px;
  height:32px;
  border-radius:9999px;
  border:1px solid #fca5a5;
  color:#f87171;
  background:transparent;
  font-size:13px;
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:0.9;
}

.toggle-canceled-btn:hover {
  background:transparent;
  border-color:#f87171;
  color:#ef4444;
}

@media (max-width: 900px) {
  .history-grid { grid-template-columns: 1fr }
  .grid-display { grid-template-columns: 1fr }
}

.mini-badge { display:inline-block; margin-top:6px; padding:6px 10px; background:#ecfdf5; color:#065f46; border-radius:9999px; font-size:13px; font-weight:700 }

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 90;
  padding: 16px;
}

.attach-modal {
  width: 100%;
  max-width: 780px;
  max-height: 85vh;
  overflow: auto;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 24px 48px rgba(2, 6, 23, 0.2);
}

.attach-modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.attach-modal-head h3 { margin: 0; font-size: 18px }
.attach-modal-close { border: none; background: transparent; font-size: 18px; cursor: pointer; color: #64748b }
.attach-modal-sub { margin: 0 0 10px; color: #64748b; font-size: 13px }
.attach-modal-error { margin-bottom: 10px; background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; padding:8px 10px; border-radius:8px; font-size:13px }
.attach-limit-note { margin: 0 0 10px; font-size: 12px; color: #64748b }

.attach-items { display: flex; flex-direction: column; gap: 10px }
.attach-row { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px; display: grid; grid-template-columns: 1.2fr 1.2fr auto; gap: 10px; align-items: end }
.attach-field { display: flex; flex-direction: column; gap: 6px }
.attach-field label { font-size: 13px; font-weight: 600; color: #334155 }
.attach-input { width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; box-sizing: border-box }
.attach-file-input { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0 }
.attach-file-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 20px;
  padding: 2px 10px;
  border-radius: 9999px;
  border: 1px solid #3b82f6;
  color: #3b82f6;
  background: #fff;
  font-weight: 300;
  font-size: 10px;
  cursor: pointer;
  box-sizing: border-box;
}
.attach-file-trigger:hover { background: #eff6ff }
.attach-file-trigger.disabled { opacity: 0.55; cursor: not-allowed; pointer-events: none }
.attach-file-name { font-size: 12px; color: #64748b }
.attach-remove { border: 1px solid #fecaca; background: #fff1f2; color: #be123c; border-radius: 8px; padding: 10px; font-size: 13px; cursor: pointer }

.attach-section-title { font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 8px }
.attach-empty { font-size: 13px; color: #64748b; padding: 8px 0 }
.existing-images-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:10px; margin-bottom:10px }
.existing-image-tile { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#f8fafc }
.existing-image-preview-wrap { position:relative; width:100%; aspect-ratio:1 / 1; overflow:hidden; background:#f8fafc }
.existing-image-preview { width: 100%; height: 100%; object-fit: cover; display: block }
.existing-image-preview:hover { opacity: 0.9 }
.existing-file-fallback {
  width: 100%;
  height: 100%;
  border: none;
  background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
  color: #1d4ed8;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
}
.existing-file-icon { width: 36px; height: 36px; display: block }
.existing-file-kind { font-size: 12px; font-weight: 700; letter-spacing: 0.08em }
.existing-image-overlay {
  position:absolute;
  inset:auto 0 0 0;
  display:flex;
  align-items:flex-end;
  justify-content:space-between;
  gap:8px;
  padding:10px;
  background:linear-gradient(180deg, rgba(15,23,42,0) 0%, rgba(15,23,42,0.78) 72%, rgba(15,23,42,0.9) 100%);
}
.existing-image-caption {
  flex:1;
  font-size:12px;
  color:#f8fafc;
  line-height:1.3;
  overflow:hidden;
  line-clamp:2;
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
}
.existing-image-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-shrink:0 }
.icon-action-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; color:#334155; display:inline-flex; align-items:center; justify-content:center; cursor:pointer }
.icon-action-btn:hover:not(:disabled) { background:#f8fafc }
.icon-action-btn:disabled { opacity:0.45; cursor:not-allowed }
.icon-action-btn.danger { color:#be123c; border-color:#fecaca; background:#fff1f2 }
.icon-action-btn.danger:hover:not(:disabled) { background:#ffe4e6 }
.icon-action-svg { width:14px; height:14px; display:block }
.overlay-btn { border-color: rgba(255,255,255,0.18); background: rgba(255,255,255,0.14); color:#fff; backdrop-filter: blur(6px) }
.overlay-btn:hover:not(:disabled) { background: rgba(255,255,255,0.24) }
.overlay-btn.danger { border-color: rgba(254,202,202,0.4); background: rgba(190,24,93,0.2); color:#fff }
.overlay-btn.danger:hover:not(:disabled) { background: rgba(190,24,93,0.32) }

.attach-actions { margin-top: 12px; display: flex; justify-content: space-between; align-items: center; gap: 8px }

.image-preview-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(2, 6, 23, 0.72);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 120;
  padding: 16px;
}

.image-preview-modal {
  width: min(980px, 100%);
  max-height: calc(100vh - 32px);
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 24px 56px rgba(2, 6, 23, 0.45);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.image-preview-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
}

.image-preview-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}

.image-preview-body {
  padding: 10px;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
}

.image-preview-full {
  max-width: 100%;
  max-height: calc(100vh - 220px);
  object-fit: contain;
  border-radius: 8px;
}
.pdf-preview-frame {
  width: min(900px, 100%);
  height: calc(100vh - 220px);
  border: none;
  border-radius: 8px;
  background: #fff;
}

.image-preview-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px;
  border-top: 1px solid #e2e8f0;
}

.preview-action-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  color: #2563eb;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.preview-action-btn:hover {
  background: #dbeafe;
  border-color: #93c5fd;
}

.preview-action-icon {
  width: 16px;
  height: 16px;
  display: block;
}

@media (max-width: 768px) {
  .attach-row { grid-template-columns: 1fr; }
  .existing-image-preview-wrap { aspect-ratio: 4 / 3; }
  .image-preview-full { max-height: calc(100vh - 240px); }
  .pdf-preview-frame { height: calc(100vh - 240px); }
}
</style>
