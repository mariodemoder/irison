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
