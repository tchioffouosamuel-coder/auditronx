/**
 * Établissement courant du portail unique.
 *
 * Une seule URL pour tous les abonnés : c'est ce code, choisi à la connexion
 * et mémorisé ici, qui désigne l'établissement dans l'en-tête `X-Tenant` de
 * chaque requête. Il est conservé après déconnexion — l'utilisateur travaille
 * presque toujours dans le même lycée, et le lui redemander à chaque fois
 * serait une friction inutile.
 */

const CLE_CODE = 'auditron_etablissement'
const CLE_BRANDING = 'auditron_etablissement_branding'

export function codeEtablissement() {
  return localStorage.getItem(CLE_CODE) || ''
}

export function definirEtablissement(code, branding = null) {
  const normalise = normaliserCode(code)

  if (!normalise) return ''

  localStorage.setItem(CLE_CODE, normalise)

  if (branding) {
    localStorage.setItem(CLE_BRANDING, JSON.stringify(branding))
  }

  return normalise
}

/**
 * Branding mis en cache : il permet d'afficher l'écran de connexion aux
 * couleurs de l'établissement sans attendre l'aller-retour réseau.
 */
export function brandingEnCache() {
  const brut = localStorage.getItem(CLE_BRANDING)

  if (!brut) return null

  try {
    return JSON.parse(brut)
  } catch {
    localStorage.removeItem(CLE_BRANDING)
    return null
  }
}

/**
 * Changer d'établissement invalide forcément la session : les jetons vivent
 * dans la base de chaque établissement et ne valent rien ailleurs.
 */
export function oublierEtablissement() {
  localStorage.removeItem(CLE_CODE)
  localStorage.removeItem(CLE_BRANDING)
  localStorage.removeItem('auditron_token')
  localStorage.removeItem('auditron_user')
}

export function normaliserCode(code) {
  return String(code || '')
    .toUpperCase()
    .replace(/[^A-Z0-9_-]/g, '')
}

export const CLES_ETABLISSEMENT = { code: CLE_CODE, branding: CLE_BRANDING }
