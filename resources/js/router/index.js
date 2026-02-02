import { createRouter, createWebHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Register from '../views/Register.vue'
import Dashboard from '../views/Dashboard.vue'
import BillingRequired from '../views/BillingRequired.vue'

const routes = [
  { path: '/login', component: Login },
  { path: '/register', component: Register },
  { path: '/dashboard', component: Dashboard, meta: { auth: true } },
  { path: '/billing/required', component: BillingRequired, meta: { auth: true } },
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
