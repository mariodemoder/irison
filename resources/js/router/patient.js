// Auth pages (guest)
import Login from '../views/patient/Login.vue'
import ForgotPassword from '../views/patient/ForgotPassword.vue'
import ResetPassword from '../views/patient/ResetPassword.vue'

// Layout
import PatientLayout from '../layouts/patient/PatientLayout.vue'

// Authenticated pages
import Dashboard from '../views/patient/Dashboard.vue'
import Profile from '../views/patient/Profile.vue'
import Appointments from '../views/patient/Appointments.vue'
import AppointmentDetail from '../views/patient/AppointmentDetail.vue'
import AppointmentRequest from '../views/patient/AppointmentRequest.vue'
import Bonuses from '../views/patient/Bonuses.vue'
import BonusDetail from '../views/patient/BonusDetail.vue'
import Payments from '../views/patient/Payments.vue'
import Consents from '../views/patient/Consents.vue'
import ConsentDetail from '../views/patient/ConsentDetail.vue'
import Documents from '../views/patient/Documents.vue'
import Notifications from '../views/patient/Notifications.vue'

const patientRoutes = [
  // Guest routes
  { path: '/patient/login', component: Login, meta: { guest: true } },
  { path: '/patient/forgot-password', component: ForgotPassword, meta: { guest: true } },
  { path: '/patient/reset-password', component: ResetPassword, meta: { guest: true } },

  // Authenticated routes
  {
    path: '/patient',
    component: PatientLayout,
    meta: { requiresPatientAuth: true },
    children: [
      { path: '', redirect: '/patient/dashboard' },
      { path: 'dashboard', component: Dashboard },
      { path: 'profile', component: Profile },
      { path: 'appointments', component: Appointments },
      { path: 'appointments/request', component: AppointmentRequest },
      { path: 'appointments/:id', component: AppointmentDetail },
      { path: 'bonuses', component: Bonuses },
      { path: 'bonuses/:id', component: BonusDetail },
      { path: 'payments', component: Payments },
      { path: 'consents', component: Consents },
      { path: 'consents/:id', component: ConsentDetail },
      { path: 'documents', component: Documents },
      { path: 'notifications', component: Notifications },
    ],
  },
]

export default patientRoutes
