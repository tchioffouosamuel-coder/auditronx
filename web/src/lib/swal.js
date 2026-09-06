import Swal from 'sweetalert2'

// Instance partagée, stylée aux couleurs de la marque (cf. index.css) plutôt
// que le thème par défaut de SweetAlert2 — remplace les window.confirm/alert
// natifs (moches et non personnalisables) dans tout le backoffice.
const swal = Swal.mixin({
  confirmButtonColor: '#0f6e49', // --color-brand-700
  cancelButtonColor: '#94a3b8',
  buttonsStyling: true,
})

/** Remplace `window.confirm(message)` : renvoie true/false. */
export async function confirmAction(message, { title = 'Confirmer', confirmText = 'Confirmer' } = {}) {
  const result = await swal.fire({
    title,
    text: message,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: 'Annuler',
    reverseButtons: true,
  })
  return result.isConfirmed
}

/** Remplace `window.alert(message)` : simple message informatif/erreur. */
export function notify(message, { title = '', icon = 'info' } = {}) {
  return swal.fire({ title, text: message, icon, confirmButtonText: 'OK' })
}

export default swal
