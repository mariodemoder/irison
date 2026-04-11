<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <div>
            <h1>{{ isEdit ? 'Editar cita' : 'Nueva cita' }}</h1>
            
          </div>
          <button type="button" class="muted back-btn" @click.prevent="cancel">Volver</button>
        </div>

        <form class="grid-form" @submit.prevent="submit">
          <div class="field">
            <label class="label">Paciente</label>
              <div style="display:flex; gap:12px; align-items:flex-start; width:100%">
              <select v-model="form.patient_id" @change="onPatientChange" class="input" :disabled="isCanceled && mode !== 'reprogram'" style="flex:1">
                <option value="" disabled>Selecciona un paciente</option>
                <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.counter ? (`${p.counter} · `) : '' }}{{ p.name }}{{ p.nif ? (' — ' + p.nif) : '' }}</option>
                <option value="__create">+ Crear paciente...</option>
              </select>
              <button v-if="form.patient_id && form.patient_id !== '__create'" type="button" class="muted" @click.prevent="goToPatient(form.patient_id)" title="Ir a la ficha del paciente" style="height:40px;padding:8px 10px;border-radius:8px;align-self:center">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </button>
            </div>
            <div v-if="errors.patient_id" class="field-error">{{ errors.patient_id[0] }}</div>
          </div>
          <div class="field">
            <label class="label">Estado</label>
            <div class="status-select-wrap">
              <select v-model="form.status" class="input" :disabled="isCanceled && mode !== 'reprogram'">
                <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ statusOptionDot(opt.value) }} {{ opt.label }}</option>
              </select>
            </div>
            <div v-if="errors.status" class="field-error">{{ errors.status[0] }}</div>
          </div>

    

          <div class="full tab-bar">
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'session' }" @click="activeTab = 'session'">
              Sesión
            </button>
            <button type="button" class="tab-btn" :class="{ active: activeTab === 'billing' && !appointmentInvoiceId }" @click="handleBillingTabClick">
              {{ appointmentInvoiceId ? 'Ver Factura' : 'Facturar' }}
            </button>
            <button type="button" class="tab-btn" :class="[{ active: activeTab === 'payment' }, { 'tab-btn-success': isPaidAppointment }]" @click="activeTab = 'payment'">
              {{ isPaidAppointment ? 'Pago Realizado' : 'Registrar Pago' }}
            </button>
          </div>

          <template v-if="activeTab === 'session'">
          <div class="full tab-content-card">
            <div class="tab-content-grid">
          <div class="field" v-if="activeTab === 'session'">
            <label class="label">Tipo</label>
            <select v-model="form.app_type_id" class="input" :disabled="isCanceled && mode !== 'reprogram'">
              <option value="">Selecciona un tipo</option>
              <option value="__custom">Otro (escribir)</option>
              <option v-for="type in appointmentTypes" :key="type.id" :value="String(type.id)">
                {{ type.description || `Tipo #${type.id}` }}
              </option>
            </select>
            <div v-if="errors.app_type_id" class="field-error">{{ errors.app_type_id[0] }}</div>
          </div>
          <div class="field" v-if="isCustomAppointmentType">
            <label class="label">Tipo personalizado</label>
            <input v-model="form.custom_type" type="text" class="input" placeholder="Ej: Seguimiento post-operatorio" :disabled="isCanceled && mode !== 'reprogram'" />
            <div v-if="errors.custom_type" class="field-error">{{ errors.custom_type[0] }}</div>
          </div>
          <div class="field">
            <label class="label">Inicio</label>
            <div class="datetime-pair">
              <input v-model="startDateModel" type="date" class="input" :disabled="isCanceled && mode !== 'reprogram'" />
              <select v-model="startTimeModel" class="input" :disabled="isCanceled && mode !== 'reprogram'">
                <option value="" disabled>Hora</option>
                <option v-for="opt in timeOptions" :key="'s'+opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div v-if="errors.start_time" class="field-error">{{ errors.start_time[0] }}</div>
          </div>
          <div class="field" v-if="hasSelectedAppointmentType">
            <label class="label">Duración</label>
            <input :value="appointmentTypeDurationLabel" type="text" class="input" disabled />
          </div>
          <div class="field">
            <label class="label">Fin</label>
            <div class="datetime-pair">
              <input v-model="endDateModel" type="date" class="input" :disabled="isCanceled && mode !== 'reprogram'" />
              <select v-model="endTimeModel" class="input" :disabled="isCanceled && mode !== 'reprogram'">
                <option value="" disabled>Hora</option>
                <option v-for="opt in timeOptions" :key="'e'+opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div v-if="errors.end_time" class="field-error">{{ errors.end_time[0] }}</div>
            <div v-if="calendarErrorMessage" class="calendar-inline-alert">{{ calendarErrorMessage }}</div>
            <div v-if="overlapping.length">
              <ul class="overlap-list">
                <li v-for="a in overlapping" :key="a.id" class="overlap-item">
                  <div style="display:flex; gap:8px; align-items:center;">
                    <div style="flex:1">
                      <div class="overlap-alert-subtle">La franja horaria se solapa con esta cita.</div>
                      {{ formatDate(a.start_time) }} - {{ formatDate(a.end_time) }}
                      <div style="color:#374151">{{ a.patient?.counter ? (`${a.patient.counter} · `) : '' }}{{ a.patient?.name || a.patient_name || 'Paciente desconocido' }}</div>
                    </div>
                    <div>
                      <button type="button" class="muted" @click.prevent="goToAppointment(a.id)">Ir a cita</button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>
        <div class="field" v-if="hasSelectedPatient">
                    <label class="label">Precio</label>
                    <input v-model.number="form.price" type="number" min="0.01" step="0.01" class="input" style="max-width:220px" required />
                    <div v-if="isCustomAppointmentType" class="field-help">Precio manual para tipo no tipificado.</div>
                  </div>
          <div class="field full">
            <label class="label">Notas</label>
            <textarea v-model="form.notes" class="textarea" rows="4" :disabled="isCanceled && mode !== 'reprogram'"></textarea>
            <div v-if="errors.notes" class="field-error">{{ errors.notes[0] }}</div>
          </div>

          <div v-if="hasSelectedPatient && form.start_time" class="field full">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; color:#374151;">
                <input type="checkbox" v-model="sendWhatsapp" style="width:16px;height:16px;" />
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366" style="flex-shrink:0"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                <span>Enviar recordatorio por WhatsApp</span>
              </label>
              <button
                v-if="whatsappReminderMessage"
                type="button"
                class="muted"
                @click.prevent="copyReminderToClipboard"
                :title="reminderCopied ? 'Copiado!' : 'Copiar mensaje de recordatorio'"
                style="display:flex;align-items:center;gap:6px;padding:6px 10px;font-size:13px;"
              >
                <svg v-if="!reminderCopied" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                {{ reminderCopied ? 'Copiado' : 'Copiar recordatorio' }}
              </button>
            </div>
          </div>
            </div>
          </div>
          </template>

          <template v-if="activeTab === 'billing'">
          <div class="full tab-content-card">
            <div class="tab-content-grid">
          <div class="field full" v-if="hasSelectedPatient">
            <label class="label">Datos de Facturación</label>
            <div class="billing-preview-box">
              <div class="billing-preview-grid">
                <div><strong>Nombre:</strong> {{ selectedPatient?.counter ? (`${selectedPatient.counter} · `) : '' }}{{ selectedPatient?.name || '—' }}</div>
                <div><strong>NIF:</strong> {{ selectedPatient?.nif || '—' }}</div>
                <div><strong>Domicilio:</strong> {{ selectedPatient?.address || '—' }}</div>
                <div><strong>Teléfono:</strong> {{ selectedPatient?.phone || '—' }}</div>
              </div>

              <div class="billing-preview-detail"><strong>Detalle:</strong> {{ billingDetailLabel }}</div>
              <div class="billing-preview-amount"><strong>Importe:</strong> {{ billingAmountLabel }}</div>
            </div>

            <div v-if="!isCoveredByBonus" style="margin-top:12px; display:flex; flex-direction:column; gap:6px; width:100%">
              <label class="label">Detalle de facturación</label>
              <textarea
                v-model="invoiceNotesDraft"
                class="textarea"
                rows="2"
                style="width:100%; box-sizing:border-box"
                placeholder="Detalle que aparecerá en la factura (deja vacío para usar las notas de la cita)"
                :disabled="isCanceled && mode !== 'reprogram'"
              ></textarea>
            </div>

            <div v-if="isEdit && !isCoveredByBonus" style="margin-top:10px; display:flex; gap:8px; align-items:center;">
              <button
                type="button"
                class="primary"
                @click.prevent="emitInvoice"
                :disabled="issuingInvoice"
              >
                {{ appointmentInvoiceId ? 'Ver factura' : (issuingInvoice ? 'Emitiendo...' : 'Emitir Factura') }}
              </button>
            </div>

            <div v-else-if="isEdit && isCoveredByBonus" class="billing-bonus-info">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
                <path d="M7 3h8l4 4v14H7z"></path>
                <path d="M15 3v4h4"></path>
                <path d="M10 12h6M10 16h6"></path>
              </svg>
              <span>Factura gestionada por el bono.</span>
              <button
                v-if="bonusInvoiceId"
                type="button"
                class="billing-bonus-link"
                @click.prevent="goToBonusInvoice"
              >
                Ver factura
              </button>
              <span v-else class="billing-bonus-muted">Sin factura de bono emitida</span>
            </div>
          </div>

          <div class="field full" v-else>
            <div class="alert-subtle">Selecciona un paciente para ver los datos de facturación.</div>
          </div>
            </div>
          </div>
          </template>

          <template v-if="activeTab === 'payment'">
          <div class="full tab-content-card">
            <div class="tab-content-grid">
          <div v-if="isPaidAppointment" class="field full">
            <div class="alert-subtle">Cita Completamente Paga</div>
          </div>

          <template v-if="!isPaidAppointment">
          <div class="field" v-if="hasSelectedPatient">
            <label class="label">Importe Pendiente</label>
            <input :value="appointmentPendingPaymentAmount.toFixed(2)" type="number" step="0.01" class="input" disabled />
          </div>

          <div class="field" v-if="hasSelectedPatient">
            <label class="label">Forma de pago</label>
              <div style="display:flex; gap:12px; align-items:center">
              <label style="display:flex; align-items:center; gap:8px"><input type="radio" v-model="form.payment_type" value="single" /> Simple</label>
              <label style="display:flex; align-items:center; gap:8px"><input type="radio" v-model="form.payment_type" value="bonus" :disabled="!hasAvailableBonuses" /> Usar bono</label>
            </div>
            <div v-if="form.patient_id && form.payment_type === 'bonus' && hasAvailableBonuses" class="inline-alert" style="margin-top:8px">
              <div>Con bonos disponibles</div>
            </div>
            <div v-if="form.patient_id && form.patient_id !== '__create' && form.payment_type === 'single' && availableCredit <= 0" class="inline-alert" style="margin-top:8px">
              <div>Sin adelantos pendientes</div>
            </div>
          </div>
          <div class="field full" v-if="form.payment_type === 'credit'">
            <label class="label">Adelanto pendiente</label>
            <AppLoading v-if="pendingCreditPaymentsLoading" compact message="Cargando adelantos pendientes..." />
            <div v-else>
              <div v-if="pendingCreditPayments.length === 0" class="alert-subtle">
                <div>No hay adelantos pendientes para este paciente.</div>
              </div>
              <select v-else v-model="form.use_credit_payment_id" class="input" style="width:100%">
                <option value="" disabled>Selecciona un adelanto pendiente</option>
                <option v-for="pay in pendingCreditPayments" :key="pay.id" :value="String(pay.id)">
                  {{ pay.counter }} — Pendiente {{ Number(creditPendingAmountOf(pay) || 0).toFixed(2) }}€ — {{ creditMethodLabel(pay.method) }}
                </option>
              </select>

              <div v-if="selectedPendingCreditPayment" style="margin-top:8px; display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:end;">
                <div class="field" style="margin:0;">
                  <label class="label">Importe a favor pendiente</label>
                  <input :value="Number(selectedPendingCreditRemainingAfterSessionAmount || 0).toFixed(2)" type="number" step="0.01" class="input" disabled />
                </div>

                <div v-if="!isEdit" class="field" style="margin:0;">
                  <label class="label">Estado de pago</label>
                  <div class="input" style="display:flex;align-items:center;background:#f8fafc">
                    <span>{{ paymentStatusLabel }}</span>
                  </div>
                </div>
              </div>

              <div v-if="errors.use_credit_payment_id" class="field-error">{{ errors.use_credit_payment_id[0] }}</div>
              <div v-if="errors.price" class="field-error">{{ errors.price[0] }}</div>
            </div>
          </div>

          <div v-if="!isEdit && hasSelectedPatient && form.payment_type !== 'credit'" class="field">
           <div v-if="!isCanceled" class="field">
            <div class="value">
              <span class="payment-badge" :class="paymentStatusClass">{{ paymentStatusLabel }}</span>
            </div>
          </div>
          </div>

          <div class="field full" v-if="form.payment_type === 'single' && form.patient_id && form.patient_id !== '__create' && availableCredit > 0" style="background:#fef3c7; border:1px solid #fcd34d; border-radius:12px; padding:16px; margin:8px 0">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px">
              <div>
                <div style="display:flex; align-items:center; gap:8px; font-size:16px; font-weight:700; color:#92400e">
                  <span>Crédito disponible</span>
                  <span>Saldo a favor: {{ availableCredit.toFixed(2) }}€</span>
                </div>
              </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px">
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                <input type="checkbox" v-model="form.apply_credit" /> <span style="color:#92400e; font-weight:500">Aplicar crédito en esta cita</span>
              </label>

              <div v-if="form.apply_credit" style="display:flex; flex-direction:column; gap:12px; padding-top:8px; border-top:1px solid #fcd34d">
                <div style="display:flex; flex-direction:column; gap:10px">
                  <div style="display:flex; align-items:flex-start; gap:8px">
                    <label style="display:flex; align-items:center; gap:6px; color:#92400e; cursor:pointer"><input type="radio" v-model="form.apply_credit_mode" value="auto" /> Aplicar Automático</label>
                  </div>
                  <div style="margin-left:24px; font-size:12px; color:#7c2d12; line-height:1.4">
                    Se usará el crédito a favor disponible para cancelar el importe pendiente de pago.
                  </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px">
                  <div style="display:flex; align-items:center; gap:8px">
                    <label style="display:flex; align-items:center; gap:6px; color:#92400e; cursor:pointer"><input type="radio" v-model="form.apply_credit_mode" value="manual" /> Aplicar Específico</label>
                  </div>
                  <div style="margin-left:24px; font-size:12px; color:#7c2d12; line-height:1.4">
                    Se usará un monto ingresado para cancelar el importe pendiente de pago.
                  </div>
                  <input
                    v-if="form.apply_credit_mode === 'manual'"
                    v-model="form.apply_credit_amount"
                    type="number"
                    min="0.01"
                    :max="availableCredit"
                    step="0.01"
                    class="input"
                    style="max-width:180px; background:#fff; border:1px solid #fbbf24; margin-left:24px"
                    placeholder="Importe a aplicar"
                  />
                </div>
                <div v-if="form.apply_credit_mode === 'manual' && Number(form.apply_credit_amount) > availableCredit && form.apply_credit_amount" style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#b91c1c; font-size:13px">
                  El importe no puede ser mayor a {{ availableCredit.toFixed(2) }}€
                </div>
                <div v-if="errors.apply_credit_error && errors.apply_credit_error[0]" style="padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#b91c1c; font-size:13px">
                  {{ errors.apply_credit_error[0] }}
                </div>
                <div v-if="applyCreditConfirmed" style="padding:8px 12px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; color:#065f46; font-size:13px">
                  El adelanto quedó listo para aplicarse al guardar la cita.
                </div>
                <button type="button" style="align-self:flex-start; padding:8px 16px; background:#f59e0b; border:none; border-radius:8px; color:#fff; font-weight:600; cursor:pointer; font-size:14px" @click.prevent="confirmApplyCredit">Aplicar Crédito</button>
              </div>
            </div>
          </div>

          <div v-if="form.payment_type === 'bonus'" class="field full">
            <label class="label">Bono</label>
            <AppLoading v-if="bonusesLoading" compact message="Cargando bonos..." />
            <div v-else>
              <div v-if="selectableBonuses.length === 0" class="alert-subtle">
                <div>No hay bonos activos para este paciente.</div>
              </div>
              <select v-model="form.use_bonus_id" @change="onBonusSelectChange" class="input" style="width:100%">
                <option value="" disabled>Selecciona un bono</option>
                <option value="__create_bonus">+ Crear bono...</option>
                <option v-if="selectableBonuses.length === 0" value="" disabled>No hay bonos disponibles</option>
                <option v-for="b in selectableBonuses" :key="b.id" :value="b.id">
                  {{ b.name ? (b.name + ' — ') : '' }}{{ b.total_sessions }} sesiones — {{ b.remaining_sessions }} restantes — {{ Number(b.price || 0).toFixed(2) }}€{{ b.expires_at ? (' — expira ' + formatDMY(b.expires_at)) : '' }}
                </option>
              </select>
              <div v-if="selectedBonus && selectedBonusSessionPrice > 0" class="hint-text" style="margin-top:6px;">
                Precio por sesión aplicado: {{ selectedBonusSessionPrice.toFixed(2) }}€ ({{ Number(selectedBonus.price || 0).toFixed(2) }}€ / {{ selectedBonus.total_sessions }} sesiones)
              </div>
              <div v-if="errors.use_bonus_id || errors.bonus_id" class="field-error">{{ (errors.use_bonus_id || errors.bonus_id)[0] }}</div>
            </div>

            <label class="label" style="margin-top:8px">Notas (bono)</label>
            <input v-model="form.bonus_notes" class="input" />
          </div>

          <div class="field" style="display:flex; justify-content:flex-end; gap:8px;">
            <div v-if="isEdit && form.payment_type === 'single' && !form.apply_credit && !applyCreditConfirmed">
              <button type="button" class="muted" @click.prevent="handleSinglePayment" :disabled="submitting">Registrar Pago Simple</button>
            </div>
          </div>
          </template>

          <div v-if="isEdit" class="field full">
            <label class="label">Historial de Pagos</label>
            <div v-if="sortedAppointmentPayments.length === 0" class="alert-subtle">
              <div>Cita sin pagos asociados.</div>
            </div>
            <div v-else class="appointment-payments-list">
              <div v-for="entry in sortedAppointmentPayments" :key="entry._key" class="appointment-payment-item">
                <div class="appointment-payment-main">
                  <span v-if="entry.counter" class="appointment-payment-counter">{{ entry.counter }}</span>
                  <span>{{ formatAppointmentPaymentDate(entry.paid_at || entry.created_at) }}</span>
                  <span>{{ formatAppointmentPaymentAmount(entry.amount) }}</span>
                  <span v-if="entry._type === 'payment' || entry._type === 'bonus_payment'">{{ appointmentPaymentConceptLabel(entry.concept) }}</span>
                  <span v-if="entry._type === 'payment' || entry._type === 'bonus_payment'">{{ appointmentPaymentMethodLabel(entry.method) }}</span>
                  
                </div>
                <span
                  class="appointment-payment-status"
                  :class="entry._type === 'credit_usage' ? 'credit' : paymentApplicationStatusClass(entry)"
                >
                  {{ entry._type === 'credit_usage'
                    ? 'Crédito aplicado'
                    : paymentApplicationStatusLabel(entry)
                  }}
                </span>
              </div>
            </div>
          </div>

          <div v-if="form.payment_type === 'credit' && selectedPendingCreditPayment" class="field full">
            <div class="bonus-details" style="margin-top:8px;padding:8px;border-radius:8px;background:#fffaf0;border:1px solid #ffedd5;color:#92400e;font-size:13px">
              <div><strong>Adelanto seleccionado</strong></div>
              <div>Importe: {{ Number(selectedPendingCreditPayment.amount || 0).toFixed(2) }}€</div>
              <div>Importe a favor pendiente: {{ Number(selectedPendingCreditRemainingAfterSessionAmount || 0).toFixed(2) }}€</div>
              <div>Método: {{ creditMethodLabel(selectedPendingCreditPayment.method) }}</div>
            </div>
          </div>
            </div>
          </div>
          </template>

          <div v-if="errors.general && errors.general[0]" class="field full" style="background:#fef2f2;border:1px solid #fecaca;padding:10px 14px;border-radius:8px;color:#b91c1c;font-size:13px">
            {{ errors.general[0] }}
          </div>

          <div class="actions full action-row">
            <div class="left-actions">
              <button class="primary" type="submit" :disabled="submitting || !canSaveAppointment">Guardar</button>
              <button v-if="isEdit && isFutureAppointment" type="button" class="muted" @click.prevent="startReprogram" :disabled="submitting">Reprogramar</button>
            </div>

            <div class="right-actions">
              <button v-if="isEdit && !isCanceled && !isCompletedAppointment" type="button" class="muted" @click.prevent="appointmentCancel" :disabled="cancelling">
                <IconCancel />
                Cancelar Cita
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>



  </MainLayout>
