import { useEffect, useState } from "react";
import LoadingState from "../../components/LoadingState";
import apiCentral from "../../lib/apiCentral";

function Tuile({ libelle, valeur, detail }) {
  return (
    <div className="card p-4">
      <p className="text-xs uppercase tracking-wide text-ink-500">{libelle}</p>
      <p className="mt-1 text-2xl font-bold text-ink-900">{valeur}</p>
      {detail && <p className="text-xs text-ink-500">{detail}</p>}
    </div>
  );
}

export default function TableauDeBordPage() {
  const [donnees, setDonnees] = useState(null);
  const [erreur, setErreur] = useState(null);

  useEffect(() => {
    apiCentral
      .get("/tableau-de-bord")
      .then(({ data }) => setDonnees(data))
      .catch(() => setErreur("Chargement impossible."));
  }, []);

  if (erreur) return <p className="text-sm text-red-700">{erreur}</p>;
  if (!donnees) return <LoadingState />;

  return (
    <div className="space-y-6">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Tuile
          libelle="Établissements"
          valeur={donnees.etablissements.total}
          detail={`${donnees.etablissements.par_statut?.actif ?? 0} actifs`}
        />
        <Tuile
          libelle="Abonnements actifs"
          valeur={donnees.abonnements.actifs}
          detail={`${donnees.abonnements.echeance_30j} à échéance sous 30 j`}
        />
        <Tuile libelle="Factures impayées" valeur={donnees.facturation.impayees} />
        <Tuile
          libelle="Encaissé ce mois"
          valeur={new Intl.NumberFormat("fr-FR").format(donnees.facturation.encaisse_mois)}
          detail="XAF"
        />
      </div>

      <section className="card">
        <h2 className="border-b border-ink-100 px-4 py-3 font-semibold text-ink-900">
          Usage par établissement
        </h2>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-ink-50 text-left text-xs uppercase text-ink-500">
              <tr>
                <th className="px-4 py-2">Établissement</th>
                <th className="px-4 py-2">Statut</th>
                <th className="px-4 py-2">Personnel</th>
                <th className="px-4 py-2">Pointages du jour</th>
                <th className="px-4 py-2">Dernier pointage</th>
              </tr>
            </thead>
            <tbody>
              {donnees.usage.map((ligne) => (
                <tr key={ligne.code} className="border-t border-ink-100">
                  <td className="px-4 py-2">
                    <span className="font-medium text-ink-800">{ligne.nom}</span>
                    <span className="ml-2 text-xs text-ink-400">{ligne.code}</span>
                  </td>
                  <td className="px-4 py-2">{ligne.statut}</td>
                  {/* Une base injoignable est signalée comme telle : afficher 0
                      laisserait croire à un établissement sans activité. */}
                  {ligne.injoignable ? (
                    <td colSpan={3} className="px-4 py-2 text-amber-700">
                      Base injoignable — à vérifier
                    </td>
                  ) : (
                    <>
                      <td className="px-4 py-2">{ligne.personnel}</td>
                      <td className="px-4 py-2">{ligne.presences_aujourdhui}</td>
                      <td className="px-4 py-2 text-ink-500">{ligne.derniere_presence ?? "—"}</td>
                    </>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
