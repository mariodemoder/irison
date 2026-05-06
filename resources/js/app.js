import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import '../css/app.css'
import api from './services/api'

// Toasts
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'

const app = createApp(App)
app.use(router)
app.use(Toast, { position: 'top-right', timeout: 4000 })
app.mount('#app')

// Interceptor global: redirigir a billing requerido cuando backend lo indique
api.interceptors.response.use(
    response => {
        const code = response?.data?.code
        if (code === 'SUBSCRIPTION_REQUIRED') {
            router.push('/billing/required')
        }
        return response
    },
    error => {
        const code = error?.response?.data?.code
        if (code === 'SUBSCRIPTION_REQUIRED') {
            // no cerramos sesión; redirigimos a la pantalla de billing
            router.push('/billing/required')
            // devolver el error para seguir manejándolo si hace falta
            return Promise.reject(error)
        }
        return Promise.reject(error)
    }
)