</template>


<script setup>
import { reactive, ref, onMounted, watch, computed, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import IconCancel from '../../components/icons/IconCancel.vue'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import { formatDate, toDatetimeLocalValue } from '../../shared/appointmentHelpers'
import { formatDMY } from '../../shared/dateHelpers'
import {
  openCreatePatientPopup as sharedOpenCreatePatientPopup,
  checkOverlapShared,
  goBack as sharedGoBack,
  startReprogramShared,
  appointmentCancelShared
} from '../../shared/formHelpers'



const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const mode = ref(route.query.mode || null)
const form = reactive({ patient_id: '', status: 'scheduled', start_time: '', end_time: '', notes: '', price: '', app_type_id: '', custom_type: '', use_bonus_id: '', use_credit_payment_id: '', bonus_notes: '', bonus_name: '', payment_type: 'single', apply_credit: false, apply_credit_mode: 'auto', apply_credit_amount: '' })
const invoiceNotesDraft = ref('')
const applyCreditConfirmed = ref(false)
const sendWhatsapp = ref(false)
const reminderCopied = ref(false)

const statusOptions = [
  { value: 'scheduled', label: 'Programada', color: '#99b1ff' },
  { value: 'rescheduled', label: 'Reprogramada', color: '#f59e0b' },
  { value: 'completed', label: 'Completada', color: '#a1f7bf' },
  { value: 'canceled', label: 'Cancelada', color: '#ffcccc' }
]

function statusOptionDot(status) {
  const map = {
    scheduled: '🔵',
    rescheduled: '🟠',
    completed: '🟢',
    canceled: '🔴',
  }
  return map[String(status || '')] || '⚪'
}
const isCanceled = ref(false)
const originalStart = ref(null)
const originalStartLocal = ref('')
const originalEndLocal = ref('')
const canReprogramInForm = ref(false)
const errors = reactive({})
const submitting = ref(false)
const cancelling = ref(false)
const loading = ref(false)
const calendarInfoMessage = ref('')
const patients = ref([])
const appointmentTypes = ref([])
const overlapping = ref([])
const hasScheduledOverlap = computed(() => overlapping.value.some(a => a.status === 'scheduled'))
let overlapTimer = null
const selectBonus = ref(false)
const activeTab = ref('session')

const bonuses = ref([])
const bonusesLoading = ref(false)
const pendingCreditPayments = ref([])
const pendingCreditPaymentsLoading = ref(false)
const loadingPatientCollections = ref(false)
const loadedCollectionsPatientId = ref(null)
const appointmentPayments = ref([])
const appointmentBonusPayments = ref([])
const appointmentCreditUsages = ref([])
const appointmentCoveredAmount = ref(0)
const issuingInvoice = ref(false)
const appointmentInvoiceId = ref(null)
const appointmentPaymentStatus = ref('')
const suppressTypeChangePrompt = ref(false)

const selectedBonus = computed(() => {
  if (!form.use_bonus_id || !bonuses.value) return null
  return bonuses.value.find(b => String(b.id) === String(form.use_bonus_id)) || null
})

const selectedBonusSessionPrice = computed(() => {
  const bonusPrice = Number(selectedBonus.value?.price || 0)
  const totalSessions = Number(selectedBonus.value?.total_sessions || 0)

  if (!Number.isFinite(bonusPrice) || bonusPrice <= 0) return 0
  if (!Number.isFinite(totalSessions) || totalSessions <= 0) return 0

  return Number((bonusPrice / totalSessions).toFixed(2))
})

const effectiveSessionPrice = computed(() => {
  if (form.payment_type === 'bonus' || form.use_bonus_id) {
    return selectedBonusSessionPrice.value
  }

  const amount = Number(form.price || 0)
  if (!Number.isFinite(amount) || amount <= 0) return 0
  return Number(amount.toFixed(2))
})

const selectedPendingCreditPayment = computed(() => {
  if (!form.use_credit_payment_id || !pendingCreditPayments.value) return null
  return pendingCreditPayments.value.find(p => String(p.id) === String(form.use_credit_payment_id)) || null
})

function creditPendingAmountOf(payment) {
  const pending = Number(payment?.credit_pending_amount)
  if (Number.isFinite(pending)) return Math.max(pending, 0)

  const total = Number(payment?.amount || 0)
  const used = Number(payment?.credit_used_amount || 0)
  if (!Number.isFinite(total) || !Number.isFinite(used)) return 0
  return Math.max(total - used, 0)
}

const selectedPendingCreditRemainingAmount = computed(() => {
  if (!selectedPendingCreditPayment.value) return 0
  return creditPendingAmountOf(selectedPendingCreditPayment.value)
})

const selectedPendingCreditRemainingAfterSessionAmount = computed(() => {
  const pendingAmount = Number(selectedPendingCreditRemainingAmount.value || 0)
  const sessionAmount = Number(form.payment_type === 'credit' ? (form.price || 0) : 0)

  if (!Number.isFinite(pendingAmount)) return 0
  if (!Number.isFinite(sessionAmount) || sessionAmount <= 0) return Math.max(pendingAmount, 0)

  return Math.max(pendingAmount - sessionAmount, 0)
})

function isExpiredLocal(b) {
  if (!b || !b.expires_at) return false
  try {
    const ex = new Date(b.expires_at)
    // treat the expiration day as inclusive -> end of day
    ex.setHours(23,59,59,999)
    return Date.now() > ex.getTime()
  } catch (e) {
    return false
  }
}

const hasAvailableBonuses = computed(() => {
  if (!bonuses.value || bonuses.value.length === 0) return false
  return bonuses.value.some(b => (b.remaining_sessions && b.remaining_sessions > 0) && !isExpiredLocal(b))
})

const selectableBonuses = computed(() => {
  if (!bonuses.value || bonuses.value.length === 0) return []
  return bonuses.value.filter(b => (b.remaining_sessions && b.remaining_sessions > 0) && !isExpiredLocal(b))
})

const hasPendingCreditPayments = computed(() => {
  return Array.isArray(pendingCreditPayments.value) && pendingCreditPayments.value.length > 0
})

const sortedAppointmentPayments = computed(() => {
  const paymentItems = appointmentPayments.value.map(p => ({
    ...p,
    _type: 'payment',
    _key: `pay-${p.id}`,
    _date: new Date(p.paid_at || p.created_at || 0).getTime(),
  }))
  const bonusPaymentItems = appointmentBonusPayments.value.map(p => ({
    ...p,
    _type: 'bonus_payment',
    _key: `bpay-${p.id}`,
    _date: new Date(p.paid_at || p.created_at || 0).getTime(),
  }))
  const creditItems = appointmentCreditUsages.value
    .filter(c => !c.reversed_at)
    .map(c => ({
      ...c,
      _type: 'credit_usage',
      _key: `cu-${c.id}`,
      _date: new Date(c.created_at || 0).getTime(),
    }))
  return [...paymentItems, ...bonusPaymentItems, ...creditItems].sort((a, b) => b._date - a._date)
})

const hasSelectedPatient = computed(() => {
  return !!form.patient_id && form.patient_id !== '__create'
})

const selectedAppointmentType = computed(() => {
  if (!form.app_type_id) return null
  return appointmentTypes.value.find((item) => String(item.id) === String(form.app_type_id)) || null
})

const isCustomAppointmentType = computed(() => form.app_type_id === '__custom')

const hasSelectedAppointmentType = computed(() => !!selectedAppointmentType.value)

const appointmentTypeDurationLabel = computed(() => {
  if (!selectedAppointmentType.value) return '—'
  const hours = Number(selectedAppointmentType.value.estimated_hours || 0)
  const minutes = Number(selectedAppointmentType.value.estimated_minutes || 0)
  const safeHours = Number.isFinite(hours) ? Math.max(hours, 0) : 0
  const safeMinutes = Number.isFinite(minutes) ? Math.max(minutes, 0) : 0
  return `${safeHours}h ${safeMinutes}min`
})

const isEndTimeLocked = computed(() => {
  return !isEdit.value && hasSelectedAppointmentType.value
})

const selectedPatient = computed(() => {
  if (!form.patient_id || form.patient_id === '__create') return null
  return patients.value.find(p => String(p.id) === String(form.patient_id)) || null
})

const availableCredit = computed(() => {
  const amount = Number(selectedPatient.value?.available_credit || 0)
  return Number.isFinite(amount) ? amount : 0
})

const appointmentPriceLabel = computed(() => {
  const amount = Number(effectiveSessionPrice.value || 0)
  if (amount > 0) return `${amount.toFixed(2)}€`
  return '0.00€'
})

const isCoveredByBonus = computed(() => {
  const persistedStatus = String(appointmentPaymentStatus.value || '').toLowerCase()
  if (persistedStatus === 'covered_by_pack') return true
  return form.payment_type === 'bonus' || !!form.use_bonus_id
})

const bonusInvoiceId = computed(() => {
  const value = Number(selectedBonus.value?.invoice_id || 0)
  return Number.isFinite(value) && value > 0 ? value : null
})

const billingDetailLabel = computed(() => {
  if (!isCoveredByBonus.value) {
    return invoiceNotesDraft.value || form.notes || '—'
  }

  const selectedBonusName = selectedBonus.value?.name || form.bonus_name || ''
  const base = selectedBonusName ? `Sesión cubierta por bono: ${selectedBonusName}` : 'Sesión cubierta por bono'

  if (form.bonus_notes) {
    return `${base} — ${form.bonus_notes}`
  }

  return base
})

const billingAmountLabel = computed(() => {
  if (!isCoveredByBonus.value) {
    return appointmentPriceLabel.value
  }

  const bonusAmount = Number(selectedBonusSessionPrice.value || 0)
  const totalSessions = Number(selectedBonus.value?.total_sessions || 0)
  if (Number.isFinite(bonusAmount) && bonusAmount > 0) {
    if (Number.isFinite(totalSessions) && totalSessions > 0) {
      return `${bonusAmount.toFixed(2)}€ (bono / ${totalSessions} sesiones)`
    }
    return `${bonusAmount.toFixed(2)}€ (bono)`
  }

  return 'Cubierto por bono'
})

const appointmentPendingPaymentAmount = computed(() => {
  if (form.payment_type === 'bonus' || form.use_bonus_id) return 0

  const sessionPrice = Number(form.price || 0)
  const coveredAmount = Number(appointmentCoveredAmount.value || 0)

  if (!Number.isFinite(sessionPrice) || sessionPrice <= 0) return 0
  if (!Number.isFinite(coveredAmount) || coveredAmount <= 0) return Number(sessionPrice.toFixed(2))

  return Number(Math.max(sessionPrice - coveredAmount, 0).toFixed(2))
})

const isPaidAppointment = computed(() => {
  if (!isEdit.value) return false

  const status = String(appointmentPaymentStatus.value || '').toLowerCase()
  if (status === 'paid' || status === 'covered_by_pack') return true
  return false
})

const showPaymentTab = computed(() => true)

const isCompletedAppointment = computed(() => {
  const status = String(form.status || '').toLowerCase()
  return status === 'completed'
})

const canSaveAppointment = computed(() => {
  const hasPatient = !!form.patient_id && form.patient_id !== '__create'
  const hasStart = !!form.start_time
  const hasEnd = !!form.end_time
  const hasPrice = Number(effectiveSessionPrice.value || 0) > 0

  return hasPatient && hasStart && hasEnd && hasPrice
})

const calendarErrorMessage = computed(() => {
  if (calendarInfoMessage.value) {
    return calendarInfoMessage.value
  }

  const isCalendarRangeMessage = (message) => {
    const text = String(message || '').toLowerCase()
    return text.includes('hora de inicio') && text.includes('hora de fin')
  }

  const pickMessage = (value) => {
    if (Array.isArray(value) && value.length > 0) return String(value[0] || '')
    if (typeof value === 'string') return value
    return ''
  }

  const startMessage = pickMessage(errors.start_time)
  const endMessage = pickMessage(errors.end_time)
  const generalMessage = pickMessage(errors.general)

  const messages = [startMessage, endMessage, generalMessage]
  const target = messages.find((message) => isCalendarRangeMessage(message))

  return target || ''
})

const paymentStatusLabel = computed(() => {
  if (form.payment_type === 'bonus') return 'Cubierto por bono'
  if (form.payment_type === 'credit') return 'Cubierto por adelanto'
  if (applyCreditConfirmed.value) return 'Parcialmente pagada'
  return 'Pendiente'
})



function creditMethodLabel(method) {
  const map = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia',
  }
  return map[method] || 'Método no definido'
}

