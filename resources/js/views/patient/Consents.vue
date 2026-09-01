<template>
  <div class="consents-page">
    <h1>Mis consentimientos</h1>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="consents.length === 0" class="empty-state">
      <p>No tienes consentimientos</p>
    </div>

    <div v-else class="consent-list">
      <div v-for="consent in consents" :key="consent.id" class="consent-card" @click="$router.push(`/patient/consents/${consent.id}`)">
        <div class="consent-info">
          <h3>{{ consent.template?.title || 'Consentimiento' }}</h3>
          <p class="consent-date">
            <span v-if="consent.status === 'sent'">Pendiente de firma</span>
            <span v-else-if="consent.status === 'signed'">Firmado el {{ new Date(consent.signed_at).toLocaleDateString('es') }}</span>
            <span v-else>{{ consent.status }}</span>
          </p>
        </div>
        <span class="consent-status" :class="consent.status">
          {{ consent.status === 'sent' ? '⚠️ Pendiente' : '✅ Firmado' }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'

const consents = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await patientApi.get('/consents')
    consents.value = data.consents
  } catch (e) {
    console.error('Error loading consents:', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.consents-page {
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

.consent-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.consent-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  cursor: pointer;
}

.consent-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.consent-info h3 {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.consent-date {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.consent-status {
  font-size: 13px;
  font-weight: 600;
}
</style>
