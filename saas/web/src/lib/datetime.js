// Fuseau horaire de l'établissement (GMT+1, sans heure d'été), appliqué quel
// que soit le fuseau du navigateur qui consulte le backoffice.
export const TIME_ZONE = 'Africa/Lagos'

const isoDateFormat = new Intl.DateTimeFormat('en-CA', {
  timeZone: TIME_ZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})

/** Date du jour `YYYY-MM-DD` dans le fuseau de l'établissement. */
export function todayIso() {
  return isoDateFormat.format(new Date())
}

/** Premier jour du mois courant `YYYY-MM-DD` dans le fuseau de l'établissement. */
export function startOfMonthIso() {
  return `${todayIso().slice(0, 7)}-01`
}

/** Dernier jour du mois courant `YYYY-MM-DD` dans le fuseau de l'établissement. */
export function endOfMonthIso() {
  const [year, month] = todayIso().split('-').map(Number)
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate()
  return `${todayIso().slice(0, 7)}-${String(lastDay).padStart(2, '0')}`
}

export function formatDateTime(value, options = { dateStyle: 'short', timeStyle: 'short' }) {
  if (!value) return '—'
  return new Date(value).toLocaleString('fr-FR', { ...options, timeZone: TIME_ZONE })
}

export function formatTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleTimeString('fr-FR', { timeZone: TIME_ZONE })
}