function appointmentPaymentMethodLabel(method) {
  return creditMethodLabel(method)
}

function appointmentPaymentConceptLabel(concept) {
  const map = {
    appointment: 'Cita',
    package: 'Bono',
    credit: 'Adelanto',
  }
  return map[String(concept || '')] || '—'
}

function paymentApplicationStatusLabel(payment) {
  const concept = String(payment?.concept || '')
  if (concept !== 'credit') return 'Aplicado'

  const amount = Number(payment?.amount || 0)
  const hasRawPending = payment?.credit_pending_amount != null || payment?.credit_used_amount != null
  const pending = hasRawPending ? Number(payment?.credit_pending_amount ?? 0) : NaN

  if (hasRawPending && amount > 0 && Math.abs(amount - pending) < 0.0001) return 'Pendiente de Aplicar'
  if (hasRawPending && amount > pending && pending > 0) return 'Parcialmente Aplicado'
  if (hasRawPending && pending <= 0) return 'Aplicado'

  const normalized = String(payment?.status || '').toLowerCase()
  if (normalized === 'completed') return 'Aplicado'
  if (normalized === 'pending') return 'Pendiente de Aplicar'
  return 'Aplicado'
}

function paymentApplicationStatusClass(payment) {
  const label = paymentApplicationStatusLabel(payment)
  if (label === 'Pendiente de Aplicar') return 'pending'
  if (label === 'Parcialmente Aplicado') return 'partial'
  return 'completed'
}

