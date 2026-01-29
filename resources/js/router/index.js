import { createRouter, createWebHistory } from 'vue-router'
import Login from '../views/Login.vue'
import Dashboard from '../views/Dashboard.vue'

const routes = [
  { path: '/login', component: Login },
  { path: '/dashboard', component: Dashboard, meta: { auth: true } },
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
