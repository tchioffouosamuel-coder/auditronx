import { useEffect, useState } from "react";
import LoadingState from "../../components/LoadingState";
import Modal from "../../components/Modal";
import { usePlatformAuth } from "../../context/PlatformAuthContext";
import apiCentral from "../../lib/apiCentral";

const money = (montant, devise = "XAF") =>
  `${new Intl.NumberFormat("fr-FR").format(montant ?? 0)} ${devise}`;

export default function FacturationPage() {
  const { estSuperAdmin } = usePlatformAuth();
  const [donnees, setDonnees] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [souscription, setSouscription] = useState(null);
  const [emission, setEmission] = useState(null);
  const [enCours, setEnCours] = useState(false);

  function recharger() {
    return Promise.all([
      apiCentral.get("/plans"),
      apiCentral.get("/abonnements"),
      apiCentral.get("/factures"),
      apiCentral.get("/etablissements"),
    ])
      .then(([plans, abonnements, factures, etablissements]) =>
        setDonnees({
          plans: plans.data.data,
          abonnements: abonnements.data.data,
          factures: factures.data.data,
          etablissements: etablissements.data.data,
        }),
      )
      .catch(() => setErreur("Chargement impossible."));
  }

  useEffect(() => {
    recharger();
  }, []);

  async function souscrire(e) {
    e.preventDefault();
    setEnCours(true);

    try {
      await apiCentral.post("/abonnements", {
        ...souscription,
        debut_le: souscription.debut_le || new Date().toISOString().slice(0, 10),
      });
      setSouscription(null);
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Souscription impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function emettre(e) {
    e.preventDefault();
    setEnCours(true);

    try {
      await apiCentral.post("/factures", emission);
      setEmission(null);
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Émission impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function marquerPayee(facture) {
    await apiCentral.post(`/factures/${facture.id}/payee`, {});
    await recharger();
  }

  async function renouveler(abonnement) {
    await apiCentral.post(`/abonnements/${abonnement.id}/renouvelle`, {});
    await recharger();
  }

  if (!donnees) return <LoadingState />;

  return (
    <div className="space-y-6">
      {erreur && (
        <div className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{erreur}</div>
      )}

      <section className="rounded-xl border border-ink-100 bg-white">
        <h2 className="border-b border-ink-100 px-4 py-3 font-semibold text-ink-900">Offres</h2>
        <div className="grid gap-3 p-4 sm:grid-cols-3">
          {donnees.plans.map((plan) => (
            <div key={plan.id} className="rounded-lg border border-ink-100 p-3">
              <p className="font-semibold text-ink-900">{plan.nom}</p>
              <p className="text-sm text-ink-500">{money(plan.prix, plan.devise)} / {plan.periodicite}</p>
              <p className="mt-2 text-xs text-ink-500">
                {plan.max_personnel ?? "∞"} agents · {plan.max_bornes ?? "∞"} bornes
              </p>
              <p className="mt-1 text-xs text-ink-400">
                {plan.abonnements_count} abonnement(s){plan.actif ? "" : " · inactif"}
              </p>
            </div>
          ))}
        </div>
      </section>

      <section className="rounded-xl border border-ink-100 bg-white">
        <div className="flex items-center justify-between border-b border-ink-100 px-4 py-3">
          <h2 className="font-semibold text-ink-900">Abonnements</h2>
          {estSuperAdmin && (
            <button
              type="button"
              onClick={() => setSouscription({})}
              className="rounded-lg bg-ink-900 px-3 py-1.5 text-xs font-semibold text-white"
            >
              Souscrire
            </button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-ink-50 text-left text-xs uppercase text-ink-500">
              <tr>
                <th className="px-4 py-2">Établissement</th>
                <th className="px-4 py-2">Plan</th>
                <th className="px-4 py-2">Début</th>
                <th className="px-4 py-2">Échéance</th>
                <th className="px-4 py-2">Statut</th>
                {estSuperAdmin && <th className="px-4 py-2" />}
              </tr>
            </thead>
            <tbody>
              {donnees.abonnements.map((abonnement) => (
                <tr key={abonnement.id} className="border-t border-ink-100">
                  <td className="px-4 py-2">{abonnement.etablissement?.nom}</td>
                  <td className="px-4 py-2">{abonnement.plan?.nom}</td>
                  <td className="px-4 py-2">{abonnement.debut_le?.slice(0, 10)}</td>
                  <td className="px-4 py-2">{abonnement.fin_le?.slice(0, 10) ?? "—"}</td>
                  <td className="px-4 py-2">{abonnement.statut}</td>
                  {estSuperAdmin && (
                    <td className="px-4 py-2">
                      <button
                        type="button"
                        onClick={() => renouveler(abonnement)}
                        className="rounded border border-ink-200 px-2 py-1 text-xs hover:bg-ink-50"
                      >
                        Renouveler
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <section className="rounded-xl border border-ink-100 bg-white">
        <div className="flex items-center justify-between border-b border-ink-100 px-4 py-3">
          <h2 className="font-semibold text-ink-900">Factures</h2>
          {estSuperAdmin && (
            <button
              type="button"
              onClick={() => setEmission({})}
              className="rounded-lg bg-ink-900 px-3 py-1.5 text-xs font-semibold text-white"
            >
              Émettre
            </button>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-ink-50 text-left text-xs uppercase text-ink-500">
              <tr>
                <th className="px-4 py-2">Numéro</th>
                <th className="px-4 py-2">Établissement</th>
                <th className="px-4 py-2">Montant</th>
                <th className="px-4 py-2">Échéance</th>
                <th className="px-4 py-2">Statut</th>
                {estSuperAdmin && <th className="px-4 py-2" />}
              </tr>
            </thead>
            <tbody>
              {donnees.factures.map((facture) => (
                <tr key={facture.id} className="border-t border-ink-100">
                  <td className="px-4 py-2 font-mono text-xs">{facture.numero}</td>
                  <td className="px-4 py-2">{facture.etablissement?.nom}</td>
                  <td className="px-4 py-2">{money(facture.montant, facture.devise)}</td>
                  <td className="px-4 py-2">{facture.echeance_le?.slice(0, 10) ?? "—"}</td>
                  <td className="px-4 py-2">{facture.statut}</td>
                  {estSuperAdmin && (
                    <td className="px-4 py-2">
                      {facture.statut !== "payee" && (
                        <button
                          type="button"
                          onClick={() => marquerPayee(facture)}
                          className="rounded border border-brand-200 px-2 py-1 text-xs text-brand-800 hover:bg-brand-50"
                        >
                          Encaissée
                        </button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      {souscription && (
        <Modal title="Souscrire un abonnement" onClose={() => setSouscription(null)}>
          <form onSubmit={souscrire} className="space-y-3">
            <p className="text-xs text-ink-500">
              Souscrire clôt l’abonnement actif précédent de cet établissement : il n’y a jamais
              deux abonnements courants.
            </p>

            <SelectChamp
              label="Établissement"
              valeur={souscription.etablissement_id}
              options={donnees.etablissements.map((e) => ({ value: e.id, label: `${e.nom} (${e.code})` }))}
              onChange={(v) => setSouscription({ ...souscription, etablissement_id: v })}
            />

            <SelectChamp
              label="Plan"
              valeur={souscription.plan_id}
              options={donnees.plans.map((p) => ({ value: p.id, label: p.nom }))}
              onChange={(v) => setSouscription({ ...souscription, plan_id: v })}
            />

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Début</span>
              <input
                type="date"
                value={souscription.debut_le ?? ""}
                onChange={(e) => setSouscription({ ...souscription, debut_le: e.target.value })}
                className="w-full rounded-lg border border-ink-100 px-3 py-2"
              />
            </label>

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Échéance (vide = sans fin)</span>
              <input
                type="date"
                value={souscription.fin_le ?? ""}
                onChange={(e) => setSouscription({ ...souscription, fin_le: e.target.value })}
                className="w-full rounded-lg border border-ink-100 px-3 py-2"
              />
            </label>

            <button
              type="submit"
              disabled={enCours}
              className="w-full rounded-lg bg-ink-900 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              Souscrire
            </button>
          </form>
        </Modal>
      )}

      {emission && (
        <Modal title="Émettre une facture" onClose={() => setEmission(null)}>
          <form onSubmit={emettre} className="space-y-3">
            <SelectChamp
              label="Établissement"
              valeur={emission.etablissement_id}
              options={donnees.etablissements.map((e) => ({ value: e.id, label: `${e.nom} (${e.code})` }))}
              onChange={(v) => setEmission({ ...emission, etablissement_id: v })}
            />

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Montant</span>
              <input
                type="number"
                min="0"
                required
                value={emission.montant ?? ""}
                onChange={(e) => setEmission({ ...emission, montant: e.target.value })}
                className="w-full rounded-lg border border-ink-100 px-3 py-2"
              />
            </label>

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Échéance</span>
              <input
                type="date"
                value={emission.echeance_le ?? ""}
                onChange={(e) => setEmission({ ...emission, echeance_le: e.target.value })}
                className="w-full rounded-lg border border-ink-100 px-3 py-2"
              />
            </label>

            <button
              type="submit"
              disabled={enCours}
              className="w-full rounded-lg bg-ink-900 py-2 text-sm font-semibold text-white disabled:opacity-50"
            >
              Émettre
            </button>
          </form>
        </Modal>
      )}
    </div>
  );
}

function SelectChamp({ label, valeur, options, onChange }) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-medium text-ink-700">{label}</span>
      <select
        required
        value={valeur ?? ""}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-lg border border-ink-100 px-3 py-2"
      >
        <option value="">—</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}