function formatAppointmentPaymentAmount(amount) {
  const value = Number(amount || 0)
  if (!Number.isFinite(value)) return '0,00 €'

  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

function formatAppointmentPaymentDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'

  const dd = String(date.getDate()).padStart(2, '0')
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const yyyy = date.getFullYear()
  const hh = String(date.getHours()).padStart(2, '0')
  const min = String(date.getMinutes()).padStart(2, '0')

  return `${dd}/${mm}/${yyyy} ${hh}:${min}`
}

// Keep form.bonus_name in sync with selected bonus id
watch(() => form.use_bonus_id, (id) => {
  if (!id) {
    form.bonus_name = ''
    return
  }
  const b = bonuses.value.find(x => String(x.id) === String(id))
  form.bonus_name = b ? (b.name || (`Bono ${b.total_sessions} sesiones`)) : ''
})

// Cuando cambia el tipo de pago: si es 'bonus' habilitar selección, si es 'single' limpiar selección
watch(() => form.payment_type, (v) => {
  if (v === 'bonus') {
    selectBonus.value = true
    form.apply_credit = false
    form.apply_credit_amount = ''
    applyCreditConfirmed.value = false
    form.use_credit_payment_id = ''
    if (selectedBonusSessionPrice.value > 0) {
      form.price = selectedBonusSessionPrice.value
    }
  } else if (v === 'credit') {
    selectBonus.value = false
    form.use_bonus_id = ''
    form.bonus_notes = ''
    form.bonus_name = ''
    form.apply_credit = false
    form.apply_credit_amount = ''
    applyCreditConfirmed.value = false
  } else {
    selectBonus.value = false
    form.use_bonus_id = ''
    form.bonus_notes = ''
    form.bonus_name = ''
    form.use_credit_payment_id = ''
  }
})

watch([() => form.use_bonus_id, () => form.payment_type, selectedBonusSessionPrice], () => {
  if (form.payment_type !== 'bonus') return

  if (selectedBonusSessionPrice.value > 0) {
    form.price = selectedBonusSessionPrice.value
  }
})

watch(() => form.use_credit_payment_id, (id) => {
  if (!id) return

  const selected = pendingCreditPayments.value.find(p => String(p.id) === String(id))
  if (!selected) return

  if (form.payment_type === 'credit') {
    form.price = Number(creditPendingAmountOf(selected)).toFixed(2)
  }
})

async function onPatientChange() {
  if (form.patient_id === '__create') {
    const toast = useToast()
    const newPatient = await sharedOpenCreatePatientPopup({ api, Swal, toast })
    if (newPatient) {
      patients.value.unshift(newPatient)
      form.patient_id = newPatient.id
    } else {
      form.patient_id = ''
    }
  }
  if (!form.patient_id || form.patient_id === '__create') {
    bonuses.value = []
    pendingCreditPayments.value = []
    loadedCollectionsPatientId.value = null
    selectBonus.value = false
    form.use_bonus_id = ''
    form.use_credit_payment_id = ''
  }
}

