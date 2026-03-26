function canGoBackInHistory() {
  return typeof window !== 'undefined' && window.history.length > 1
}

export function goBackWithStack(router, fallbackPath) {
  if (canGoBackInHistory()) {
    router.back()
    return
  }

  router.push(fallbackPath)
}

export function goBackWithPriority(router, { priorityPath = '', fallbackPath = '/' } = {}) {
  if (priorityPath) {
    router.push(priorityPath)
    return
  }

  goBackWithStack(router, fallbackPath)
}

export default {
  goBackWithStack,
  goBackWithPriority,
}
