<template>
  <div class="import-tab">
    <h2>Importar datos</h2>
    <div class="form-sub">
      Importa tus datos desde ficheros CSV. Disponible en los planes PRO y Enterprise. Cada fila del CSV genera un registro; las filas con errores se omiten y se muestran en el informe.
    </div>

    <div class="import-grid">
      <div v-for="card in cards" :key="card.entity" class="import-card">
        <div class="import-card-header">
          <div>
            <div class="import-card-title">{{ card.title }}</div>
            <div class="import-card-desc">{{ card.description }}</div>
          </div>
        </div>

        <div class="import-card-actions">
          <button type="button" class="btn btn-sm btn-outline" :disabled="uploading" @click="downloadTemplate(card.entity)">
            Plantilla
          </button>

          <input
            :id="'csv-' + card.entity"
            class="import-file-input"
            type="file"
            accept=".csv,.txt,text/csv,text/plain"
            @change="onCsvChange(card, $event)"
          />
          <label class="btn btn-sm btn-outline import-file-label" :for="'csv-' + card.entity">
            {{ selectedFiles[card.entity] ? 'Cambiar CSV' : 'Subir CSV' }}
          </label>

          <input
            v-if="card.needsZip"
            :id="'zip-' + card.entity"
            class="import-file-input"
            type="file"
            accept=".zip,application/zip"
            @change="onZipChange(card, $event)"
          />
          <label v-if="card.needsZip" class="btn btn-sm btn-outline import-file-label" :for="'zip-' + card.entity">
            {{ selectedZip[card.entity] ? 'Cambiar ZIP' : 'Subir ZIP' }}
          </label>

          <button type="button" class="btn btn-sm btn-primary" :disabled="!canImport(card)" @click="runImport(card)">
            <span v-if="uploadingEntity === card.entity">Importando...</span>
            <span v-else>Importar</span>
          </button>
        </div>

        <div v-if="selectedFiles[card.entity]" class="import-file-name">
          <span>CSV: {{ selectedFiles[card.entity].name }}</span>
          <span v-if="card.needsZip && selectedZip[card.entity]"> · ZIP: {{ selectedZip[card.entity].name }}</span>
        </div>

        <div v-if="uploadError && uploadingEntity === null && lastEntity === card.entity" class="import-error">
          {{ uploadError }}
        </div>

        <div v-if="report && report.entity === card.entity" class="import-report">
          <div class="import-score">
            Creados <b>{{ report.created }}</b>
            <span class="import-score-sep">·</span>
            Omitidos <b>{{ report.skipped }}</b>
            <span class="import-score-sep">·</span>
            Errores <b>{{ report.errors.length }}</b>
            <span class="import-score-sep">·</span>
            Avisos <b>{{ report.warnings.length }}</b>
          </div>

          <div v-if="report.errors.length" class="import-rows">
            <div v-for="(err, i) in report.errors" :key="'e' + i" class="import-row import-row-error">
              Fila {{ err.row }}: {{ err.message }}
            </div>
          </div>

          <div v-if="report.warnings.length" class="import-rows">
            <div v-for="(w, i) in report.warnings" :key="'w' + i" class="import-row import-row-warning">
              Fila {{ w.row }}: {{ w.message }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '../../services/api'

const toast = useToast()

const cards = [
  { entity: 'session-types', title: 'Tipos de sesión', description: 'Importa las cesiones (nombre, duración, precio y color). La columna "nombre" no puede repetirse.', needsZip: false },
  { entity: 'bonus-types', title: 'Tipos de bono', description: 'Crea plantillas de bonos multisesión con sus líneas (TipoSesion|cantidad|precio).', needsZip: false },
  { entity: 'patients', title: 'Pacientes', description: 'Importa pacientes. Cada fila necesita al menos NIF o email; los duplicados se omiten.', needsZip: false },
  { entity: 'clinical-histories', title: 'Historias clínicas', description: 'Crea la cita inicial de cada paciente (estado completado) con su historia clínica.', needsZip: false },
  { entity: 'patient-images', title: 'Imágenes de paciente', description: 'Sube un CSV con la columna imagen_1..imagen_n y un ZIP con las imágenes (máx. 6 por paciente, 200 KB cada una).', needsZip: true },
  { entity: 'products', title: 'Productos', description: 'Importa el catálogo de productos (referencia, precios, impuestos, familia y lote).', needsZip: false },
]

const selectedFiles = reactive({})
const selectedZip = reactive({})
const uploading = ref(false)
const uploadingEntity = ref(null)
const lastEntity = ref(null)
const report = ref(null)
const uploadError = ref(null)

function canImport(card) {
  if (uploading.value) return false
  if (!selectedFiles[card.entity]) return false
  if (card.needsZip && !selectedZip[card.entity]) return false
  return true
}

function onCsvChange(card, event) {
  const file = event.target.files?.[0]
  if (file) {
    selectedFiles[card.entity] = file
    clearReport(card.entity)
  }
}

function onZipChange(card, event) {
  const file = event.target.files?.[0]
  if (file) {
    selectedZip[card.entity] = file
    clearReport(card.entity)
  }
}

function clearReport(entity) {
  if (report.value && report.value.entity === entity) {
    report.value = null
  }
  if (lastEntity.value === entity) {
    lastEntity.value = null
    uploadError.value = null
  }
}

async function downloadTemplate(entity) {
  try {
    const res = await api.get(`/imports/${entity}/template`, { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const link = document.createElement('a')
    link.href = url
    link.download = `plantilla_${entity}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch {
    toast.error('No se pudo descargar la plantilla.')
  }
}

async function runImport(card) {
  const file = selectedFiles[card.entity]
  if (!file || uploading.value) return
  if (card.needsZip && !selectedZip[card.entity]) {
    toast.error('Selecciona también el fichero ZIP con las imágenes.')
    return
  }

  uploadingEntity.value = card.entity
  uploading.value = true
  uploadError.value = null
  report.value = null
  lastEntity.value = card.entity

  const form = new FormData()
  form.append('file', file)
  if (card.needsZip) {
    form.append('zip', selectedZip[card.entity])
  }

  try {
    const res = await api.post(`/imports/${card.entity}`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    report.value = res.data?.data || null
    toast.success('Importación completada.')
    selectedFiles[card.entity] = null
    if (card.needsZip) {
      selectedZip[card.entity] = null
    }
  } catch (error) {
    const message = error.response?.data?.message || 'No se pudo completar la importación.'
    uploadError.value = message
    toast.error(message)
  } finally {
    uploadingEntity.value = null
    uploading.value = false
  }
}
</script>

<style scoped>
.import-tab {
  display: grid;
  gap: 8px;
}

.import-tab h2 {
  margin: 0 0 4px;
  font-size: 18px;
}

.import-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 14px;
  margin-top: 12px;
}

.import-card {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px;
  background: #ffffff;
  display: grid;
  gap: 10px;
  align-content: start;
}

.import-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.import-card-title {
  font-weight: 600;
  font-size: 15px;
  color: #0f172a;
}

.import-card-desc {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
  line-height: 1.45;
}

.import-card-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}

.import-file-input {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.import-file-input:focus-visible + .import-file-label {
  outline: 2px solid var(--primary, #2563eb);
  outline-offset: 2px;
}

.import-file-name {
  font-size: 12px;
  color: #475569;
  overflow-wrap: anywhere;
}

.import-error {
  font-size: 13px;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 8px 10px;
}

.import-report {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px;
  background: #f8fafc;
  font-size: 12px;
  display: grid;
  gap: 6px;
}

.import-score {
  color: #334155;
}

.import-score b {
  color: #0f172a;
}

.import-score-sep {
  margin: 0 4px;
  color: #cbd5e1;
}

.import-rows {
  display: grid;
  gap: 4px;
  max-height: 180px;
  overflow-y: auto;
}

.import-row {
  padding: 4px 8px;
  border-radius: 6px;
}

.import-row-error {
  background: #fef2f2;
  color: #b91c1c;
}

.import-row-warning {
  background: #fffbeb;
  color: #92400e;
}
</style>