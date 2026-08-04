import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import '../css/app.css'
import api from './services/api'
import toastConfig from './services/toastConfig'
import BaseButton from './components/BaseButton.vue'
import SaveButton from './components/SaveButton.vue'
import BtnTrash from './components/BtnTrash.vue'
import EditButton from './components/EditButton.vue'
import NewButton from './components/NewButton.vue'

// Toasts
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'

const app = createApp(App)
app.component('BaseButton', BaseButton)
app.component('SaveButton', SaveButton)
app.component('BtnTrash', BtnTrash)
app.component('EditButton', EditButton)
app.component('NewButton', NewButton)
app.use(router)
app.use(Toast, toastConfig)
app.mount('#app')
