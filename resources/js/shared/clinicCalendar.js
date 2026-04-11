export function normalizeClosedDays(rawItems) {
  if (!Array.isArray(rawItems)) return []

  return Array.from(new Set(rawItems
    .map((item) => String(item || '').trim())
    .filter((item) => /^\d{4}-\d{2}-\d{2}(\.\.\d{4}-\d{2}-\d{2})?$/.test(item))))
    .sort()
}

export function isDateClosed(isoDate, closedRules) {
  const date = String(isoDate || '').trim()
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return false

  for (const rule of normalizeClosedDays(closedRules)) {
    if (!rule.includes('..')) {
      if (rule === date) return true
      continue
    }

    const [start, end] = rule.split('..')
    if (!start || !end) continue
    if (start <= date && date <= end) return true
  }

  return false
}