async function loadPatientCollections(patientId, force = false) {
  const normalizedId = patientId ? String(patientId) : ''
  if (!normalizedId || normalizedId === '__create') {
    bonuses.value = []
    pendingCreditPayments.value = []
    loadedCollectionsPatientId.value = null
    return
  }

  if (!force && loadedCollectionsPatientId.value === normalizedId) {
    return
  }

  if (loadingPatientCollections.value) {
    return
  }

  loadingPatientCollections.value = true
  try {
    await Promise.all([
      loadBonusesForPatient(normalizedId),
      loadPendingCreditPaymentsForPatient(normalizedId),
    ])
    loadedCollectionsPatientId.value = normalizedId
  } finally {
    loadingPatientCollections.value = false
  }
}

async function loadBonusesForPatient(patientId) {
  bonuses.value = []
  if (!patientId) return
  bonusesLoading.value = true
  try {
    const res = await api.get(`/patients/${patientId}/bonuses`)
    bonuses.value = (res.data && res.data.data) ? res.data.data : []
  } catch (e) {
    bonuses.value = []
  } finally {
    bonusesLoading.value = false
    // If there are no bonuses, ensure checkbox is off and selection cleared
    if (!bonuses.value || bonuses.value.length === 0) {
      selectBonus.value = false
      form.use_bonus_id = ''
    }
  }
}

async function loadPendingCreditPaymentsForPatient(patientId) {
  pendingCreditPayments.value = []
  if (!patientId) return

  pendingCreditPaymentsLoading.value = true
  try {
    const res = await api.get('/payments', {
      params: {
        patient_id: Number(patientId),
        concept: 'credit',
        status: 'pending',
        per_page: 100,
      },
    })

    const rows = Array.isArray(res.data?.data) ? res.data.data : []
    pendingCreditPayments.value = rows
  } catch (e) {
    pendingCreditPayments.value = []
  } finally {
    pendingCreditPaymentsLoading.value = false
    if (!pendingCreditPayments.value.length) {
      form.use_credit_payment_id = ''
    }
  }
}

// openCreatePatientPopup moved to shared/formHelpers


async function loadFormBootstrap({ appointmentId = null, patientId = null } = {}) {
  const params = {}

  if (appointmentId) {
    params.appointment_id = Number(appointmentId)
  }

  if (patientId) {
    params.patient_id = Number(patientId)
  }

  const res = await api.get('/appointments/form-bootstrap', { params })
  const data = res.data?.data ?? {}

  patients.value = Array.isArray(data.patients) ? data.patients : []
  appointmentTypes.value = Array.isArray(data.appointment_types) ? data.appointment_types : []
  bonuses.value = Array.isArray(data.bonuses) ? data.bonuses : []
  pendingCreditPayments.value = Array.isArray(data.pending_credit_payments) ? data.pending_credit_payments : []

  const effectivePatientId = Number(
    data?.appointment?.patient_id
    || patientId
    || 0
  )
  loadedCollectionsPatientId.value = effectivePatientId > 0 ? String(effectivePatientId) : null

  return data
}

async function applyAppointmentData(data) {
  appointmentPayments.value = Array.isArray(data?.payments) ? data.payments : []
  appointmentBonusPayments.value = Array.isArray(data?.bonus_payments) ? data.bonus_payments : []
  appointmentCreditUsages.value = Array.isArray(data?.credit_usages) ? data.credit_usages : []
  form.patient_id = data.patient_id || ''
  form.status = data.status || 'scheduled'

  if (mode.value === 'reprogram') {
    form.status = 'rescheduled'
  }

  isCanceled.value = (data.status === 'canceled' || data.status === 'cancelled')
  originalStart.value = data.start_time || null

  if (originalStart.value) {
    const startMs = new Date(originalStart.value).getTime()
    canReprogramInForm.value = Date.now() < (startMs - (1 * 60 * 60 * 1000))
  } else {
    canReprogramInForm.value = false
  }

  form.start_time = toDatetimeLocalValue(data.start_time)
  form.end_time = toDatetimeLocalValue(data.end_time)
  originalStartLocal.value = form.start_time || ''
  originalEndLocal.value = form.end_time || ''
  form.notes = data.notes || ''
  invoiceNotesDraft.value = form.notes || ''
  form.price = data.price != null ? Number(data.price) : ''
  suppressTypeChangePrompt.value = true
  form.app_type_id = data.app_type_id != null ? String(data.app_type_id) : (data.custom_type ? '__custom' : '')
  await nextTick()
  suppressTypeChangePrompt.value = false
  form.custom_type = data.custom_type || ''
  appointmentInvoiceId.value = data.invoice_id ? Number(data.invoice_id) : null
  appointmentPaymentStatus.value = String(data.payment_status || '')

  const currentPrice = Number(data.price || 0)
  const pendingFromApi = Number(data.pending_payment_amount || 0)
  if (Number.isFinite(currentPrice) && currentPrice > 0 && Number.isFinite(pendingFromApi) && pendingFromApi >= 0) {
    appointmentCoveredAmount.value = Number(Math.max(currentPrice - pendingFromApi, 0).toFixed(2))
  } else {
    appointmentCoveredAmount.value = 0
  }

  const bid = data.use_bonus_id || data.bonus_id
  if (bid) {
    form.use_bonus_id = bid
    selectBonus.value = true
  }

  if (data.payment_type) {
    form.payment_type = data.payment_type
  }
}

async function suggestCreateBonus() {
  const toast = useToast()
  if (!form.patient_id) {
    toast.info('Selecciona primero un paciente')
    return
  }

  const result = await Swal.fire({
    title: 'Crear bono',
    html: `
      <div class="swal-card">
        <div class="create-row">
          <label for="swal-bonus-name">Nombre</label>
          <input id="swal-bonus-name" class="input" type="text" required value="Bono" />
        </div>
        <div class="create-row">
          <label for="swal-bonus-sessions">Nº sesiones</label>
          <input id="swal-bonus-sessions" class="input" type="number" min="1" required value="1" />
        </div>
        <div class="create-row">
          <label for="swal-bonus-price">Precio</label>
          <input id="swal-bonus-price" class="input" type="number" step="0.01" min="0" value="0" />
        </div>
        <div class="create-row">
          <label for="swal-bonus-expires">Expira (opcional)</label>
          <input id="swal-bonus-expires" class="input" type="date" />
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Crear',
    cancelButtonText: 'Cancelar',
    customClass: { popup: 'swal-popup-card' },
    focusConfirm: false,
    preConfirm: async () => {
      const name = document.getElementById('swal-bonus-name')?.value?.trim() || 'Bono'
      const totalSessions = Number(document.getElementById('swal-bonus-sessions')?.value || 0)
      const price = Number(document.getElementById('swal-bonus-price')?.value || 0)
      const expiresAt = document.getElementById('swal-bonus-expires')?.value || ''

      if (!Number.isFinite(totalSessions) || totalSessions <= 0) {
        Swal.showValidationMessage('El número de sesiones debe ser mayor a 0')
        return false
      }

      if (!Number.isFinite(price) || price < 0) {
        Swal.showValidationMessage('El precio debe ser 0 o mayor')
        return false
      }

      try {
        const payload = {
          name,
          total_sessions: totalSessions,
          price,
        }
        if (expiresAt) payload.expires_at = expiresAt

        const res = await api.post(`/patients/${form.patient_id}/bonuses`, payload)
        return (res.data && res.data.data) ? res.data.data : res.data
      } catch (e) {
        const message = e?.response?.data?.message || 'Error creando bono'
        Swal.showValidationMessage(message)
        return false
      }
    },
  })

  if (!result.isConfirmed || !result.value) return

  const createdBonus = result.value
  await loadBonusesForPatient(form.patient_id)
  if (createdBonus?.id) {
    form.use_bonus_id = String(createdBonus.id)
  }
  toast.success('Bono creado')
}

function onBonusSelectChange() {
  if (form.use_bonus_id === '__create_bonus') {
    form.use_bonus_id = ''
    suggestCreateBonus()
  }
}

const whatsappReminderMessage = computed(() => {
  const patientName = selectedPatient.value?.name || ''
  const start = form.start_time
  const end = form.end_time

  if (!patientName || !start) return ''

  const fmtDate = (val) => {
    if (!val) return ''
    const d = new Date(val)
    if (Number.isNaN(d.getTime())) return val
    const dd = String(d.getDate()).padStart(2, '0')
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const yyyy = d.getFullYear()
    const hh = String(d.getHours()).padStart(2, '0')
    const min = String(d.getMinutes()).padStart(2, '0')
    return `${dd}/${mm}/${yyyy} a las ${hh}:${min}`
  }

  const fmtTime = (val) => {
    if (!val) return ''
    const d = new Date(val)
    if (Number.isNaN(d.getTime())) return ''
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
  }

  let msg = `Hola ${patientName} Recuerda que tienes una cita\nFecha: ${fmtDate(start)}`
  // if (form.notes) msg += `\nNotas: ${form.notes}`
  msg += `\nContesta este mensaje si quieres confirmar, reprogramar o cancelar tu cita.`
  return msg
})

async function copyReminderToClipboard() {
  const msg = whatsappReminderMessage.value
  if (!msg) return
  try {
    await navigator.clipboard.writeText(msg)
    reminderCopied.value = true
    setTimeout(() => { reminderCopied.value = false }, 2000)
  } catch (e) {
    // fallback
    const el = document.createElement('textarea')
    el.value = msg
    document.body.appendChild(el)
    el.select()
    document.execCommand('copy')
    document.body.removeChild(el)
    reminderCopied.value = true
    setTimeout(() => { reminderCopied.value = false }, 2000)
  }
}

function cancel() {
  sharedGoBack(router, route)
}

function checkOverlap() {
  // no hay rango completo
  if (!form.start_time || !form.end_time) {
    overlapping.value = []
    return Promise.resolve()
  }

  if (overlapTimer) clearTimeout(overlapTimer)
  return new Promise((resolve) => {
    overlapTimer = setTimeout(async () => {
      try {
        const currentId = route.params.id ? String(route.params.id) : null
        const cleaned = await checkOverlapShared({ start: form.start_time, end: form.end_time, currentId, api, Swal })
        overlapping.value = cleaned
      } catch (e) {
        overlapping.value = []
      }
      resolve()
    }, 300)
  })
}

async function appointmentCancel() {
  cancelling.value = true
  const toast = useToast()
  try {
    await appointmentCancelShared(route.params.id, { api, toast, router })
  } catch (e) {
    // ignore
  } finally {
    cancelling.value = false
  }
}

function confirmApplyCredit() {
  if (!form.apply_credit) {
    errors.apply_credit_error = ['Marca la opción para aplicar crédito en esta cita']
    return
  }

  if (form.apply_credit_mode === 'manual') {
    const manualAmount = Number(form.apply_credit_amount || 0)
    if (!manualAmount || manualAmount <= 0) {
      errors.apply_credit_error = ['Ingresa un importe válido mayor a 0']
      return
    }
    if (manualAmount > availableCredit.value) {
      errors.apply_credit_error = [`El importe no puede ser mayor a ${availableCredit.value.toFixed(2)}€`]
      return
    }
  }

  errors.apply_credit_error = []
  applyCreditConfirmed.value = true
}

function handleSinglePayment() {
  // Navigate to payments/create with preloaded query params
  if (isEdit.value && route.params.id) {
    router.push({
      path: '/payments/create',
      query: {
        patient_id: form.patient_id,
        concept: 'appointment',
        appointment_id: route.params.id,
        amount: String(Number(appointmentPendingPaymentAmount.value || 0).toFixed(2)),
      }
    })
  }
}

function handleBillingTabClick() {
  if (appointmentInvoiceId.value) {
    goToInvoiceFromAppointment(appointmentInvoiceId.value)
    return
  }

  activeTab.value = 'billing'
}

function goToBonusInvoice() {
  if (!bonusInvoiceId.value) return
  goToInvoiceFromAppointment(bonusInvoiceId.value)
}

function goToInvoiceFromAppointment(invoiceId) {
  if (!invoiceId) return

  const appointmentId = Number(route.params.id || 0)

  router.push({
    path: `/invoices/${invoiceId}`,
    query: appointmentId > 0
      ? { from: 'appointment', appointment_id: String(appointmentId) }
      : undefined,
  })
}

async function emitInvoice() {
  if (!isEdit.value || !route.params.id) return

  if (isCoveredByBonus.value) {
    if (bonusInvoiceId.value) {
      goToInvoiceFromAppointment(bonusInvoiceId.value)
    }
    return
  }

  if (appointmentInvoiceId.value) {
    goToInvoiceFromAppointment(appointmentInvoiceId.value)
    return
  }

  issuingInvoice.value = true
  const toast = useToast()

  try {
    const invoiceNotes = String(invoiceNotesDraft.value || '').trim() || String(form.notes || '').trim() || undefined
    const res = await api.post(`/appointments/${route.params.id}/invoice`, {
      notes: invoiceNotes,
    })
    const documentId = res.data?.data?.id

    toast.success(res.data?.message || 'Factura emitida correctamente')

    if (documentId) {
      appointmentInvoiceId.value = Number(documentId)
      goToInvoiceFromAppointment(documentId)
    }
  } catch (e) {
    const message = e?.response?.data?.message || 'No se pudo emitir la factura'
    toast.error(message)
  } finally {
    issuingInvoice.value = false
  }
}

// formatDate moved to shared/appointmentHelpers

function goToAppointment(id) {
  if (!id) return
  router.push(`/appointments/${id}`)
}

function goToPatient(id) {
  if (!id || id === '__create') return
  router.push(`/patients/${id}`)
}

function toDatetimeLocalString(date) {
  const yyyy = date.getFullYear()
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const dd = String(date.getDate()).padStart(2, '0')
  const hh = String(date.getHours()).padStart(2, '0')
  const min = String(date.getMinutes()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`
}

const timeOptions = computed(() => {
  const opts = []
  for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 5) {
      opts.push(`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`)
    }
  }
  return opts
})

