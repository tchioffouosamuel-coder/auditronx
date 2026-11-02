import api from './api'

/** Garde-fou : plafonne le nombre d'allers-retours si l'API pagine à l'infini. */
const MAX_PAGES = 100

/**
 * Récupère l'intégralité d'une ressource en suivant la pagination Laravel.
 *
 * La recherche, le tri et la pagination de `DataTable` travaillent sur le
 * tableau déjà en mémoire : ne lire que la première page revenait à chercher
 * dans les 25 (ou 50) premières lignes seulement — un enseignant classé après
 * la lettre B ressortait en « 0 résultat » alors qu'il existe bien en base.
 * Les listes déroulantes souffraient du même tronquage. On agrège donc toutes
 * les pages avant d'afficher.
 */
export default async function fetchAllPages(url) {
  const all = []
  let page = 1
  let lastPage = 1

  do {
    const { data } = await api.get(url, { params: { page } })

    if (Array.isArray(data)) {
      all.push(...data)
      break
    }

    all.push(...(data.data ?? []))
    lastPage = Number(data.last_page ?? 1)
    page += 1
  } while (page <= lastPage && page <= MAX_PAGES)

  return all
}
