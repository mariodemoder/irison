const toastConfig = {
  position: 'top-right',
  timeout: 2600,
  closeOnClick: true,
  pauseOnHover: true,
  pauseOnFocusLoss: true,
  draggable: true,
  draggablePercent: 0.55,
  hideProgressBar: false,
  newestOnTop: true,
  maxToasts: 4,
  toastClassName: 'irison-toast',
  bodyClassName: 'irison-toast__body',
  closeButtonClassName: 'irison-toast__close',
  progressClassName: 'irison-toast__progress',
  icon: false,
  toastDefaults: {
    success: {
      timeout: 2200,
    },
    info: {
      timeout: 2600,
    },
    warning: {
      timeout: 3200,
    },
    error: {
      timeout: 3600,
    },
  },
}

export default toastConfig