const startDateModel = computed({
  get() {
    return form.start_time ? form.start_time.slice(0, 10) : ''
  },
  set(date) {
    const time = form.start_time ? form.start_time.slice(11, 16) : '00:00'
    form.start_time = date ? `${date}T${time}` : ''
  }
})

const startTimeModel = computed({
  get() {
    return form.start_time ? form.start_time.slice(11, 16) : ''
  },
  set(time) {
    const date = form.start_time ? form.start_time.slice(0, 10) : new Date().toISOString().slice(0, 10)
    form.start_time = time ? `${date}T${time}` : ''
    normalizeDateTimeField('start_time')
  }
})

const endDateModel = computed({
  get() {
    return form.end_time ? form.end_time.slice(0, 10) : ''
  },
  set(date) {
    const time = form.end_time ? form.end_time.slice(11, 16) : '00:00'
    form.end_time = date ? `${date}T${time}` : ''
  }
})

const endTimeModel = computed({
  get() {
    return form.end_time ? form.end_time.slice(11, 16) : ''
  },
  set(time) {
    const date = form.end_time ? form.end_time.slice(0, 10) : new Date().toISOString().slice(0, 10)
    form.end_time = time ? `${date}T${time}` : ''
  }
})

function roundDatetimeLocalToNearestMinutes(value, stepMinutes = 5) {
  if (!value) return value
  const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/)
  if (!match) return value

  const [, y, m, d, hh, mm] = match
  const parsed = new Date(Number(y), Number(m) - 1, Number(d), Number(hh), Number(mm), 0, 0)
  if (Number.isNaN(parsed.getTime())) return value

  const stepMs = stepMinutes * 60 * 1000
  const roundedMs = Math.round(parsed.getTime() / stepMs) * stepMs
  return toDatetimeLocalString(new Date(roundedMs))
}

function normalizeDateTimeField(fieldName) {
  const currentValue = form[fieldName]
  const roundedValue = roundDatetimeLocalToNearestMinutes(currentValue, 5)
  if (roundedValue !== currentValue) {
    form[fieldName] = roundedValue
  }
}

function toIsoFromLocal(localValue) {
  if (!localValue) return null
  const date = new Date(localValue)
  if (Number.isNaN(date.getTime())) return null
  return date.toISOString()
}

function toLocalFromIso(isoValue) {
  if (!isoValue) return ''
  return toDatetimeLocalValue(isoValue)
}

function applyAppointmentTypeDefaults() {
  if (isEdit.value || !selectedAppointmentType.value) return

  const minutes = Number(selectedAppointmentType.value.estimated_minutes || 0)
  const hours = Number(selectedAppointmentType.value.estimated_hours || 0)
  const totalMinutes = (Number.isFinite(hours) ? Math.max(hours, 0) * 60 : 0) + (Number.isFinite(minutes) ? Math.max(minutes, 0) : 0)

  const typePrice = Number(selectedAppointmentType.value.price || 0)
  if (Number.isFinite(typePrice) && typePrice >= 0) {
    form.price = Number(typePrice.toFixed(2))
  }

  if (selectedAppointmentType.value.payment_type === 'abono' && hasAvailableBonuses.value) {
    form.payment_type = 'bonus'
  } else {
    form.payment_type = 'single'
  }

  if (!form.start_time || totalMinutes <= 0) return

  const startIso = toIsoFromLocal(form.start_time)
  if (!startIso) return
  const startDate = new Date(startIso)
  startDate.setMinutes(startDate.getMinutes() + totalMinutes)
  form.end_time = toLocalFromIso(startDate.toISOString())
}

async function confirmAndApplyTypeDefaultsOnEdit() {
  if (!selectedAppointmentType.value) return

  const result = await Swal.fire({
    title: 'Recalcular automáticamente',
    text: '¿Deseas recalcular precio, tipo de pago y hora de fin según el tipo seleccionado?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, recalcular',
    cancelButtonText: 'No, mantener actual',
  })

  if (!result.isConfirmed) return

  const minutes = Number(selectedAppointmentType.value.estimated_minutes || 0)
  const hours = Number(selectedAppointmentType.value.estimated_hours || 0)
  const totalMinutes = (Number.isFinite(hours) ? Math.max(hours, 0) * 60 : 0) + (Number.isFinite(minutes) ? Math.max(minutes, 0) : 0)

  const typePrice = Number(selectedAppointmentType.value.price || 0)
  if (Number.isFinite(typePrice) && typePrice >= 0) {
    form.price = Number(typePrice.toFixed(2))
  }

  form.payment_type = (selectedAppointmentType.value.payment_type === 'abono' && hasAvailableBonuses.value)
    ? 'bonus'
    : 'single'

  if (!form.start_time || totalMinutes <= 0) return

  const startIso = toIsoFromLocal(form.start_time)
  if (!startIso) return
  const startDate = new Date(startIso)
  startDate.setMinutes(startDate.getMinutes() + totalMinutes)
  form.end_time = toLocalFromIso(startDate.toISOString())
}

const isFutureAppointment = computed(() => {
  try {
    const t = originalStart.value || form.start_time
    if (!t) return false
    const dt = new Date(t)
    return dt.getTime() > Date.now()
  } catch (e) {
    return false
  }
})

