import { createRouter, createWebHistory } from 'vue-router'
import Landing from '../views/Landing.vue'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import ForgotPassword from '../views/ForgotPassword.vue'
import ResetPassword from '../views/ResetPassword.vue'
import Dashboard from '../views/Dashboard.vue'
import BillingRequired from '../views/BillingRequired.vue'
import PatientsIndex from '../views/patients/Index.vue'
import PatientsForm from '../views/patients/Form.vue'
// Nota: usamos el mismo formulario para creación/edición
import PatientsShow from '../views/patients/Show.vue'
import ClinicalHistory from '../views/patients/ClinicalHistory.vue'
import Profile from '../views/Profile.vue'
import Configuration from '../views/Configuration.vue'
import AgendaDay from '../views/appointments/AgendaDay.vue'
import AgendaWeek from '../views/appointments/AgendaWeek.vue'
import AppointmentsForm from '../views/appointments/Form.vue'
import AppointmentsShow from '../views/appointments/Show.vue'
import PaymentsIndex from '../views/payments/Index.vue'
import PaymentsForm from '../views/payments/Form.vue'
import PaymentsShow from '../views/payments/Show.vue'
import ProductsIndex from '../views/products/Index.vue'
import ProductsForm from '../views/products/Form.vue'
import ProductsShow from '../views/products/Show.vue'
import InvoicesIndex from '../views/invoices/Index.vue'
import InvoicesShow from '../views/invoices/Show.vue'
import InvoicesForm from '../views/invoices/Form.vue'
import NotificationsIndex from '../views/notifications/Index.vue'
import NotificationsShow from '../views/notifications/Show.vue'
import Privacy from '../views/Privacy.vue'
import Terms from '../views/Terms.vue'
import ImpersonateEntry from '../views/ImpersonateEntry.vue'
import Team from '../views/team/Team.vue'
import TeamUserForm from '../views/team/TeamUserForm.vue'
import CompanyServices from '../views/company-services/Index.vue'

const routes = [
  { path: '/', component: Landing, meta: { publicLanding: true } },
  { path: '/login', component: Login },
  { path: '/forgot-password', component: ForgotPassword },
  { path: '/reset-password', component: ResetPassword },
  { path: '/impersonate', component: ImpersonateEntry },
  { path: '/register', component: Register },
  { path: '/dashboard', component: Dashboard, meta: { auth: true } },
  { path: '/profile', component: Profile, meta: { auth: true } },
  { path: '/settings', component: Configuration, meta: { auth: true } },
  { path: '/company-services', component: CompanyServices, meta: { auth: true } },
  { path: '/payments', component: PaymentsIndex, meta: { auth: true } },
  { path: '/payments/create', component: PaymentsForm, meta: { auth: true } },
  { path: '/payments/:id', component: PaymentsShow, meta: { auth: true } },
  { path: '/payments/:id/edit', component: PaymentsForm, meta: { auth: true } },
  { path: '/products', component: ProductsIndex, meta: { auth: true } },
  { path: '/products/create', component: ProductsForm, meta: { auth: true } },
  { path: '/products/:id', component: ProductsShow, meta: { auth: true } },
  { path: '/products/:id/edit', component: ProductsForm, meta: { auth: true } },
  { path: '/invoices', component: InvoicesIndex, meta: { auth: true } },
  { path: '/invoices/create', component: InvoicesForm, meta: { auth: true } },
  { path: '/invoices/:id', component: InvoicesShow, meta: { auth: true } },
  { path: '/bonuses', redirect: '/patients', meta: { auth: true } },
  { path: '/notifications', component: NotificationsIndex, meta: { auth: true } },
  { path: '/notifications/:id', component: NotificationsShow, meta: { auth: true } },
  { path: '/patients', component: PatientsIndex, meta: { auth: true } },
  { path: '/patients/create', component: PatientsForm, meta: { auth: true } },
  { path: '/patients/:id', component: PatientsShow, meta: { auth: true } },
  { path: '/patients/:id/history', component: ClinicalHistory, meta: { auth: true } },
  { path: '/patients/:id/edit', component: PatientsForm, meta: { auth: true } },
  { path: '/billing/required', component: BillingRequired, meta: { auth: true } },
  { path: '/team', component: Team, meta: { auth: true } },
  { path: '/team/users/create', component: TeamUserForm, meta: { auth: true } },
  { path: '/team/users/:id/edit', component: TeamUserForm, meta: { auth: true } },
  { path: '/appointments', redirect: '/appointments/day', meta: { auth: true } },
  { path: '/appointments/day', component: AgendaDay, meta: { auth: true } },
  { path: '/appointments/week', component: AgendaWeek, meta: { auth: true } },
  { path: '/appointments/create', component: AppointmentsForm, meta: { auth: true } },
  { path: '/appointments/:id', component: AppointmentsShow, meta: { auth: true } },
  { path: '/appointments/:id/edit', component: AppointmentsForm, meta: { auth: true } },
  { path: '/booking/:slug', component: () => import('../views/booking/BookingPage.vue'), meta: { publicLanding: true } },
  { path: '/consent-templates', component: () => import('../views/consents/ConsentTemplatesIndex.vue'), meta: { auth: true } },
  { path: '/consent-templates/create', component: () => import('../views/consents/ConsentTemplatesForm.vue'), meta: { auth: true } },
  { path: '/consent-templates/:id/edit', component: () => import('../views/consents/ConsentTemplatesForm.vue'), meta: { auth: true } },
  { path: '/sign/:token', component: () => import('../views/consents/ConsentSignPublic.vue') },
  { path: '/settings/subscription', component: () => import('../views/settings/Subscription.vue'), meta: { auth: true } },
  { path: '/privacy', component: Privacy },
  { path: '/terms', component: Terms },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// 🔐 Protección de rutas
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')

  if (to.meta.auth && !token) {
    next('/login')
  } else {
    next()
  }
})

export default router
