export function formatDMY(v) {
  if (!v) return ''
  try {
    const d = new Date(v)
    const dd = String(d.getDate()).padStart(2, '0')
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const yyyy = d.getFullYear()
    return `${dd}/${mm}/${yyyy}`
  } catch (e) {
    return ''
  }
}

export function formatDateOnlyDay(v, locale = 'es-ES') {
  if (!v) return '—'

  const date = new Date(v)
  if (Number.isNaN(date.getTime())) return '—'

  return date.toLocaleDateString(locale)
}

export default { formatDMY, formatDateOnlyDay }