function startReprogram() {
  // enable reprogram mode in the route so form respects reprogram behavior
  startReprogramShared(router, route)
}
async function loadForEdit(id) {
  loading.value = true
  try {
    const res = await api.get(`/appointments/${id}`)
    const data = res.data
    await applyAppointmentData(data)
    if (form.patient_id) {
      await loadPatientCollections(form.patient_id, true)
    }
  } catch (e) {
    appointmentPayments.value = []
    appointmentBonusPayments.value = []
    appointmentCreditUsages.value = []
    console.error('Error cargando cita para edición', e)
    if (e.response && e.response.status === 404) router.push('/appointments/day')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  loading.value = true
  const id = route.params.id
  const preselect = route.query.patient_id

  try {
    const bootstrap = await loadFormBootstrap({
      appointmentId: id ? Number(id) : null,
      patientId: !id && preselect ? Number(preselect) : null,
    })

    if (id) {
      isEdit.value = true
      if (bootstrap?.appointment) {
        await applyAppointmentData(bootstrap.appointment)
      } else {
        await loadForEdit(id)
      }
    } else if (preselect) {
      form.patient_id = String(preselect)
    }
  } catch (e) {
    // Fallback: mantener comportamiento anterior si falla bootstrap
    if (id) {
      isEdit.value = true
      await loadForEdit(id)
    }
  } finally {
    loading.value = false
  }

  // Pre-fill start/end from query params (e.g. coming from a free gap or week cell)
  if (!isEdit.value) {
    if (route.query.start) form.start_time = route.query.start
    if (route.query.end)   form.end_time   = route.query.end
  }
})

// When selecting patient, load bonuses for that patient
watch(() => form.patient_id, (id) => {
  if (id && id !== '__create') {
    loadPatientCollections(id)
    return
  }

  loadedCollectionsPatientId.value = null
  bonuses.value = []
  pendingCreditPayments.value = []
  form.use_credit_payment_id = ''
  form.apply_credit = false
  form.apply_credit_amount = ''
  applyCreditConfirmed.value = false
})

watch(() => form.apply_credit_mode, (modeValue) => {
  if (modeValue !== 'manual') {
    form.apply_credit_amount = ''
  }
  applyCreditConfirmed.value = false
  errors.apply_credit_error = []
})

watch(() => form.apply_credit, (enabled) => {
  if (!enabled) {
    applyCreditConfirmed.value = false
    errors.apply_credit_error = []
    return
  }

  applyCreditConfirmed.value = false
})

watch(() => form.apply_credit_amount, () => {
  if (form.apply_credit_mode === 'manual') {
    applyCreditConfirmed.value = false
  }
})

watch(() => showPaymentTab.value, (visible) => {
  if (!visible && activeTab.value === 'payment') {
    activeTab.value = 'session'
  }
})

// keep mode in sync with route query
watch(() => route.query.mode, (m) => { mode.value = m || null })

watch(() => route.params.id, (id) => {
  if (id) {
    isEdit.value = true
    Object.keys(errors).forEach(k => delete errors[k])
    loadForEdit(id)
  } else {
    isEdit.value = false
    originalStartLocal.value = ''
    originalEndLocal.value = ''
    form.patient_id = ''
    form.status = 'scheduled'
    form.start_time = ''
    form.end_time = ''
    form.notes = ''
    invoiceNotesDraft.value = ''
    form.price = ''
    form.app_type_id = ''
    form.custom_type = ''
    form.use_bonus_id = ''
    form.use_credit_payment_id = ''
    form.payment_type = 'single'
    form.apply_credit = false
    form.apply_credit_mode = 'auto'
    form.apply_credit_amount = ''
    appointmentInvoiceId.value = null
    appointmentCoveredAmount.value = 0
    Object.keys(errors).forEach(k => delete errors[k])
  }
})

watch(() => [form.start_time, form.end_time], () => {
  checkOverlap()
})

watch(() => form.start_time, (newVal, oldVal) => {
  if (suppressTypeChangePrompt.value) return
  if (!newVal || !oldVal) return
  if (!selectedAppointmentType.value) return

  const minutes = Number(selectedAppointmentType.value.estimated_minutes || 0)
  const hours = Number(selectedAppointmentType.value.estimated_hours || 0)
  const totalMinutes = Math.max(hours, 0) * 60 + Math.max(minutes, 0)
  if (totalMinutes <= 0) return

  const startIso = toIsoFromLocal(newVal)
  if (!startIso) return
  const endDate = new Date(startIso)
  endDate.setMinutes(endDate.getMinutes() + totalMinutes)
  form.end_time = toLocalFromIso(endDate.toISOString())
})

watch(() => form.app_type_id, () => {
  if (suppressTypeChangePrompt.value) return

  if (selectedAppointmentType.value) {
    confirmAndApplyTypeDefaultsOnEdit()
  }

  if (isCustomAppointmentType.value) {
    if (!isEdit.value) {
      form.price = ''
    }
    form.payment_type = 'single'
    form.use_bonus_id = ''
    form.bonus_notes = ''
    form.bonus_name = ''
    return
  }

  if (!isCustomAppointmentType.value) {
    form.custom_type = ''
  }
  applyAppointmentTypeDefaults()
})

watch(() => form.start_time, () => {
  if (!isEdit.value && hasSelectedAppointmentType.value) {
    applyAppointmentTypeDefaults()
  }
})

watch(() => form.end_time, () => {
  calendarInfoMessage.value = ''
})

