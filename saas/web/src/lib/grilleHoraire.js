import { useEffect, useState } from 'react'
import api from './api'

/** Durée d'une période, en minutes — même convention que `App\Services\GrilleHoraire` côté serveur. */
export const DUREE_PERIODE_MINUTES = 40

/** Une colonne SQL `time` revient en « 08:10:00 » : on retombe sur « HH:MM ». */
export const toHeureMinute = (valeur) => (valeur ? String(valeur).slice(0, 5) : '')

export const toHeureAffichee = (valeur) => toHeureMinute(valeur).replace(':', 'h')

export const plageAffichee = (debut, fin) => `${toHeureAffichee(debut)}–${toHeureAffichee(fin)}`

/**
 * « 08:10 » -> 490 minutes depuis minuit ; `null` si l'heure n'en est pas une.
 * Une heure illisible prise pour minuit ferait d'une pause une plage géante.
 */
function enMinutes(heure) {
  const [h, m] = toHeureMinute(heure).split(':').map(Number)
  if (!Number.isInteger(h) || !Number.isInteger(m) || h > 23 || m > 59) return null
  return h * 60 + m
}

const enHeure = (minutes) =>
  `${String(Math.floor(minutes / 60) % 24).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`

/**
 * Pauses de la journée (récréation, pause de midi), saisies dans
 * Configuration. Une pause n'est pas une période : elle repousse la fin d'un
 * créneau sans le faire compter davantage, et le serveur applique la même
 * règle aux périodes à rattraper.
 *
 * Mémorisées au niveau du module : les calculs de formulaire sont synchrones,
 * ils ne peuvent pas attendre une requête à chaque frappe.
 */
let pauses = []

export function definirPauses(liste) {
  pauses = (liste ?? [])
    .map((pause) => [enMinutes(pause.heure_debut), enMinutes(pause.heure_fin)])
    .filter(([debut, fin]) => debut !== null && fin !== null && fin > debut)
    .sort((a, b) => a[0] - b[0])
}

/** Charge les pauses de l'établissement et les rend disponibles aux calculs ci-dessous. */
export function usePauses() {
  const [liste, setListe] = useState([])

  useEffect(() => {
    api
      .get('/periodes-cours', { params: { type: 'pause' } })
      .then(({ data }) => {
        const pausesChargees = data.data ?? []
        definirPauses(pausesChargees)
        setListe(pausesChargees)
      })
      .catch(() => definirPauses([]))
  }, [])

  return liste
}

/** Minutes d'une plage, pauses déduites. */
export function minutesHorsPause(heureDebut, heureFin) {
  const debut = enMinutes(heureDebut)
  const fin = enMinutes(heureFin)
  if (debut === null || fin === null || fin <= debut) return 0

  const pause = pauses.reduce(
    (total, [pauseDebut, pauseFin]) => total + Math.max(0, Math.min(fin, pauseFin) - Math.max(debut, pauseDebut)),
    0,
  )
  return Math.max(0, fin - debut - pause)
}

/**
 * Heure de fin à partir d'une heure de début et d'un nombre de périodes :
 * évite de saisir une fin incohérente avec le début. Les pauses rencontrées
 * en chemin sont enjambées — elles repoussent la fin sans compter pour une
 * période.
 */
export function finApresPeriodes(heureDebut, nombrePeriodes) {
  const debut = enMinutes(heureDebut)
  const nb = Number(nombrePeriodes)
  if (debut === null || !Number.isFinite(nb) || nb < 1) return ''

  let courant = debut
  let restant = Math.floor(nb) * DUREE_PERIODE_MINUTES

  // Une pause par tour au plus : la boucle ne peut pas s'emballer sur une
  // grille mal saisie.
  for (let tour = 0; tour <= pauses.length; tour++) {
    const prochaine = pauses.find(
      ([pauseDebut, pauseFin]) =>
        (pauseFin > courant && pauseDebut >= courant && pauseDebut < courant + restant) ||
        (pauseDebut <= courant && pauseFin > courant),
    )
    if (!prochaine) break
    const [pauseDebut, pauseFin] = prochaine
    restant -= Math.max(0, pauseDebut - courant)
    courant = pauseFin
  }

  return enHeure(courant + Math.max(0, restant))
}

/** Nombre de périodes d'une plage (minimum 1), pauses déduites — pour pré-remplir le formulaire en édition. */
export function nombrePeriodesEntre(heureDebut, heureFin) {
  const minutes = minutesHorsPause(heureDebut, heureFin)
  return Math.max(1, Math.ceil(minutes / DUREE_PERIODE_MINUTES))
}
