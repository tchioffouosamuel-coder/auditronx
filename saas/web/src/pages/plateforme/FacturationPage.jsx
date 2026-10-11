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
  const [planEdition, setPlanEdition] = useState(null);
  const [paiement, setPaiement] = useState(null);
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
    setEnCours(true);
    setErreur(null);
    try {
      await apiCentral.post(`/factures/${facture.id}/payee`, {
        payee_le: paiement.payee_le || new Date().toISOString().slice(0, 10),
        moyen_paiement: paiement.moyen_paiement,
        reference_paiement: paiement.reference_paiement || null,
      });
      setPaiement(null);
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Enregistrement du paiement impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function renouveler(abonnement) {
    setEnCours(true);
    setErreur(null);
    try {
      await apiCentral.post(`/abonnements/${abonnement.id}/renouvelle`, {});
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Renouvellement impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function enregistrerPlan(event) {
    event.preventDefault();
    setEnCours(true);
    setErreur(null);
    try {
      const { fonctionnalites, ...champs } = planEdition;
      const payload = {
        ...champs,
        fonctionnalites: (fonctionnalites ?? "")
          .split(/[\n,]/)
          .map((valeur) => valeur.trim())
          .filter(Boolean),
      };
      if (planEdition.id) {
        await apiCentral.put(`/plans/${planEdition.id}`, payload);
      } else {
        await apiCentral.post("/plans", payload);
      }
      setPlanEdition(null);
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Enregistrement de l’offre impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function supprimerPlan(plan) {
    if (!window.confirm(`Supprimer ou désactiver l’offre « ${plan.nom} » ?`)) return;
    setEnCours(true);
    setErreur(null);
    try {
      await apiCentral.delete(`/plans/${plan.id}`);
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Suppression de l’offre impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function annulerFacture(facture) {
    if (!window.confirm(`Annuler la facture ${facture.numero} ?`)) return;
    setEnCours(true);
    setErreur(null);
    try {
      await apiCentral.post(`/factures/${facture.id}/annule`, {});
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Annulation impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function telechargerFacture(facture) {
    setErreur(null);
    try {
      const { data } = await apiCentral.get(`/factures/${facture.id}/pdf`, { responseType: "blob" });
      const url = URL.createObjectURL(data);
      const lien = document.createElement("a");
      lien.href = url;
      lien.download = `facture-${facture.numero}.pdf`;
      document.body.appendChild(lien);
      lien.click();
      lien.remove();
      URL.revokeObjectURL(url);
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Téléchargement de la facture impossible.");
    }
  }

  if (!donnees) return <LoadingState />;

  return (
    <div className="space-y-6">
      {erreur && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{erreur}</div>
      )}

      <section className="card">
        <div className="flex items-center justify-between border-b border-ink-100 px-4 py-3">
          <h2 className="font-semibold text-ink-900">Offres</h2>
          {estSuperAdmin && (
            <button type="button" onClick={() => setPlanEdition({ devise: "XAF", periodicite: "mensuel", actif: true })} className="btn-secondary px-3 py-1.5 text-xs">
              Ajouter une offre
            </button>
          )}
        </div>
        <div className="grid gap-3 p-4 sm:grid-cols-3">
          {donnees.plans.map((plan) => (
            <div key={plan.id} className="rounded-lg border border-ink-100 p-3">
              <p className="font-semibold text-ink-900">{plan.nom}</p>
              <p className="text-sm text-ink-500">{money(plan.prix, plan.devise)} / {plan.periodicite}</p>
              {plan.description && <p className="mt-2 text-xs text-ink-500">{plan.description}</p>}
              <p className="mt-2 text-xs text-ink-500">
                {plan.max_personnel ?? "∞"} agents · {plan.max_bornes ?? "∞"} bornes
              </p>
              {plan.fonctionnalites?.length > 0 && (
                <p className="mt-1 text-xs text-ink-500">{plan.fonctionnalites.join(" · ")}</p>
              )}
              <p className="mt-1 text-xs text-ink-400">
                {plan.abonnements_count} abonnement(s){plan.actif ? "" : " · inactif"}
              </p>
              {estSuperAdmin && (
                <div className="mt-3 flex gap-3 text-xs">
                  <button type="button" onClick={() => setPlanEdition({ ...plan, fonctionnalites: (plan.fonctionnalites ?? []).join(", ") })} className="text-brand-800 hover:underline">
                    Modifier
                  </button>
                  <button type="button" disabled={enCours} onClick={() => supprimerPlan(plan)} className="text-red-700 hover:underline disabled:opacity-50">
                    {plan.actif ? "Désactiver" : "Supprimer"}
                  </button>
                </div>
              )}
            </div>
          ))}
          {donnees.plans.length === 0 && <p className="text-sm text-ink-500">Aucune offre configurée.</p>}
        </div>
      </section>

      <section className="card">
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
                        className="btn-secondary px-2 py-1 text-xs"
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

      <section className="card">
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
                <th className="px-4 py-2">Actions</th>
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
                  <td className="px-4 py-2">
                    {estSuperAdmin && (
                      <>
                      {facture.statut !== "payee" && (
                        <>
                          {facture.statut === "emise" && (
                            <>
                              <button
                                type="button"
                                onClick={() => setPaiement({ ...facture, moyen_paiement: facture.moyen_paiement ?? "mobile_money", payee_le: new Date().toISOString().slice(0, 10) })}
                                className="rounded border border-brand-200 px-2 py-1 text-xs text-brand-800 hover:bg-brand-50"
                              >
                                Enregistrer paiement
                              </button>
                              <button
                                type="button"
                                disabled={enCours}
                                onClick={() => annulerFacture(facture)}
                                className="rounded border border-red-200 px-2 py-1 text-xs text-red-700 hover:bg-red-50 disabled:opacity-50"
                              >
                                Annuler
                              </button>
                            </>
                          )}
                        </>
                      )}
                      </>
                    )}
                    <button type="button" onClick={() => telechargerFacture(facture)} className="text-xs text-brand-800 hover:underline">
                      Télécharger PDF
                    </button>
                  </td>
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
                className="field w-full"
              />
            </label>

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Échéance (vide = sans fin)</span>
              <input
                type="date"
                value={souscription.fin_le ?? ""}
                onChange={(e) => setSouscription({ ...souscription, fin_le: e.target.value })}
                className="field w-full"
              />
            </label>

            <button
              type="submit"
              disabled={enCours}
              className="btn w-full bg-ink-900 text-white hover:bg-ink-800"
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
                className="field w-full"
              />
            </label>

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Échéance</span>
              <input
                type="date"
                value={emission.echeance_le ?? ""}
                onChange={(e) => setEmission({ ...emission, echeance_le: e.target.value })}
                className="field w-full"
              />
            </label>

            <button
              type="submit"
              disabled={enCours}
              className="btn w-full bg-ink-900 text-white hover:bg-ink-800"
            >
              Émettre
            </button>
          </form>
        </Modal>
      )}

      {planEdition && (
        <Modal title={planEdition.id ? "Modifier une offre" : "Créer une offre"} onClose={() => setPlanEdition(null)}>
          <form onSubmit={enregistrerPlan} className="space-y-3">
            <ChampTexte label="Code" value={planEdition.code} required onChange={(value) => setPlanEdition({ ...planEdition, code: value })} />
            <ChampTexte label="Nom" value={planEdition.nom} required onChange={(value) => setPlanEdition({ ...planEdition, nom: value })} />
            <ChampTexte label="Description" value={planEdition.description} onChange={(value) => setPlanEdition({ ...planEdition, description: value })} />
            <div className="grid grid-cols-2 gap-3">
              <ChampTexte label="Prix" type="number" min="0" value={planEdition.prix ?? 0} required onChange={(value) => setPlanEdition({ ...planEdition, prix: value })} />
              <ChampTexte label="Devise" value={planEdition.devise ?? "XAF"} required onChange={(value) => setPlanEdition({ ...planEdition, devise: value })} />
            </div>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Périodicité</span>
              <select value={planEdition.periodicite ?? "mensuel"} onChange={(e) => setPlanEdition({ ...planEdition, periodicite: e.target.value })} className="field w-full">
                <option value="mensuel">Mensuelle</option>
                <option value="trimestriel">Trimestrielle</option>
                <option value="annuel">Annuelle</option>
              </select>
            </label>
            <div className="grid grid-cols-2 gap-3">
              <ChampTexte label="Quota personnel (vide = illimité)" type="number" min="1" value={planEdition.max_personnel ?? ""} onChange={(value) => setPlanEdition({ ...planEdition, max_personnel: value || null })} />
              <ChampTexte label="Quota bornes (vide = illimité)" type="number" min="1" value={planEdition.max_bornes ?? ""} onChange={(value) => setPlanEdition({ ...planEdition, max_bornes: value || null })} />
            </div>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Fonctionnalités (séparées par des virgules)</span>
              <textarea rows={3} value={planEdition.fonctionnalites ?? ""} onChange={(e) => setPlanEdition({ ...planEdition, fonctionnalites: e.target.value })} className="field w-full" />
            </label>
            <label className="flex items-center gap-2 text-sm text-ink-700">
              <input type="checkbox" checked={Boolean(planEdition.actif)} onChange={(e) => setPlanEdition({ ...planEdition, actif: e.target.checked })} />
              Offre active et disponible pour les souscriptions
            </label>
            <button type="submit" disabled={enCours} className="btn w-full bg-ink-900 text-white">
              {enCours ? "Enregistrement…" : "Enregistrer l’offre"}
            </button>
          </form>
        </Modal>
      )}

      {paiement && (
        <Modal title={`Enregistrer le règlement — ${paiement.numero}`} onClose={() => setPaiement(null)}>
          <form onSubmit={(event) => { event.preventDefault(); marquerPayee(paiement); }} className="space-y-3">
            <p className="text-sm text-ink-600">{money(paiement.montant, paiement.devise)} · {paiement.etablissement?.nom}</p>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Date du règlement</span>
              <input type="date" required value={paiement.payee_le} onChange={(e) => setPaiement({ ...paiement, payee_le: e.target.value })} className="field w-full" />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">Moyen de paiement</span>
              <select required value={paiement.moyen_paiement} onChange={(e) => setPaiement({ ...paiement, moyen_paiement: e.target.value })} className="field w-full">
                <option value="mobile_money">Mobile Money</option>
                <option value="virement">Virement bancaire</option>
                <option value="especes">Espèces</option>
                <option value="cheque">Chèque</option>
              </select>
            </label>
            <ChampTexte label="Référence de transaction (facultatif)" value={paiement.reference_paiement} onChange={(value) => setPaiement({ ...paiement, reference_paiement: value })} />
            <button type="submit" disabled={enCours} className="btn w-full bg-ink-900 text-white">
              {enCours ? "Enregistrement…" : "Confirmer le règlement"}
            </button>
          </form>
        </Modal>
      )}
    </div>
  );
}

function ChampTexte({ label, value, onChange, ...props }) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-medium text-ink-700">{label}</span>
      <input value={value ?? ""} onChange={(event) => onChange(event.target.value)} className="field w-full" {...props} />
    </label>
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
        className="field w-full"
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