async function submit(payNow = false) {
  submitting.value = true
  calendarInfoMessage.value = ''
  Object.keys(errors).forEach(k => delete errors[k])

  const intendedStatus = mode.value === 'reprogram' ? 'rescheduled' : String(form.status || '')
  const endTimeChanged = isEdit.value && (
    (form.end_time || '') !== (originalEndLocal.value || '')
  )
  const shouldValidatePastDateTime = isEdit.value && endTimeChanged && ['scheduled', 'rescheduled'].includes(intendedStatus)
  const shouldShowBonusExhaustedAlert = ['scheduled', 'rescheduled'].includes(intendedStatus)

  if (shouldValidatePastDateTime && form.end_time) {
    const selectedEndDate = new Date(form.end_time)
    if (!Number.isNaN(selectedEndDate.getTime()) && selectedEndDate.getTime() < Date.now()) {
      calendarInfoMessage.value = 'La fecha y hora de fin ya han pasado.'
      submitting.value = false
      return
    }
  }

  // If trying to reprogram a canceled appointment, ensure it's allowed
  if (isCanceled.value && mode.value === 'reprogram' && !canReprogramInForm.value) {
    errors.general = ['Reprogramación no permitida fuera del plazo de 1 horas antes del inicio']
    submitting.value = false
    return
  }

  if (form.payment_type === 'single' && applyCreditConfirmed.value && availableCredit.value <= 0) {
    errors.general = ['El paciente no tiene crédito disponible']
    submitting.value = false
    return
  }

  if (form.payment_type === 'single' && applyCreditConfirmed.value && form.apply_credit_mode === 'manual') {
    const manualAmount = Number(form.apply_credit_amount || 0)
    if (!manualAmount || manualAmount <= 0) {
      errors.general = ['Indica un importe de crédito válido']
      submitting.value = false
      return
    }

    if (manualAmount > availableCredit.value) {
      errors.general = ['El importe de crédito supera el saldo disponible']
      submitting.value = false
      return
    }
  }

  if (form.payment_type === 'credit' && !form.use_credit_payment_id) {
    errors.use_credit_payment_id = ['Debes seleccionar un adelanto pendiente.']
    submitting.value = false
    return
  }

  if (form.payment_type === 'credit') {
    const sessionAmount = Number(form.price || 0)
    const pendingAmount = Number(selectedPendingCreditRemainingAmount.value || 0)

    if (!sessionAmount || sessionAmount <= 0) {
      errors.price = ['Debes indicar el precio de la sesión.']
      submitting.value = false
      return
    }

    if (sessionAmount > pendingAmount) {
      errors.price = ['El importe de la sesión no puede superar el importe a favor pendiente.']
      submitting.value = false
      return
    }
  }

  const estimatedCreditApplied = (() => {
    if (form.payment_type !== 'single' || !applyCreditConfirmed.value) return 0

    const pending = Number(appointmentPendingPaymentAmount.value || 0)
    const available = Number(availableCredit.value || 0)
    const requested = form.apply_credit_mode === 'manual'
      ? Number(form.apply_credit_amount || 0)
      : available

    if (!Number.isFinite(requested) || requested <= 0) return 0
    return Number(Math.max(Math.min(requested, available, pending), 0).toFixed(2))
  })()

    try {
      // comprobar solapamiento antes de enviar (muestra aviso, pero no bloquea)
      await checkOverlap()
    const toast = useToast()
    const payload = {
      patient_id: form.patient_id,
      status: form.status,
      start_time: form.start_time,
      end_time: form.end_time,
      app_type_id: (!isCustomAppointmentType.value && form.app_type_id) ? form.app_type_id : undefined,
      custom_type: isCustomAppointmentType.value ? (form.custom_type || undefined) : undefined,
      notes: form.notes,
      price: effectiveSessionPrice.value > 0 ? Number(effectiveSessionPrice.value) : undefined,
      payment_type: form.payment_type === 'credit' ? 'single' : form.payment_type,
      use_bonus_id: form.use_bonus_id || undefined,
      bonus_id: form.use_bonus_id || undefined,
      use_credit_payment_id: form.payment_type === 'credit' ? (form.use_credit_payment_id || undefined) : undefined,
      use_credit_amount: form.payment_type === 'credit' ? (form.price || undefined) : undefined,
      bonus_notes: form.bonus_notes || undefined,
      bonus_name: form.bonus_name || undefined,
      apply_credit: form.payment_type === 'single' ? applyCreditConfirmed.value : false,
      apply_credit_amount: form.payment_type === 'single' && applyCreditConfirmed.value && form.apply_credit_mode === 'manual'
        ? form.apply_credit_amount
        : undefined,
    }

    // If reprogramming (mode=reprogram) force status -> reprogrammed
    if (mode.value === 'reprogram') {
      payload.status = 'rescheduled'
    }

    if (isEdit.value && route.params.id) {
      await api.patch(`/appointments/${route.params.id}`, payload)
      toast.success('Cita actualizada')
      if (estimatedCreditApplied > 0) {
        toast.info(`Se aplicaron ${estimatedCreditApplied.toFixed(2)}€ de crédito.`)
      }
      if (sendWhatsapp.value) {
        window.open('https://web.whatsapp.com/', '_blank', 'noopener,noreferrer')
      }
      router.push(`/appointments/${route.params.id}`)
    } else {
      // If user selected 'bonus' as payment type but there are no bonuses, block and show error
      if (form.payment_type === 'bonus' && selectableBonuses.value.length === 0 && !payload.bonus_id) {
        errors.general = ['No hay bonos activos disponibles para este paciente']
        submitting.value = false
        return
      }

      if (form.payment_type === 'credit' && (!pendingCreditPayments.value.length || !form.use_credit_payment_id)) {
        errors.general = ['No hay adelantos pendientes disponibles para este paciente']
        submitting.value = false
        return
      }

      const res = await api.post('/appointments', payload)
      const createdId = Number(res?.data?.id || res?.data?.data?.id || 0)
      toast.success('Cita creada')
      if (estimatedCreditApplied > 0) {
        toast.info(`Se aplicaron ${estimatedCreditApplied.toFixed(2)}€ de crédito.`)
      }
      if (sendWhatsapp.value) {
        window.open('https://web.whatsapp.com/', '_blank', 'noopener,noreferrer')
      }
      if (createdId > 0) {
        router.push(`/appointments/${createdId}`)
      } else {
        router.push('/appointments/day')
      }
    }
  } catch (e) {
    if (e.response) {
      const status = e.response.status
      const data = e.response.data || {}
      const serverError = data.error || data.message || ''

      if (status === 400) {
        if (typeof serverError === 'string' && serverError.trim()) {
          calendarInfoMessage.value = serverError
        }
        return
      }

        if (status === 422) {
          // Backend may return structured validation errors or a simple error message
          // Show SweetAlert if it's a concurrency error about exhausted bonus
          if (shouldShowBonusExhaustedAlert && typeof serverError === 'string' && serverError.indexOf('Bono agotado') !== -1) {
            Swal.fire({ icon: 'error', title: 'Bono agotado', text: 'Bono agotado' })
            errors.general = [serverError]
          }
          const eobj = data.errors || {}
          Object.assign(errors, eobj)

          const rangeCandidates = [
            serverError,
            ...(Array.isArray(eobj.start_time) ? eobj.start_time : []),
            ...(Array.isArray(eobj.end_time) ? eobj.end_time : []),
            ...(Array.isArray(eobj.general) ? eobj.general : []),
          ]

          const rangeMessage = rangeCandidates.find((message) => {
            const text = String(message || '').toLowerCase()
            return (text.includes('hora de inicio') && text.includes('hora de fin'))
              || text.includes('clínica está cerrada')
              || text.includes('horario de atención')
          })

          if (rangeMessage) {
            calendarInfoMessage.value = String(rangeMessage)
          }

          // If there is a top-level message that's not validation, include it
          if (!Object.keys(eobj).length && serverError) errors.general = [serverError]
        } else {
          errors.general = [data.message || 'Error desconocido']
        }
    } else {
      errors.general = ['Error de red o servidor']
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-wrapper {
  display:flex;
  justify-content:center;
  width:100%;
  padding:16px;
  box-sizing:border-box;
}
.form-card {
  width:min(100%, 980px);
  background:#fff;
  border-radius:12px;
  box-shadow:0 10px 30px rgba(2,6,23,0.06);
  padding:clamp(14px, 2vw, 24px);
  box-sizing:border-box;
}
.form-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }

.grid-form { display:grid; grid-template-columns: repeat(2, 1fr); gap:12px }
.grid-form .full { grid-column: 1 / -1 }
.tab-content-card { border:1px solid #e5e7eb; border-radius:12px; padding:12px; background:#fff; min-height:0; height:auto }
.tab-content-grid { display:grid; grid-template-columns: repeat(2, 1fr); gap:12px; align-content:start }
.tab-content-grid .full { grid-column: 1 / -1 }
.field { display:flex; flex-direction:column }
.label { font-weight:600; margin-bottom:6px }
.tab-bar { display:flex; gap:8px; margin-top:2px; flex-wrap:wrap }
.tab-btn { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff; font-weight:600; color:#6b7280 }
.tab-btn.active { border-color:#3b82f6; color:#3b82f6; background:#eff6ff }
.tab-btn-success { border-color:#86efac; color:#166534; background:#dcfce7 }
.input, .textarea { width:100%; box-sizing:border-box; padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px }
.datetime-pair { display:grid; grid-template-columns: 1fr 120px; gap:6px; align-items:center }
:deep(.option-select .select-btn) {
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
}
.status-select-wrap {
  position: relative;
  width: 100%;
}
.textarea { resize:vertical }
.field-error { color:#b91c1c; font-size:13px; margin-top:6px }
.field-help { color:#6b7280; font-size:12px; margin-top:6px }
.calendar-inline-alert { margin-top:6px; background:#fffbeb; border:1px solid #fde68a; padding:6px 8px; border-radius:8px; color:#92400e; font-size:12px }

.actions { display:flex; gap:12px; align-items:center }
.actions .muted { color:#6b7280; text-decoration:none }
.primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.primary:hover { background: #eff6ff }
.appointment-payments-list { display:flex; flex-direction:column; gap:8px }
.appointment-payment-item { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; background:#f8fafc }
.appointment-payment-main { display:flex; flex-wrap:wrap; gap:10px; align-items:center; color:#0f172a; font-size:13px }
.appointment-payment-status { display:inline-flex; align-items:center; padding:4px 10px; border-radius:9999px; font-size:12px; font-weight:600 }
.appointment-payment-status.completed { background:#dcfce7; color:#166534 }
.appointment-payment-status.pending { background:#fef3c7; color:#92400e }
.appointment-payment-status.partial { background:#dbeafe; color:#1e40af }
.appointment-payment-status.refunded { background:#fee2e2; color:#b91c1c }
.appointment-payment-status.credit { background:#dbeafe; color:#1e40af }
.appointment-payment-counter { font-size:12px; color:#6b7280; font-weight:500 }

.action-row { display:flex; justify-content:space-between; align-items:center }
.left-actions { display:flex; gap:12px; align-items:center }
.right-actions { display:flex; gap:8px; align-items:center }

@media (max-width: 768px) {
  .form-wrapper { padding:10px }
  .grid-form { grid-template-columns: 1fr }
  .tab-content-grid { grid-template-columns: 1fr }
}

@media (min-width: 1024px) {
  .tab-content-card { min-height:420px }
}

.icon-cancel { width:16px; height:16px; margin-right:8px; vertical-align:middle; color:#ef4444 }
.icon-cancel circle { stroke: currentColor; stroke-width:1.5 }
.icon-cancel path { stroke: currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round }

/* Alinear icono y texto en botones */
.actions button { display:inline-flex; align-items:center; gap:8px }

.alert-subtle { background: #f8fafc; border: 1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }

.inline-alert { display:flex; flex-direction:column; gap:6px; background: #f8fafc; border: 1px solid #e6edf3; padding:8px; border-radius:8px; color:#334155; font-size:13px; max-width:360px }
.inline-alert button { padding:6px 10px; font-size:13px }

.overlap-list { list-style:none; margin:8px 0 0; padding:0; display:flex; flex-direction:column; gap:8px }
.overlap-item { border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#fff }
.overlap-alert-subtle { margin-top:6px; background:#fffbeb; border:1px solid #fde68a; padding:6px 8px; border-radius:8px; color:#92400e; font-size:12px }

.billing-preview-box { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:10px; color:#92400e; font-size:13px }
.billing-preview-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 12px }
.billing-preview-detail { margin-top:10px }
.billing-preview-amount { margin-top:8px; font-size:14px }

.billing-bonus-info { margin-top:10px; display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e6edf3; border-radius:8px; padding:8px 10px; color:#334155; font-size:13px }
.btn-icon { width:16px; height:16px; color:#64748b; flex-shrink:0 }
.billing-bonus-link { margin-left:auto; padding:4px 10px; border-radius:9999px; border:1px solid #dbeafe; color:#1d4ed8; background:#eff6ff; font-size:12px; font-weight:600; cursor:pointer }
.billing-bonus-link:hover { background:#dbeafe }
.billing-bonus-muted { margin-left:auto; color:#64748b; font-size:12px }

@media (max-width: 768px) {
  .billing-preview-grid { grid-template-columns:1fr }
}
</style>

/* Estilos globales para el popup de creación de paciente */
<style>
.swal-popup-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(2,6,23,0.06);
  padding: 18px 18px 16px;
  max-width: 480px;
}
.swal-popup-card .swal2-title { margin-bottom:8px }
.swal-card { display:flex; flex-direction:column; gap:10px }
.swal-card .create-row { display:flex; flex-direction:column; gap:6px }
.swal-card .create-row label { font-weight:600; text-align:left; color:#111827 }
.swal-card .input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb; box-sizing:border-box }
.swal2-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px }
.swal2-actions .primary, .primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.swal2-actions .primary:hover, .primary:hover { background:#eff6ff }

</style>
