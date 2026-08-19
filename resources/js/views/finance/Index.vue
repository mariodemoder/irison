<template>
  <MainLayout>
    <div class="finance-page">
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Finanzas</h1>
            <div class="form-sub">Control de gastos, tarifas y dashboard de beneficios</div>
          </div>
        </div>

        <div class="finance-tabs">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            class="finance-tab"
            :class="{ active: activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- ============ RESUMEN ============ -->
        <section v-if="activeTab === 'resumen'">
          <AppLoading v-if="summaryLoading" message="Cargando resumen..." />

          <template v-else-if="summary">
            <!-- Fila 1: KPI cards -->
            <div class="summary-kpis">
              <div class="summary-kpi">
                <div class="summary-kpi-label">Ingresos</div>
                <div class="summary-kpi-value">{{ formatMoney(summary.current_period.revenue) }}</div>
                <div v-if="summaryVariationText(summary.variation?.revenue) !== null" class="summary-kpi-variation" :class="summaryVariationClass(summary.variation?.revenue)">
                  {{ summaryVariationText(summary.variation?.revenue) }} vs. anterior
                </div>
              </div>
              <div class="summary-kpi">
                <div class="summary-kpi-label">Gastos</div>
                <div class="summary-kpi-value">{{ formatMoney(summary.current_period.expenses) }}</div>
                <div v-if="summaryVariationText(summary.variation?.expenses) !== null" class="summary-kpi-variation" :class="summaryVariationClass(summary.variation?.expenses)">
                  {{ summaryVariationText(summary.variation?.expenses) }} vs. anterior
                </div>
              </div>
              <div class="summary-kpi accent">
                <div class="summary-kpi-label">Beneficio</div>
                <div class="summary-kpi-value">{{ formatMoney(summary.current_period.profit) }}</div>
                <div v-if="summaryVariationText(summary.variation?.profit) !== null" class="summary-kpi-variation" :class="summaryVariationClass(summary.variation?.profit)">
                  {{ summaryVariationText(summary.variation?.profit) }} vs. anterior
                </div>
              </div>
              <div class="summary-kpi">
                <div class="summary-kpi-label">Pendientes</div>
                <div class="summary-kpi-value">{{ summary.current_period.pending_count }}</div>
                <div class="summary-kpi-sub">{{ formatMoney(summary.current_period.pending_amount) }}</div>
              </div>
              <div class="summary-kpi">
                <div class="summary-kpi-label">Ticket medio</div>
                <div class="summary-kpi-value">{{ formatMoney(summary.current_period.ticket_medio) }}</div>
              </div>
              <div class="summary-kpi">
                <div class="summary-kpi-label">Margen</div>
                <div class="summary-kpi-value">{{ summary.current_period.margin_percentage === null ? '—' : summary.current_period.margin_percentage + ' %' }}</div>
                <div v-if="summaryVariationText(summary.variation?.margin_percentage) !== null" class="summary-kpi-variation" :class="summaryVariationClass(summary.variation?.margin_percentage)">
                  {{ summaryVariationText(summary.variation?.margin_percentage) }} p.p. vs. anterior
                </div>
              </div>
            </div>

            <!-- Fila 2: Gráfico evolución -->
            <div class="summary-chart-section">
              <h3 class="summary-section-title">Evolución mensual (12 meses)</h3>
              <EvolutionChart v-if="summary.evolution?.length" :evolution="summary.evolution" />
              <div v-else class="empty-card">Sin datos de evolución.</div>
            </div>

            <!-- Fila 3: Métodos de pago + Comparativa -->
            <div class="summary-bottom">
              <div class="summary-bottom-panel">
                <h3 class="summary-section-title">Ingresos por método de pago</h3>
                <div v-if="summary.by_payment_method?.length" class="table-wrap">
                  <table class="entity-table">
                    <thead>
                      <tr><th>Método</th><th>Total</th><th>%</th></tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in summary.by_payment_method" :key="row.method">
                        <td>{{ row.label }}</td>
                        <td class="total-cell">{{ formatMoney(row.total) }}</td>
                        <td>{{ row.percentage }}%</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="empty-card">Sin datos.</div>
              </div>
              <div class="summary-bottom-panel">
                <h3 class="summary-section-title">Mes actual vs anterior</h3>
                <div v-if="summary.previous_period" class="summary-comparison">
                  <div class="comparison-row">
                    <span class="comparison-label">Ingresos</span>
                    <span class="comparison-current">{{ formatMoney(summary.current_period.revenue) }}</span>
                    <span class="comparison-prev">{{ formatMoney(summary.previous_period.revenue) }}</span>
                  </div>
                  <div class="comparison-row">
                    <span class="comparison-label">Gastos</span>
                    <span class="comparison-current">{{ formatMoney(summary.current_period.expenses) }}</span>
                    <span class="comparison-prev">{{ formatMoney(summary.previous_period.expenses) }}</span>
                  </div>
                  <div class="comparison-row">
                    <span class="comparison-label">Beneficio</span>
                    <span class="comparison-current">{{ formatMoney(summary.current_period.profit) }}</span>
                    <span class="comparison-prev">{{ formatMoney(summary.previous_period.profit) }}</span>
                  </div>
                  <div class="comparison-row">
                    <span class="comparison-label">Margen</span>
                    <span class="comparison-current">{{ summary.current_period.margin_percentage === null ? '—' : summary.current_period.margin_percentage + ' %' }}</span>
                    <span class="comparison-prev">{{ summary.previous_period.margin_percentage === null ? '—' : summary.previous_period.margin_percentage + ' %' }}</span>
                  </div>
                </div>
                <div v-else class="empty-card">Sin datos de comparativa.</div>
              </div>
            </div>
          </template>

          <div v-else class="empty-card">No hay datos de resumen disponibles.</div>
        </section>

        <!-- ============ PENDIENTES ============ -->
        <section v-if="activeTab === 'pendientes'">
          <div class="finance-toolbar">
            <select v-model="pendingProfessionalFilter" class="input input-sm" @change="loadPendingPayments(1)">
              <option value="">Todos los profesionales</option>
              <option v-for="p in pendingProfessionals" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <label class="date-field">
              <span>Desde</span>
              <input v-model="pendingFromDate" type="date" class="filter-date" aria-label="Desde" />
            </label>
            <label class="date-field">
              <span>Hasta</span>
              <input v-model="pendingToDate" type="date" class="filter-date" aria-label="Hasta" />
            </label>
            <button class="btn btn-sm small primary" @click="loadPendingPayments(1)" :disabled="pendingLoading">
              {{ pendingLoading ? 'Cargando...' : 'Buscar' }}
            </button>
          </div>

          <AppLoading v-if="pendingLoading" message="Cargando pendientes..." />

          <template v-else>
            <div v-if="pendingSummary" class="pending-summary">
              <span class="pending-summary-count">{{ pendingSummary.count }} cita{{ pendingSummary.count !== 1 ? 's' : '' }} pendiente{{ pendingSummary.count !== 1 ? 's' : '' }}</span>
              <span class="pending-summary-amount">Total pendiente: {{ formatMoney(pendingSummary.total_pending_amount) }}</span>
            </div>

            <div v-if="pendingPayments.length === 0" class="empty-card">
              No hay citas pendientes de cobro.
            </div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Profesional</th>
                    <th>Servicio</th>
                    <th>Importe</th>
                    <th>Pagado</th>
                    <th>Pendiente</th>
                    <th>Estado</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in pendingPayments" :key="p.appointment_id">
                    <td>{{ formatDate(p.appointment_date) }}</td>
                    <td>{{ p.patient_name }}</td>
                    <td>{{ p.professional_name }}</td>
                    <td>{{ p.service_name }}</td>
                    <td>{{ formatMoney(p.price) }}</td>
                    <td>{{ formatMoney(p.paid_amount) }}</td>
                    <td class="total-cell pending-amount">{{ formatMoney(p.pending_amount) }}</td>
                    <td>
                      <span class="status-chip" :class="p.payment_status === 'partially_paid' ? 'status-partial' : 'status-pending'">
                        {{ p.payment_status === 'partially_paid' ? 'Parcial' : 'Pendiente' }}
                      </span>
                    </td>
                    <td class="row-action">
                      <button class="btn btn-sm small primary" @click="openPaymentModal(p)">Registrar pago</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="pendingMeta" class="pagination">
              <div class="pagination-info">Página {{ pendingMeta.current_page }} / {{ pendingMeta.last_page }} — {{ pendingMeta.total }} citas</div>
              <div class="pagination-actions">
                <button :disabled="pendingMeta.current_page <= 1" class="icon-btn" @click="loadPendingPayments(pendingMeta.current_page - 1)">‹</button>
                <button :disabled="pendingMeta.current_page >= pendingMeta.last_page" class="icon-btn" @click="loadPendingPayments(pendingMeta.current_page + 1)">›</button>
              </div>
            </div>
          </template>
        </section>

        <!-- ============ INGRESOS ============ -->
        <section v-if="activeTab === 'ingresos'">
          <div class="income-summary" v-if="incomeSummary">
            <div class="income-summary-item">
              <span class="income-summary-label">Total ingresos</span>
              <span class="income-summary-value income-positive">{{ formatMoney(incomeSummary.total_income) }}</span>
            </div>
            <div class="income-summary-item">
              <span class="income-summary-label">Reembolsos</span>
              <span class="income-summary-value income-negative">{{ formatMoney(incomeSummary.total_refunded) }}</span>
            </div>
            <div class="income-summary-item">
              <span class="income-summary-label">Neto</span>
              <span class="income-summary-value">{{ formatMoney(incomeSummary.net) }}</span>
            </div>
          </div>

          <div class="finance-toolbar">
            <div class="filter-group">
              <select v-model="incomeProfessionalFilter" class="input input-sm" @change="loadIncomes(1)">
                <option value="">Todos los profesionales</option>
                <option v-for="p in incomeProfessionals" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <select v-model="incomeMethodFilter" class="input input-sm" @change="loadIncomes(1)">
                <option value="">Todos los métodos</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
              </select>
              <select v-model="incomeConceptFilter" class="input input-sm" @change="loadIncomes(1)">
                <option value="">Todos los conceptos</option>
                <option value="appointment">Cita</option>
                <option value="package">Bono</option>
                <option value="credit">Crédito</option>
                <option value="other">Manual</option>
              </select>
            </div>
            <label class="date-field">
              <span>Desde</span>
              <input v-model="incomeFromDate" type="date" class="filter-date" aria-label="Desde" />
            </label>
            <label class="date-field">
              <span>Hasta</span>
              <input v-model="incomeToDate" type="date" class="filter-date" aria-label="Hasta" />
            </label>
            <button class="btn btn-sm small primary" @click="loadIncomes(1)" :disabled="incomeLoading">
              {{ incomeLoading ? 'Cargando...' : 'Buscar' }}
            </button>
            <NewButton label="Registrar ingreso" @click="openIncomeModal()" />
          </div>

          <AppLoading v-if="incomeLoading" message="Cargando ingresos..." />

          <template v-else>
            <div v-if="incomes.length === 0" class="empty-card">No hay ingresos registrados.</div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Concepto</th>
                    <th>Profesional</th>
                    <th>Método</th>
                    <th>Importe</th>
                    <th>Estado</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="inc in incomes" :key="inc.id">
                    <td>{{ formatDate(inc.paid_at) }}</td>
                    <td>{{ inc.patient_name || '—' }}</td>
                    <td>
                      <span class="concept-chip" :class="'concept-' + inc.concept">{{ inc.concept_label }}</span>
                    </td>
                    <td>{{ inc.professional_name || '—' }}</td>
                    <td>{{ paymentLabel(inc.method) }}</td>
                    <td class="total-cell">{{ formatMoney(inc.amount) }}</td>
                    <td>
                      <span v-if="inc.status === 'refunded'" class="status-chip status-refunded">Reembolsado</span>
                      <span v-else class="status-chip status-completed">Cobrado</span>
                    </td>
                    <td class="row-action">
                      <button
                        v-if="inc.status !== 'refunded'"
                        class="btn btn-sm small danger"
                        @click="openRefundModal(inc)"
                      >Reembolsar</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="incomeMeta" class="pagination">
              <div class="pagination-info">Página {{ incomeMeta.current_page }} / {{ incomeMeta.last_page }} — {{ incomeMeta.total }} ingresos</div>
              <div class="pagination-actions">
                <button :disabled="incomeMeta.current_page <= 1" class="icon-btn" @click="loadIncomes(incomeMeta.current_page - 1)">‹</button>
                <button :disabled="incomeMeta.current_page >= incomeMeta.last_page" class="icon-btn" @click="loadIncomes(incomeMeta.current_page + 1)">›</button>
              </div>
            </div>
          </template>
        </section>

        <!-- Modal: Registrar ingreso manual -->
        <FormModal :show="showIncomeModal" @close="showIncomeModal = false" title="Registrar ingreso manual">
          <div class="field">
            <label class="label">Concepto</label>
            <input v-model="incomeForm.description" class="input" placeholder="Ej: Venta material, Curso, etc." />
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Importe (€)</label>
              <input v-model.number="incomeForm.amount" type="number" min="0.01" step="0.01" class="input" placeholder="0.00" />
            </div>
            <div class="field">
              <label class="label">Método de pago</label>
              <select v-model="incomeForm.method" class="input">
                <option value="">Seleccionar...</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Profesional (opcional)</label>
              <select v-model="incomeForm.professional_id" class="input">
                <option value="">Ninguno</option>
                <option v-for="p in incomeProfessionals" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div class="field">
              <label class="label">Fecha</label>
              <input v-model="incomeForm.date" type="date" class="input" />
            </div>
          </div>
          <div class="field">
            <label class="label">Notas (opcional)</label>
            <textarea v-model="incomeForm.notes" class="input" rows="2" placeholder="Notas adicionales..."></textarea>
          </div>
          <div class="actions">
            <button class="muted" @click="showIncomeModal = false">Cancelar</button>
            <button class="primary" :disabled="savingIncome" @click="saveIncome">{{ savingIncome ? 'Guardando...' : 'Registrar ingreso' }}</button>
          </div>
        </FormModal>

        <!-- Modal: Reembolsar -->
        <FormModal :show="showRefundModal" @close="showRefundModal = false" title="Reembolsar pago">
          <div class="refund-info">
            <div><strong>Pago:</strong> {{ formatMoney(refundPaymentData?.amount) }} — {{ refundPaymentData?.concept_label }}</div>
            <div v-if="refundPaymentData?.patient_name"><strong>Paciente:</strong> {{ refundPaymentData.patient_name }}</div>
          </div>
          <div class="field">
            <label class="label">Importe a reembolsar (€)</label>
            <input v-model.number="refundForm.amount" type="number" min="0.01" step="0.01" class="input" />
          </div>
          <div class="field">
            <label class="label">Motivo del reembolso</label>
            <textarea v-model="refundForm.reason" class="input" rows="2" placeholder="Describe el motivo del reembolso..."></textarea>
          </div>
          <div v-if="refundPaymentData?.invoice_id" class="field">
            <label class="refund-checkbox">
              <input type="checkbox" v-model="refundForm.generate_abono" />
              <span>Generar factura rectificativa (abono)</span>
            </label>
            <div class="field-help">Se creará automáticamente una factura rectificativa vinculada a la factura original.</div>
          </div>
          <div class="actions">
            <button class="muted" @click="showRefundModal = false">Cancelar</button>
            <button class="primary danger-action" :disabled="savingRefund" @click="saveRefund">{{ savingRefund ? 'Procesando...' : 'Confirmar reembolso' }}</button>
          </div>
        </FormModal>

        <!-- ============ GASTOS ============ -->
        <section v-if="activeTab === 'gastos'">
          <div class="finance-toolbar">
            <div class="search-wrapper">
              <input
                v-model="expenseQuery"
                class="search-input"
                placeholder="Buscar por concepto, proveedor o nº de factura"
                @input="debouncedLoadExpenses"
              />
            </div>
            <div class="filter-group">
              <select v-model="expenseCategoryFilter" class="input input-sm" @change="loadExpenses(1)">
                <option value="">Todas las categorías</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <select v-model="expenseProviderFilter" class="input input-sm" @change="loadExpenses(1)">
                <option value="">Todos los proveedores</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <label class="date-field">
              <span>Desde</span>
              <input v-model="expenseFromDate" type="date" class="filter-date" aria-label="Desde" @change="loadExpenses(1)" />
            </label>
            <label class="date-field">
              <span>Hasta</span>
              <input v-model="expenseToDate" type="date" class="filter-date" aria-label="Hasta" @change="loadExpenses(1)" />
            </label>
            <div class="toolbar-actions">
              <button class="btn btn-sm small" @click="openCategoryModal">Categorías</button>
              <NewButton label="Nuevo gasto" @click="openExpenseModal()" />
            </div>
          </div>

          <AppLoading v-if="expensesLoading" message="Cargando gastos..." />

          <template v-else>
            <div v-if="expenses.length === 0" class="empty-card">No hay gastos registrados.</div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>Base</th>
                    <th>IVA</th>
                    <th>Total</th>
                    <th>Pago</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="e in expenses" :key="e.id">
                    <td>{{ formatDate(e.date) }}</td>
                    <td class="concept-cell">{{ e.concept }}<span v-if="e.receipt_number" class="receipt-num">· {{ e.receipt_number }}</span></td>
                    <td>
                      <span v-if="e.category" class="category-chip" :style="categoryStyle(e.category)">{{ e.category.name }}</span>
                      <span v-else class="muted">—</span>
                    </td>
                    <td>{{ e.provider?.name || e.supplier || '—' }}</td>
                    <td>{{ formatMoney(e.amount) }}</td>
                    <td>{{ formatTax(e.tax_rate) }}</td>
                    <td class="total-cell">{{ formatMoney(e.total) }}</td>
                    <td>{{ paymentLabel(e.payment_method) }}</td>
                    <td class="row-action">
                      <EditButton @click="openExpenseModal(e)" />
                      <BtnTrash @click="removeExpense(e)" title="Eliminar gasto" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="expensesMeta" class="pagination">
              <div class="pagination-info">Página {{ expensesMeta.current_page }} / {{ expensesMeta.last_page }} — {{ expensesMeta.total }} gastos</div>
              <div class="pagination-actions">
                <button :disabled="expensesMeta.current_page <= 1" class="icon-btn" @click="loadExpenses(expensesMeta.current_page - 1)">‹</button>
                <button :disabled="expensesMeta.current_page >= expensesMeta.last_page" class="icon-btn" @click="loadExpenses(expensesMeta.current_page + 1)">›</button>
              </div>
            </div>
          </template>
        </section>

        <!-- ============ PROVEEDORES ============ -->
        <section v-if="activeTab === 'proveedores'">
          <div class="finance-toolbar">
            <div class="search-wrapper">
              <input
                v-model="providerQuery"
                class="search-input"
                placeholder="Buscar proveedor..."
                @input="debouncedLoadProviders"
              />
            </div>
            <NewButton label="Nuevo proveedor" @click="openProviderModal()" />
          </div>

          <AppLoading v-if="providersLoading" message="Cargando proveedores..." />

          <template v-else>
            <div v-if="providers.length === 0" class="empty-card">No hay proveedores registrados.</div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Nombre</th>
                    <th>NIF</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in providers" :key="p.id">
                    <td class="concept-cell">{{ p.name }}</td>
                    <td>{{ p.nif || '—' }}</td>
                    <td>{{ p.email || '—' }}</td>
                    <td>{{ p.phone || '—' }}</td>
                    <td>{{ p.address || '—' }}</td>
                    <td class="row-action">
                      <EditButton @click="openProviderModal(p)" />
                      <BtnTrash @click="removeProvider(p)" title="Eliminar proveedor" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </section>

        <!-- ============ TARIFAS ============ -->
        <section v-if="activeTab === 'tarifas'">
          <div class="section-copy">
            Define el <strong>coste por hora</strong> de cada miembro del equipo. Se usa para calcular el coste laboral y el margen por cita en el dashboard de beneficios.
          </div>

          <AppLoading v-if="ratesLoading" message="Cargando equipo..." />

          <template v-else>
            <div v-if="professionals.length === 0" class="empty-card">
              No hay miembros en el equipo. Añádelos desde <router-link to="/team" class="link">Equipo</router-link>.
            </div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Miembro</th>
                    <th>Coste/hora (€)</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in professionals" :key="p.id">
                    <td>{{ p.name }}</td>
                    <td class="rate-cell">
                      <input v-model.number="p.cost_per_hour" type="number" min="0" step="0.5" class="input counter-input" @change="markDirty(p)" />
                    </td>
                    <td class="row-action">
                      <button class="btn btn-sm small primary" :disabled="p._saving" @click="saveRate(p)">{{ p._saving ? 'Guardando...' : 'Guardar' }}</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </section>

        <!-- ============ BENEFICIOS ============ -->
        <section v-if="activeTab === 'beneficios'">
          <div class="finance-toolbar">
            <label class="date-field">
              <span>Desde</span>
              <input v-model="fromDate" type="date" class="filter-date" aria-label="Desde" />
            </label>
            <label class="date-field">
              <span>Hasta</span>
              <input v-model="toDate" type="date" class="filter-date" aria-label="Hasta" />
            </label>
            <button class="btn btn-sm small primary" @click="loadBenefits" :disabled="benefitsLoading">{{ benefitsLoading ? 'Calculando...' : 'Calcular' }}</button>
            <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
          </div>

          <AppLoading v-if="benefitsLoading" message="Calculando beneficios..." />

          <template v-else-if="benefits">
            <div class="benefits-cards">
              <div class="benefit-card">
                <div class="benefit-label">Ingresos</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.revenue) }}</div>
                <div v-if="variationOf('revenue') !== null" class="variation" :class="variationClass('revenue')">
                  {{ variationText('revenue') }} vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Coste personal</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.labor_cost) }}</div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Gastos registrados</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.expenses) }}</div>
                <div v-if="variationOf('expenses') !== null" class="variation" :class="variationClass('expenses')">
                  {{ variationText('expenses') }} vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Coste total</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.cost) }}</div>
              </div>
              <div class="benefit-card accent" :class="{ negative: (benefits.totals.profit || 0) < 0 }">
                <div class="benefit-label">Beneficio</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.profit) }}</div>
                <div v-if="variationOf('profit') !== null" class="variation" :class="variationClass('profit')">
                  {{ variationText('profit') }} vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card accent">
                <div class="benefit-label">Margen</div>
                <div class="benefit-value">{{ benefits.totals.margin_percentage === null ? '—' : benefits.totals.margin_percentage + ' %' }}</div>
                <div v-if="variationOf('margin_percentage') !== null" class="variation" :class="variationClass('margin_percentage')">
                  {{ variationText('margin_percentage') }} p.p. vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Ticket medio</div>
                <div class="benefit-value">{{ formatMoney(ticketMedio) }}</div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Operaciones pagadas</div>
                <div class="benefit-value">{{ benefits.totals.paid_operations_count }}</div>
              </div>
            </div>

            <div class="benefits-grid">
              <div v-if="benefits.by_service.length" class="table-wrap">
                <h3 class="benefit-section-title">Ingresos por servicio</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Servicio</th><th>Citas</th><th>Ingresos</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.by_service" :key="row.name">
                      <td>{{ row.name }}</td>
                      <td>{{ row.count }}</td>
                      <td>{{ formatMoney(row.revenue) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="benefits.by_professional.length" class="table-wrap">
                <h3 class="benefit-section-title">Contribución por profesional</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Profesional</th><th>Ingresos</th><th>Coste laboral</th><th>Contribución</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.by_professional" :key="row.user_id">
                      <td>{{ row.user_name }}</td>
                      <td>{{ formatMoney(row.revenue) }}</td>
                      <td>{{ formatMoney(row.labor_cost) }}</td>
                      <td class="total-cell">{{ formatMoney(row.contribution) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="benefits.by_category.length" class="table-wrap">
                <h3 class="benefit-section-title">Gastos por categoría</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Categoría</th><th>Total</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.by_category" :key="row.name">
                      <td>{{ row.name }}</td>
                      <td>{{ formatMoney(row.total) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="benefits.revenue_by_payment_method?.length" class="table-wrap">
                <h3 class="benefit-section-title">Ingresos por método de pago</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Método</th><th>Operaciones</th><th>Total</th><th>% del total</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.revenue_by_payment_method" :key="row.method">
                      <td>{{ row.label }}</td>
                      <td>{{ row.count }}</td>
                      <td>{{ formatMoney(row.total) }}</td>
                      <td>{{ row.percentage }}%</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="!benefits.by_service.length && !benefits.by_professional.length && !benefits.by_category.length && !benefits.revenue_by_payment_method?.length" class="empty-card">
              No hay datos para el período seleccionado.
            </div>
          </template>
        </section>

        <!-- ============ INFORMES ============ -->
        <section v-if="activeTab === 'informes'">
          <div class="finance-toolbar">
            <select v-model="reportType" class="input input-sm" @change="loadReport">
              <option value="income">Ingresos</option>
              <option value="expenses">Gastos</option>
              <option value="profit">Beneficio</option>
              <option value="professional">Por profesional</option>
              <option value="service">Por servicio</option>
            </select>
            <select v-model="reportGroupBy" class="input input-sm" @change="loadReport">
              <option value="day">Día</option>
              <option value="week">Semana</option>
              <option value="month">Mes</option>
            </select>
            <label class="date-field">
              <span>Desde</span>
              <input v-model="reportFromDate" type="date" class="filter-date" aria-label="Desde" @change="loadReport" />
            </label>
            <label class="date-field">
              <span>Hasta</span>
              <input v-model="reportToDate" type="date" class="filter-date" aria-label="Hasta" @change="loadReport" />
            </label>
            <div class="toolbar-actions">
              <button class="btn btn-sm small" :disabled="!reportData || reportLoading" @click="exportReportCSV">Exportar CSV</button>
            </div>
          </div>

          <AppLoading v-if="reportLoading" message="Generando informe..." />

          <template v-else>
            <div v-if="!reportData" class="empty-card">Selecciona los filtros y pulsа «Generar».</div>

            <template v-else>
              <div v-if="reportData.rows.length === 0" class="empty-card">No hay datos para este informe.</div>

              <div v-else class="table-wrap">
                <table class="entity-table">
                  <thead>
                    <tr>
                      <th v-for="(h, i) in reportData.headers" :key="i" :class="{ 'report-num-col': isNumericReportColumn(i) }">{{ h }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, ri) in reportData.rows" :key="ri">
                      <td v-for="(cell, ci) in row" :key="ci" :class="{ 'report-num-col': isNumericReportColumn(ci) }">{{ cell }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Summary row -->
              <div class="report-summary" v-if="sortedReportSummary.length">
                <div v-for="[key, value] in sortedReportSummary" :key="key" class="report-summary-item">
                  <span class="report-summary-label">{{ reportSummaryLabel(key) }}</span>
                  <span class="report-summary-value">{{ reportSummaryValue(key, value) }}</span>
                </div>
              </div>
            </template>
          </template>
        </section>
      </div>
      <FormModal :show="showExpenseModal" :title="editingExpense ? 'Editar gasto' : 'Nuevo gasto'" @close="showExpenseModal = false">
        <form @submit.prevent="saveExpense">
          <div class="field">
            <label class="label">Concepto *</label>
            <input v-model="expenseForm.concept" class="input" required />
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Categoría</label>
              <select v-model.number="expenseForm.category_id" class="input">
                <option :value="null">Sin categoría</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="field">
              <label class="label">Proveedor</label>
              <select v-model.number="expenseForm.provider_id" class="input">
                <option :value="null">Sin proveedor</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Base (sin IVA) *</label>
              <input v-model.number="expenseForm.amount" type="number" min="0" step="0.01" class="input" required />
            </div>
            <div class="field">
              <label class="label">IVA (%)</label>
              <input v-model.number="expenseForm.tax_rate" type="number" min="0" max="100" step="0.01" class="input" />
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Fecha</label>
              <input v-model="expenseForm.date" type="date" class="date-field-input" aria-label="Fecha del gasto" />
            </div>
            <div class="field">
              <label class="label">Forma de pago</label>
              <select v-model="expenseForm.payment_method" class="input">
                <option value="">Sin especificar</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Nº de factura / recibo</label>
              <input v-model="expenseForm.receipt_number" class="input" />
            </div>
            <div class="field">
              <label class="label">Total (con IVA)</label>
              <div class="total-preview">{{ formatMoney(computedExpenseTotal) }}</div>
            </div>
          </div>
          <div class="field">
            <label class="label">Notas</label>
            <textarea v-model="expenseForm.notes" class="input" rows="2"></textarea>
          </div>
          <div class="actions">
            <SaveButton type="submit" :disabled="savingExpense" :saving="savingExpense" />
            <button type="button" class="muted" @click="showExpenseModal = false">Cancelar</button>
          </div>
        </form>
      </FormModal>

      <!-- Modal: categorías -->
      <FormModal :show="showCategoryModal" title="Categorías de gasto" @close="showCategoryModal = false">
        <div class="cat-row" v-for="c in categories" :key="c.id">
          <span class="category-dot" :style="{ background: c.color || '#9ca3af' }"></span>
          <span class="cat-name">{{ c.name }}</span>
          <span class="cat-desc">{{ c.description || '' }}</span>
          <BtnTrash variant="danger" @click="removeCategory(c)" title="Eliminar categoría" />
        </div>

        <form class="cat-new" @submit.prevent="saveCategory">
          <input v-model="categoryForm.name" class="input" placeholder="Nombre de la categoría" required />
          <input v-model="categoryForm.color" type="color" class="color-input" />
          <NewButton type="submit" label="Añadir" :disabled="savingCategory" />
        </form>
      </FormModal>

      <!-- Modal: proveedor -->
      <FormModal :show="showProviderModal" :title="editingProvider ? 'Editar proveedor' : 'Nuevo proveedor'" @close="showProviderModal = false">
        <form @submit.prevent="saveProvider">
          <div class="field">
            <label class="label">Nombre *</label>
            <input v-model="providerForm.name" class="input" required />
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">NIF</label>
              <input v-model="providerForm.nif" class="input" />
            </div>
            <div class="field">
              <label class="label">Email</label>
              <input v-model="providerForm.email" type="email" class="input" />
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Teléfono</label>
              <input v-model="providerForm.phone" class="input" />
            </div>
            <div class="field">
              <label class="label">Dirección</label>
              <input v-model="providerForm.address" class="input" />
            </div>
          </div>
          <div class="field">
            <label class="label">Notas</label>
            <textarea v-model="providerForm.notes" class="input" rows="2"></textarea>
          </div>
          <div class="actions">
            <SaveButton type="submit" :disabled="savingProvider" :saving="savingProvider" />
            <button type="button" class="muted" @click="showProviderModal = false">Cancelar</button>
          </div>
        </form>
      </FormModal>

      <!-- Modal: registrar pago pendiente -->
      <FormModal :show="showPaymentModal" :title="`Registrar pago — ${paymentModalData?.patient_name || ''}`" @close="showPaymentModal = false">
        <form @submit.prevent="savePendingPayment">
          <div class="field">
            <label class="label">Paciente</label>
            <div class="total-preview">{{ paymentModalData?.patient_name }}</div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Importe total</label>
              <div class="total-preview">{{ formatMoney(paymentModalData?.price) }}</div>
            </div>
            <div class="field">
              <label class="label">Ya pagado</label>
              <div class="total-preview">{{ formatMoney(paymentModalData?.paid_amount) }}</div>
            </div>
          </div>
          <div class="field">
            <label class="label">Importe a pagar *</label>
            <input v-model.number="paymentForm.amount" type="number" min="0.01" :max="paymentModalData?.pending_amount" step="0.01" class="input" required />
            <div class="field-help">Máximo: {{ formatMoney(paymentModalData?.pending_amount) }}</div>
          </div>
          <div class="field">
            <label class="label">Método de pago *</label>
            <select v-model="paymentForm.method" class="input" required>
              <option value="">Seleccionar...</option>
              <option value="cash">Efectivo</option>
              <option value="card">Tarjeta</option>
              <option value="transfer">Transferencia</option>
            </select>
          </div>
          <div class="field">
            <label class="label">Notas</label>
            <textarea v-model="paymentForm.notes" class="input" rows="2"></textarea>
          </div>
          <div class="actions">
            <SaveButton type="submit" :disabled="savingPayment" :saving="savingPayment" />
            <button type="button" class="muted" @click="showPaymentModal = false">Cancelar</button>
          </div>
        </form>
      </FormModal>

      <FinanceHelpModal v-if="showHelp" @close="showHelp = false" />
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import FormModal from '../../components/FormModal.vue'
import BtnTrash from '../../components/BtnTrash.vue'
import FinanceHelpModal from '../../components/finance/FinanceHelpModal.vue'
import EvolutionChart from '../../components/finance/EvolutionChart.vue'
import api from '../../services/api'
import { getLoadErrorMessage } from '../../shared/httpErrors'
import { confirmDelete } from '../../shared/confirmDelete'

const toast = useToast()

const tabs = [
  { key: 'resumen', label: 'Resumen' },
  { key: 'pendientes', label: 'Pendientes' },
  { key: 'ingresos', label: 'Ingresos' },
  { key: 'gastos', label: 'Gastos' },
  { key: 'proveedores', label: 'Proveedores' },
  { key: 'tarifas', label: 'Tarifas' },
  { key: 'beneficios', label: 'Beneficios' },
  { key: 'informes', label: 'Informes' },
]
const activeTab = ref('resumen')
const showHelp = ref(false)

// ---------- Gastos ----------
const expenses = ref([])
const expensesMeta = ref(null)
const expensesLoading = ref(false)
const expenseQuery = ref('')
const expenseCategoryFilter = ref('')
const expenseProviderFilter = ref('')
const expenseFromDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const expenseToDate = ref(new Date().toISOString().slice(0, 10))
const categories = ref([])
const showExpenseModal = ref(false)
const editingExpense = ref(null)
const savingExpense = ref(false)
let expenseSearchTimer = null

const emptyExpenseForm = () => ({
  concept: '',
  category_id: null,
  provider_id: null,
  supplier: '',
  amount: null,
  tax_rate: 0,
  date: new Date().toISOString().slice(0, 10),
  payment_method: '',
  receipt_number: '',
  notes: '',
})
const expenseForm = reactive(emptyExpenseForm())

const computedExpenseTotal = computed(() => {
  const base = Number(expenseForm.amount || 0)
  const tax = Number(expenseForm.tax_rate || 0)
  return base * (1 + tax / 100)
})

async function loadCategories() {
  try {
    const res = await api.get('/finance/expense-categories')
    categories.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch {
    categories.value = []
  }
}

async function loadExpenses(page = 1) {
  expensesLoading.value = true
  try {
    const res = await api.get('/finance/expenses', {
      params: {
        page,
        per_page: 15,
        q: expenseQuery.value || undefined,
        category_id: expenseCategoryFilter.value || undefined,
        provider_id: expenseProviderFilter.value || undefined,
        from_date: expenseFromDate.value || undefined,
        to_date: expenseToDate.value || undefined,
      },
    })
    expenses.value = Array.isArray(res.data?.data) ? res.data.data : []
    expensesMeta.value = res.data?.meta ?? null
  } catch (e) {
    expenses.value = []
    expensesMeta.value = null
    toast.error(getLoadErrorMessage(e, 'gastos'))
  } finally {
    expensesLoading.value = false
  }
}

function debouncedLoadExpenses() {
  clearTimeout(expenseSearchTimer)
  expenseSearchTimer = setTimeout(() => loadExpenses(1), 250)
}

function openExpenseModal(expense = null) {
  editingExpense.value = expense
  Object.assign(expenseForm, emptyExpenseForm())
  if (expense) {
    Object.assign(expenseForm, {
      concept: expense.concept,
      category_id: expense.category_id ?? null,
      provider_id: expense.provider?.id ?? expense.provider_id ?? null,
      supplier: expense.supplier || '',
      amount: expense.amount,
      tax_rate: expense.tax_rate,
      date: expense.date || new Date().toISOString().slice(0, 10),
      payment_method: expense.payment_method || '',
      receipt_number: expense.receipt_number || '',
      notes: expense.notes || '',
    })
  }
  showExpenseModal.value = true
}

async function saveExpense() {
  savingExpense.value = true
  try {
    const payload = {
      concept: expenseForm.concept,
      category_id: expenseForm.category_id || null,
      provider_id: expenseForm.provider_id || null,
      supplier: expenseForm.supplier || null,
      amount: Number(expenseForm.amount || 0),
      tax_rate: Number(expenseForm.tax_rate || 0),
      date: expenseForm.date || null,
      payment_method: expenseForm.payment_method || null,
      receipt_number: expenseForm.receipt_number || null,
      notes: expenseForm.notes || null,
    }
    if (editingExpense.value) {
      await api.put(`/finance/expenses/${editingExpense.value.id}`, payload)
      toast.success('Gasto actualizado')
    } else {
      await api.post('/finance/expenses', payload)
      toast.success('Gasto registrado')
    }
    showExpenseModal.value = false
    loadExpenses(expensesMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error guardando el gasto')
  } finally {
    savingExpense.value = false
  }
}

async function removeExpense(expense) {
  const confirmed = await confirmDelete({
    title: 'Eliminar gasto',
    text: `¿Eliminar el gasto "${expense.concept}"? Esta acción no se puede deshacer.`,
  })
  if (!confirmed) return
  try {
    await api.delete(`/finance/expenses/${expense.id}`)
    toast.success('Gasto eliminado')
    loadExpenses(expensesMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error eliminando el gasto')
  }
}

// ---------- Categorías ----------
const showCategoryModal = ref(false)
const savingCategory = ref(false)
const categoryForm = reactive({ name: '', color: '#6366f1' })

function openCategoryModal() {
  categoryForm.name = ''
  showCategoryModal.value = true
}

async function saveCategory() {
  if (!categoryForm.name.trim()) return
  savingCategory.value = true
  try {
    await api.post('/finance/expense-categories', {
      name: categoryForm.name.trim(),
      color: categoryForm.color || null,
    })
    toast.success('Categoría creada')
    categoryForm.name = ''
    await loadCategories()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error creando la categoría')
  } finally {
    savingCategory.value = false
  }
}

async function removeCategory(category) {
  const confirmed = await confirmDelete({
    title: 'Eliminar categoría',
    text: `¿Eliminar la categoría "${category.name}"? Esta acción no se puede deshacer.`,
  })
  if (!confirmed) return
  try {
    await api.delete(`/finance/expense-categories/${category.id}`)
    toast.success('Categoría eliminada')
    await loadCategories()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error eliminando la categoría')
  }
}

// ---------- Proveedores ----------
const providers = ref([])
const providersLoading = ref(false)
const providerQuery = ref('')
const showProviderModal = ref(false)
const editingProvider = ref(null)
const savingProvider = ref(false)
let providerSearchTimer = null

const emptyProviderForm = () => ({
  name: '',
  nif: '',
  email: '',
  phone: '',
  address: '',
  notes: '',
})
const providerForm = reactive(emptyProviderForm())

async function loadProviders() {
  providersLoading.value = true
  try {
    const res = await api.get('/finance/providers')
    providers.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch {
    providers.value = []
  } finally {
    providersLoading.value = false
  }
}

function debouncedLoadProviders() {
  clearTimeout(providerSearchTimer)
  providerSearchTimer = setTimeout(() => loadProviders(), 250)
}

function openProviderModal(provider = null) {
  editingProvider.value = provider
  Object.assign(providerForm, emptyProviderForm())
  if (provider) {
    Object.assign(providerForm, {
      name: provider.name,
      nif: provider.nif || '',
      email: provider.email || '',
      phone: provider.phone || '',
      address: provider.address || '',
      notes: provider.notes || '',
    })
  }
  showProviderModal.value = true
}

async function saveProvider() {
  savingProvider.value = true
  try {
    const payload = {
      name: providerForm.name,
      nif: providerForm.nif || null,
      email: providerForm.email || null,
      phone: providerForm.phone || null,
      address: providerForm.address || null,
      notes: providerForm.notes || null,
    }
    if (editingProvider.value) {
      await api.put(`/finance/providers/${editingProvider.value.id}`, payload)
      toast.success('Proveedor actualizado')
    } else {
      await api.post('/finance/providers', payload)
      toast.success('Proveedor creado')
    }
    showProviderModal.value = false
    loadProviders()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error guardando el proveedor')
  } finally {
    savingProvider.value = false
  }
}

async function removeProvider(provider) {
  const confirmed = await confirmDelete({
    title: 'Eliminar proveedor',
    text: `¿Eliminar el proveedor "${provider.name}"? Esta acción no se puede deshacer.`,
  })
  if (!confirmed) return
  try {
    await api.delete(`/finance/providers/${provider.id}`)
    toast.success('Proveedor eliminado')
    loadProviders()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error eliminando el proveedor')
  }
}

// ---------- Tarifas ----------
const professionals = ref([])
const ratesLoading = ref(false)

async function loadRates() {
  ratesLoading.value = true
  try {
    const [teamRes, ratesRes] = await Promise.all([
      api.get('/team/users', { params: { per_page: 100 } }),
      api.get('/finance/professional-rates'),
    ])

    const ratesMap = {}
    for (const r of ratesRes.data?.data || []) {
      ratesMap[r.user_id] = Number(r.cost_per_hour || 0)
    }

    professionals.value = (teamRes.data?.data || []).map(u => ({
        id: u.id,
        name: u.name,
        cost_per_hour: ratesMap[u.id] ?? 0,
        _dirty: false,
        _saving: false,
      }))
  } catch (e) {
    professionals.value = []
    toast.error(getLoadErrorMessage(e, 'profesionales'))
  } finally {
    ratesLoading.value = false
  }
}

function markDirty(p) {
  p._dirty = true
}

async function saveRate(p) {
  p._saving = true
  try {
    await api.put(`/finance/professional-rates/${p.id}`, {
      cost_per_hour: Number(p.cost_per_hour || 0),
    })
    p._dirty = false
    toast.success('Tarifa actualizada')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error guardando la tarifa')
  } finally {
    p._saving = false
  }
}

// ---------- Beneficios ----------
const fromDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const toDate = ref(new Date().toISOString().slice(0, 10))
const benefits = ref(null)
const benefitsLoading = ref(false)

async function loadBenefits() {
  benefitsLoading.value = true
  benefits.value = null
  try {
    const res = await api.get('/finance/benefits', {
      params: {
        from_date: fromDate.value || undefined,
        to_date: toDate.value || undefined,
      },
    })
    benefits.value = res.data?.data ?? null
  } catch (e) {
    benefits.value = null
    toast.error(getLoadErrorMessage(e, 'beneficios'))
  } finally {
    benefitsLoading.value = false
  }
}

function variationOf(key) {
  const value = benefits.value?.variation?.[key]
  return value === null || value === undefined ? null : Number(value)
}

function variationText(key) {
  const value = variationOf(key)
  const prefix = value > 0 ? '+' : ''
  return `${prefix}${Number(value).toFixed(2)} %`
}

function variationClass(key) {
  const value = variationOf(key)
  return value === 0 ? '' : (value > 0 ? 'up' : 'down')
}

const ticketMedio = computed(() => {
  if (!benefits.value?.totals?.paid_operations_count) return 0
  return benefits.value.totals.revenue / benefits.value.totals.paid_operations_count
})

// ---------- Pendientes ----------
const pendingPayments = ref([])
const pendingMeta = ref(null)
const pendingSummary = ref(null)
const pendingLoading = ref(false)
const pendingProfessionalFilter = ref('')
const pendingFromDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const pendingToDate = ref(new Date().toISOString().slice(0, 10))
const pendingProfessionals = ref([])
const showPaymentModal = ref(false)
const paymentModalData = ref(null)
const savingPayment = ref(false)
const paymentForm = reactive({ amount: null, method: '', notes: '' })

async function loadPendingPayments(page = 1) {
  pendingLoading.value = true
  try {
    const res = await api.get('/finance/pending-payments', {
      params: {
        page,
        per_page: 15,
        professional_id: pendingProfessionalFilter.value || undefined,
        from_date: pendingFromDate.value || undefined,
        to_date: pendingToDate.value || undefined,
      },
    })
    pendingPayments.value = Array.isArray(res.data?.data) ? res.data.data : []
    pendingMeta.value = res.data?.meta ?? null
    pendingSummary.value = res.data?.summary ?? null
  } catch (e) {
    pendingPayments.value = []
    pendingMeta.value = null
    pendingSummary.value = null
    toast.error(getLoadErrorMessage(e, 'pendientes de cobro'))
  } finally {
    pendingLoading.value = false
  }
}

async function loadPendingProfessionals() {
  try {
    const res = await api.get('/agenda/professionals')
    pendingProfessionals.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch {
    pendingProfessionals.value = []
  }
}

function openPaymentModal(payment) {
  paymentModalData.value = payment
  paymentForm.amount = payment.pending_amount
  paymentForm.method = ''
  paymentForm.notes = ''
  showPaymentModal.value = true
}

async function savePendingPayment() {
  if (!paymentForm.method) {
    toast.error('Selecciona un método de pago')
    return
  }
  if (!paymentForm.amount || paymentForm.amount <= 0) {
    toast.error('Introduce un importe válido')
    return
  }
  savingPayment.value = true
  try {
    await api.post(`/finance/pending-payments/${paymentModalData.value.appointment_id}/register-payment`, {
      amount: Number(paymentForm.amount),
      method: paymentForm.method,
      notes: paymentForm.notes || null,
    })
    toast.success('Pago registrado')
    showPaymentModal.value = false
    loadPendingPayments(pendingMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error registrando el pago')
  } finally {
    savingPayment.value = false
  }
}

// ---------- Ingresos ----------
const incomes = ref([])
const incomeMeta = ref(null)
const incomeSummary = ref(null)
const incomeLoading = ref(false)
const incomeProfessionalFilter = ref('')
const incomeMethodFilter = ref('')
const incomeConceptFilter = ref('')
const incomeFromDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const incomeToDate = ref(new Date().toISOString().slice(0, 10))
const incomeProfessionals = ref([])
const showIncomeModal = ref(false)
const savingIncome = ref(false)
const incomeForm = reactive({ description: '', amount: null, method: '', professional_id: '', date: new Date().toISOString().slice(0, 10), notes: '' })
const showRefundModal = ref(false)
const refundPaymentData = ref(null)
const savingRefund = ref(false)
const refundForm = reactive({ amount: null, reason: '', generate_abono: false })

async function loadIncomes(page = 1) {
  incomeLoading.value = true
  try {
    const res = await api.get('/finance/income', {
      params: {
        page,
        per_page: 15,
        professional_id: incomeProfessionalFilter.value || undefined,
        method: incomeMethodFilter.value || undefined,
        concept: incomeConceptFilter.value || undefined,
        from_date: incomeFromDate.value || undefined,
        to_date: incomeToDate.value || undefined,
      },
    })
    incomes.value = Array.isArray(res.data?.data) ? res.data.data : []
    incomeMeta.value = res.data?.meta ?? null
    // Compute summary from current page data
    const allIncome = incomes.value.reduce((sum, i) => sum + (i.status === 'completed' ? i.amount : 0), 0)
    const allRefunded = incomes.value.reduce((sum, i) => sum + (i.status === 'refunded' ? i.amount : 0), 0)
    incomeSummary.value = { total_income: allIncome, total_refunded: allRefunded, net: allIncome - allRefunded }
  } catch (e) {
    incomes.value = []
    incomeMeta.value = null
    incomeSummary.value = null
    toast.error(getLoadErrorMessage(e, 'ingresos'))
  } finally {
    incomeLoading.value = false
  }
}

async function loadIncomeProfessionals() {
  try {
    const res = await api.get('/agenda/professionals')
    incomeProfessionals.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch {
    incomeProfessionals.value = []
  }
}

function openIncomeModal() {
  incomeForm.description = ''
  incomeForm.amount = null
  incomeForm.method = ''
  incomeForm.professional_id = ''
  incomeForm.date = new Date().toISOString().slice(0, 10)
  incomeForm.notes = ''
  showIncomeModal.value = true
}

async function saveIncome() {
  if (!incomeForm.amount || incomeForm.amount <= 0) {
    toast.error('Introduce un importe válido')
    return
  }
  if (!incomeForm.method) {
    toast.error('Selecciona un método de pago')
    return
  }
  savingIncome.value = true
  try {
    await api.post('/finance/income', {
      amount: Number(incomeForm.amount),
      method: incomeForm.method,
      description: incomeForm.description || null,
      professional_id: incomeForm.professional_id || null,
      date: incomeForm.date,
      notes: incomeForm.notes || null,
    })
    toast.success('Ingreso registrado')
    showIncomeModal.value = false
    loadIncomes(incomeMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error registrando el ingreso')
  } finally {
    savingIncome.value = false
  }
}

function openRefundModal(payment) {
  refundPaymentData.value = payment
  refundForm.amount = payment.amount
  refundForm.reason = ''
  refundForm.generate_abono = false
  showRefundModal.value = true
}

async function saveRefund() {
  if (!refundForm.reason.trim()) {
    toast.error('Introduce el motivo del reembolso')
    return
  }
  if (!refundForm.amount || refundForm.amount <= 0) {
    toast.error('Introduce un importe válido')
    return
  }
  savingRefund.value = true
  try {
    const res = await api.post(`/finance/payments/${refundPaymentData.value.id}/refund`, {
      amount: Number(refundForm.amount),
      reason: refundForm.reason,
      generate_abono: refundForm.generate_abono,
    })
    const abonoMsg = res.data?.data?.abono?.created ? ' Factura rectificativa generada.' : ''
    toast.success('Reembolso procesado.' + abonoMsg)
    showRefundModal.value = false
    loadIncomes(incomeMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error procesando el reembolso')
  } finally {
    savingRefund.value = false
  }
}

// ---------- Resumen ----------
const summary = ref(null)
const summaryLoading = ref(false)

async function loadSummary() {
  summaryLoading.value = true
  try {
    const res = await api.get('/finance/summary')
    summary.value = res.data?.data ?? null
  } catch (e) {
    summary.value = null
    toast.error(getLoadErrorMessage(e, 'resumen financiero'))
  } finally {
    summaryLoading.value = false
  }
}

function summaryVariationText(value) {
  if (value === null || value === undefined) return null
  const prefix = value > 0 ? '+' : ''
  return `${prefix}${Number(value).toFixed(1)} %`
}

function summaryVariationClass(value) {
  if (value === null || value === undefined) return ''
  return value > 0 ? 'up' : value < 0 ? 'down' : ''
}

// ---------- Helpers ----------
function formatMoney(value) {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}

function formatTax(value) {
  return `${Number(value || 0).toFixed(2)} %`
}

function formatDate(value) {
  if (!value) return '—'
  const parts = String(value).split('-')
  if (parts.length !== 3) return value
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

function paymentLabel(method) {
  const labels = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' }
  return labels[method] || '—'
}

function categoryStyle(category) {
  return { background: (category.color || '#e5e7eb') + '22', color: category.color || '#374151', borderColor: (category.color || '#e5e7eb') }
}

// ---------- Informes ----------
const reportType = ref('income')
const reportGroupBy = ref('day')
const reportFromDate = ref('')
const reportToDate = ref('')
const reportData = ref(null)
const reportLoading = ref(false)

async function loadReport() {
  reportLoading.value = true
  reportData.value = null
  try {
    const res = await api.get(`/finance/reports/${reportType.value}`, {
      params: {
        from_date: reportFromDate.value || undefined,
        to_date: reportToDate.value || undefined,
        group_by: reportGroupBy.value,
      },
    })
    reportData.value = res.data?.data ?? null
  } catch (e) {
    reportData.value = null
    toast.error(getLoadErrorMessage(e, 'informes'))
  } finally {
    reportLoading.value = false
  }
}

function exportReportCSV() {
  if (!reportData.value) return
  const params = new URLSearchParams()
  params.set('from_date', reportFromDate.value || '')
  params.set('to_date', reportToDate.value || '')
  params.set('group_by', reportGroupBy.value)
  window.open(`/api/finance/reports/${reportType.value}/export?${params.toString()}`, '_blank')
}

function isNumericReportColumn(colIndex) {
  if (!reportData.value?.rows?.length) return false
  return reportData.value.rows.some((row) => typeof row[colIndex] === 'number')
}

// Conteos primero, luego el resto en el orden original del backend
const sortedReportSummary = computed(() => {
  if (!reportData.value?.summary) return []
  const countKeys = ['count', 'total_count']
  return Object.entries(reportData.value.summary).sort(([a], [b]) => {
    const ia = countKeys.indexOf(a)
    const ib = countKeys.indexOf(b)
    if (ia === -1 && ib === -1) return 0
    if (ia === -1) return 1
    if (ib === -1) return -1
    return ia - ib
  })
})

function reportSummaryLabel(key) {  const labels = {
    total: 'Total',
    count: 'Nº registros',
    total_revenue: 'Total ingresos',
    total_expenses: 'Total gastos',
    total_profit: 'Beneficio neto',
    margin_percentage: 'Margen %',
    total_labor: 'Coste laboral',
    total_contribution: 'Contribución',
    total_count: 'Nº citas',
    avg_ticket: 'Ticket medio',
  }
  return labels[key] || key
}

function reportSummaryValue(key, value) {
  if (value === null || value === undefined) return '—'
  if (key === 'count' || key === 'total_count') return Number(value).toLocaleString('es-ES')
  if (key === 'margin_percentage') return value + ' %'
  if (typeof value === 'number') return formatMoney(value)
  return value
}

onMounted(async () => {
  await Promise.all([
    loadSummary(),
    loadCategories(),
    loadProviders(),
    loadPendingProfessionals(),
    loadPendingPayments(1),
    loadExpenses(1),
    loadIncomeProfessionals(),
    loadIncomes(1),
  ])
})
</script>

<style scoped>
/* ── Full-height card layout ── */
.finance-page {
  display: flex;
  flex-direction: column;
  min-height: calc(100vh - 160px);
}
.finance-page > .entity-card {
  flex: 1;
  display: flex;
  flex-direction: column;
}
section {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.entity-card > .page-header { flex-shrink: 0; }
.finance-tabs { flex-shrink: 0; }
section > .finance-toolbar { flex-shrink: 0; }
section > .benefits-cards { flex-shrink: 0; }
section > .empty-card { flex: 1; display: flex; align-items: center; justify-content: center; }
section > .table-wrap { flex: 1; overflow: auto; }

/* ── Benefits table frames ── */
.benefits-grid .table-wrap {
  border: 1px solid var(--border, #e5e7eb);
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
}
.benefits-grid .table-wrap h3.benefit-section-title {
  padding: 14px 16px 0;
  margin-bottom: 0;
}
.benefits-grid .table-wrap .entity-table {
  margin: 0;
}

/* ── Tabs ── */
.finance-tabs { display: flex; gap: 8px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; }
.finance-tab {
  padding: 8px 16px; font-size: 14px; font-weight: 600; color: #6b7280;
  background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer;
}
.finance-tab.active { color: #4338ca; border-bottom-color: #4338ca; }

.finance-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.finance-toolbar .filter-group { display: flex; gap: 10px; flex: 1 1 0; min-width: 0; }
.finance-toolbar .filter-group .input-sm { flex: 1 1 0; min-width: 0; }
.finance-toolbar .search-wrapper { flex: 1; min-width: 220px; }
.toolbar-actions { display: flex; gap: 8px; margin-left: auto; }
.input-sm { min-width: 180px; }
.date-field { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; }
.date-field .input { padding: 8px 10px; }

.section-copy { color: #6b7280; font-size: 14px; margin-bottom: 14px; }
.muted { color: #9ca3af; }
.link { color: #4338ca; }

.concept-cell { font-weight: 600; }
.receipt-num { color: #9ca3af; font-weight: 400; margin-left: 4px; }
.total-cell { font-weight: 700; color: #111827; }
.category-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid; }

.row-action { display: flex; align-items: center; gap: 8px; }
.action-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px;
  color: #374151; font-size: 13px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer;
}
.action-btn.danger { color: #b91c1c; border-color: #fecaca; }

.rate-cell { width: 180px; }
.counter-input { width: 120px; }

.pagination { margin-top: 12px; display: flex; justify-content: flex-end; gap: 12px; align-items: center; }
.pagination-info { color: #6b7280; font-size: 13px; }
.pagination-actions { display: flex; gap: 8px; }
.icon-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; }
.icon-btn:disabled { opacity: 0.45; }

.benefits-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
.benefit-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
.benefit-card.accent { background: #eef2ff; border-color: #c7d2fe; }
.benefit-card.accent.negative { background: #fef2f2; border-color: #fecaca; }
.benefit-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.benefit-value { font-size: 18px; font-weight: 700; color: #111827; }
.variation { font-size: 12px; font-weight: 600; margin-top: 6px; }
.variation.up { color: #059669; }
.variation.down { color: #b91c1c; }

.help-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 16px; font-weight: 700; color: #6b7280; display: flex; align-items: center; justify-content: center; line-height: 1; flex-shrink: 0; }
.help-btn:hover { background: #f3f4f6; color: #374151; }

.benefits-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
.benefit-section-title { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 8px; }

.field { display: flex; flex-direction: column; margin-bottom: 10px; }
.label { font-weight: 600; margin-bottom: 6px; color: #374151; font-size: 13px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.input { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; width: 100%; box-sizing: border-box; }
textarea.input { resize: vertical; font-family: inherit; }
.total-preview { padding: 10px 12px; border: 1px dashed #e5e7eb; border-radius: 8px; color: #4338ca; font-weight: 700; background: #fafbff; }
.actions { display: flex; gap: 12px; align-items: center; margin-top: 16px; }
.actions .primary, .actions .muted { padding: 8px 16px; font-size: 14px; border-radius: 9999px; cursor: pointer; }
.actions .primary { background: #4338ca; color: #fff; border: 1px solid #4338ca; font-weight: 600; }
.actions .muted { border: 1px solid #d1d5db; color: #374151; background: #fff; font-weight: 600; }

.cat-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
.category-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.cat-name { font-weight: 600; color: #111827; }
.cat-desc { color: #9ca3af; font-size: 13px; flex: 1; }
.cat-new { display: flex; gap: 8px; margin-top: 14px; align-items: center; }
.color-input { width: 40px; height: 40px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 2px; cursor: pointer; }

.pending-summary { display: flex; gap: 20px; align-items: center; padding: 12px 16px; background: #fef3c7; border: 1px solid #fcd34d; border-radius: 10px; margin-bottom: 16px; }
.pending-summary-count { font-weight: 700; color: #92400e; }
.pending-summary-amount { font-weight: 600; color: #b45309; }
.pending-amount { color: #dc2626; }
.status-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.status-pending { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.status-partial { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
.status-completed { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
.status-refunded { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.field-help { font-size: 12px; color: #6b7280; margin-top: 4px; }

/* ── Income tab ── */
.income-summary { display: flex; gap: 20px; align-items: center; padding: 12px 16px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; margin-bottom: 16px; }
.income-summary-item { display: flex; flex-direction: column; gap: 2px; }
.income-summary-label { font-size: 12px; color: #6b7280; }
.income-summary-value { font-size: 18px; font-weight: 700; color: #111827; }
.income-positive { color: #059669; }
.income-negative { color: #dc2626; }
.concept-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.concept-appointment { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.concept-package { background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }
.concept-credit { background: #fefce8; color: #a16207; border: 1px solid #fde68a; }
.concept-other { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
.refund-info { padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
.refund-info div { margin-bottom: 4px; }
.refund-checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; color: #374151; cursor: pointer; }
.refund-checkbox input[type="checkbox"] { width: 18px; height: 18px; accent-color: #4338ca; }
.danger-action { background: #dc2626 !important; border-color: #dc2626 !important; }

/* ── Summary / Resumen tab ── */
.summary-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
.summary-kpi { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
.summary-kpi.accent { background: #eef2ff; border-color: #c7d2fe; }
.summary-kpi-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.summary-kpi-value { font-size: 20px; font-weight: 700; color: #111827; }
.summary-kpi-sub { font-size: 13px; color: #6b7280; margin-top: 2px; }
.summary-kpi-variation { font-size: 12px; font-weight: 600; margin-top: 4px; }
.summary-kpi-variation.up { color: #059669; }
.summary-kpi-variation.down { color: #b91c1c; }

.summary-chart-section { margin-bottom: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.summary-section-title { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 12px; }

.summary-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.summary-bottom-panel { min-width: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; overflow: hidden; }

.summary-comparison { display: flex; flex-direction: column; gap: 0; }
.comparison-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
.comparison-row:last-child { border-bottom: none; }
.comparison-label { font-weight: 600; color: #374151; font-size: 14px; }
.comparison-current { font-weight: 700; color: #111827; font-size: 14px; }
.comparison-prev { color: #9ca3af; font-size: 13px; }

.report-summary { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; margin-top: 16px; padding: 14px 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; }
.report-summary-item { display: flex; flex-direction: column; gap: 2px; min-width: 120px; text-align: right; }
.report-summary-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.03em; }
.report-summary-value { font-size: 15px; font-weight: 700; color: #111827; text-align: right; }
.report-num-col { text-align: right; }

@media (max-width: 768px) {
  .grid-2 { grid-template-columns: 1fr; }
  .toolbar-actions { margin-left: 0; width: 100%; }
  .finance-toolbar .filter-group { flex-basis: 100%; flex-direction: column; }
  .finance-toolbar .filter-group .input-sm { min-width: 0; }
  .summary-bottom { grid-template-columns: 1fr; }
}
</style>
