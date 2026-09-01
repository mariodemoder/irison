import axios from 'axios'

const patientApi = axios.create({
  baseURL: `${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/api/patient`,
  headers: { Accept: 'application/json' },
})

// Request interceptor: adds patient token from localStorage
patientApi.interceptors.request.use(config => {
  const token = localStorage.getItem('patient_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor: handles errors
patientApi.interceptors.response.use(
  response => response,
  error => {
    const status = error?.response?.status

    if (status === 401) {
      localStorage.removeItem('patient_token')
      localStorage.removeItem('patient_data')
      window.location.href = '/patient/login'
    }

    return Promise.reject(error)
  }
)

export default patientApi
