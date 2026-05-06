import axios from 'axios'

export default async function logout(router) {
  try {
    await axios.post('/api/logout', {}, {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`
      }
    })
  } catch (e) {
    // ignore errors
  }

  localStorage.removeItem('token')
  if (router) router.push('/login')
}
