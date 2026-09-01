<template>
  <div class="documents-page">
    <h1>Mis documentos</h1>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="documents.length === 0" class="empty-state">
      <p>No hay documentos disponibles</p>
    </div>

    <div v-else class="document-list">
      <div v-for="doc in documents" :key="doc.id" class="document-card">
        <div class="doc-info">
          <h3>{{ doc.type === 'invoice' ? 'Factura' : doc.type === 'abono' ? 'Abono' : 'Documento' }}</h3>
          <p class="doc-meta">{{ doc.counter }} — {{ new Date(doc.date).toLocaleDateString('es') }}</p>
        </div>
        <div class="doc-amount">€{{ doc.amount }}</div>
        <button class="download-btn" @click="handleDownload(doc)" :disabled="downloading === doc.id">
          {{ downloading === doc.id ? 'Descargando...' : 'Descargar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'

const documents = ref([])
const loading = ref(true)
const downloading = ref(null)

onMounted(async () => {
  try {
    const { data } = await patientApi.get('/documents')
    documents.value = data.documents
  } catch (e) {
    console.error('Error loading documents:', e)
  } finally {
    loading.value = false
  }
})

async function handleDownload(doc) {
  downloading.value = doc.id
  try {
    const response = await patientApi.get(`/documents/${doc.id}/download`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${doc.counter}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Error downloading document:', e)
  } finally {
    downloading.value = null
  }
}
</script>

<style scoped>
.documents-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.document-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.document-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.doc-info {
  flex: 1;
}

.doc-info h3 {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.doc-meta {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.doc-amount {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

.download-btn {
  padding: 8px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  color: #6366f1;
}

.download-btn:hover:not(:disabled) {
  background: #f0f4ff;
}
</style>
