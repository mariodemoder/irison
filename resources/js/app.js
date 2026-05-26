import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import '../css/app.css'
import api from './services/api'
import toastConfig from './services/toastConfig'

// Toasts
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'

const app = createApp(App)
app.use(router)
app.use(Toast, toastConfig)
app.mount('#app')
