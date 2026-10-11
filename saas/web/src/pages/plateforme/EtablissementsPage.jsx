import { useEffect, useState } from "react";
import LoadingState from "../../components/LoadingState";
import Modal from "../../components/Modal";
import { usePlatformAuth } from "../../context/PlatformAuthContext";
import apiCentral from "../../lib/apiCentral";

const STATUTS = ["en_attente", "actif", "suspendu", "archive"];

const CHAMPS_CREATION = [
  { key: "code", label: "Code (en-tête X-Tenant, suffixe de base)", requis: true },
  { key: "nom", label: "Nom complet", requis: true },
  { key: "nom_court", label: "Nom court (app mobile)" },
  { key: "ville", label: "Ville" },
  { key: "couleur_primaire", label: "Couleur primaire (#0F766E)" },
  { key: "contact_nom", label: "Contact — nom" },
  { key: "contact_email", label: "Contact — email", type: "email" },
  { key: "contact_tel", label: "Contact — téléphone" },
  { key: "direction_email", label: "E-mail du compte de direction à créer", type: "email" },
];

const CHAMPS_EDITION = [
  { key: "nom", label: "Nom complet", requis: true },
  { key: "nom_court", label: "Nom court" },
  { key: "ville", label: "Ville" },
  { key: "pays", label: "Pays" },
  { key: "fuseau", label: "Fuseau horaire" },
  { key: "contact_nom", label: "Contact — nom" },
  { key: "contact_email", label: "Contact — e-mail", type: "email" },
  { key: "contact_tel", label: "Contact — téléphone" },
  { key: "couleur_primaire", label: "Couleur primaire" },
  { key: "couleur_secondaire", label: "Couleur secondaire" },
  { key: "db_username", label: "Utilisateur de base (facultatif)" },
  { key: "db_password", label: "Nouveau mot de passe base (laisser vide pour ne pas changer)", type: "password" },
];

export default function EtablissementsPage() {
  const { estSuperAdmin } = usePlatformAuth();
  const [etablissements, setEtablissements] = useState(null);
  const [erreur, setErreur] = useState(null);
  const [creation, setCreation] = useState(null);
  const [identifiants, setIdentifiants] = useState(null);
  const [enCours, setEnCours] = useState(false);
  const [suppression, setSuppression] = useState(null);
  const [confirmation, setConfirmation] = useState("");
  const [details, setDetails] = useState(null);
  const [editionDetails, setEditionDetails] = useState(false);

  function recharger() {
    return apiCentral
      .get("/etablissements")
      .then(({ data }) => setEtablissements(data.data))
      .catch(() => setErreur("Chargement impossible."));
  }

  useEffect(() => {
    recharger();
  }, []);

  async function creer(e) {
    e.preventDefault();
    setErreur(null);
    setEnCours(true);

    try {
      const { data } = await apiCentral.post("/etablissements", creation);
      setCreation(null);
      // Mot de passe affiché une seule fois : il n'est stocké que haché dans
      // la base du client, personne ne pourra le relire ensuite.
      setIdentifiants({ ...data.identifiants_direction, code: data.data.code });
      await recharger();
    } catch (e) {
      setErreur(
        e.response?.data?.message ?? "Création impossible : vérifiez le code et le nom.",
      );
    } finally {
      setEnCours(false);
    }
  }

  async function changerStatut(etablissement, statut) {
    setErreur(null);
    try {
      await apiCentral.post(`/etablissements/${etablissement.id}/statut`, { statut });
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Modification du statut impossible.");
    }
  }

  async function ouvrirDetails(etablissement) {
    setErreur(null);
    setEnCours(true);
    try {
      const { data } = await apiCentral.get(`/etablissements/${etablissement.id}`);
      setDetails(data);
      setEditionDetails(false);
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Chargement de la fiche impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function enregistrerDetails(e) {
    e.preventDefault();
    setEnCours(true);
    setErreur(null);
    try {
      const { db_password, ...champs } = details.data;
      await apiCentral.put(`/etablissements/${details.data.id}`, {
        ...champs,
        ...(db_password ? { db_password } : {}),
      });
      const { data } = await apiCentral.get(`/etablissements/${details.data.id}`);
      setDetails(data);
      setEditionDetails(false);
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Modification de la fiche impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function provisionner(etablissement) {
    setEnCours(true);
    try {
      const { data } = await apiCentral.post(`/etablissements/${etablissement.id}/provision`, {});
      setIdentifiants({ ...data.identifiants_direction, code: data.data.code });
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Provisioning impossible.");
    } finally {
      setEnCours(false);
    }
  }

  async function migrer(etablissement) {
    setEnCours(true);
    try {
      await apiCentral.post(`/etablissements/${etablissement.id}/migrate`, {});
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Migrations impossibles.");
    } finally {
      setEnCours(false);
    }
  }

  async function supprimer(e) {
    e.preventDefault();
    setEnCours(true);

    try {
      await apiCentral.delete(`/etablissements/${suppression.id}`, {
        data: { confirmation },
      });
      setSuppression(null);
      setConfirmation("");
      await recharger();
    } catch (e) {
      setErreur(e.response?.data?.message ?? "Suppression refusée.");
    } finally {
      setEnCours(false);
    }
  }

  if (!etablissements) return <LoadingState />;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="page-title">
          Établissements abonnés ({etablissements.length})
        </h1>

        {estSuperAdmin && (
          <button
            type="button"
            onClick={() => setCreation({})}
            className="btn bg-ink-900 text-white hover:bg-ink-800"
          >
            Ouvrir un établissement
          </button>
        )}
      </div>

      {erreur && (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{erreur}</div>
      )}

      <div className="overflow-x-auto card">
        <table className="w-full text-sm">
          <thead className="bg-ink-50 text-left text-xs uppercase text-ink-500">
            <tr>
              <th className="px-4 py-2">Établissement</th>
              <th className="px-4 py-2">Code</th>
              <th className="px-4 py-2">Statut</th>
              <th className="px-4 py-2">Plan</th>
              <th className="px-4 py-2">Échéance</th>
              <th className="px-4 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {etablissements.map((etablissement) => (
              <tr key={etablissement.id} className="border-t border-ink-100">
                <td className="px-4 py-2">
                  <span className="font-medium text-ink-800">{etablissement.nom}</span>
                  {etablissement.ville && (
                    <span className="ml-2 text-xs text-ink-400">{etablissement.ville}</span>
                  )}
                </td>
                <td className="px-4 py-2 font-mono text-xs">{etablissement.code}</td>
                <td className="px-4 py-2">
                  {estSuperAdmin ? (
                    <select
                      value={etablissement.statut}
                      onChange={(e) => changerStatut(etablissement, e.target.value)}
                      className="rounded border border-ink-100 px-2 py-1 text-xs"
                    >
                      {STATUTS.map((statut) => (
                        <option key={statut} value={statut}>
                          {statut}
                        </option>
                      ))}
                    </select>
                  ) : (
                    etablissement.statut
                  )}
                </td>
                <td className="px-4 py-2">{etablissement.abonnement_actif?.plan?.nom ?? "—"}</td>
                <td className="px-4 py-2 text-ink-500">
                  {etablissement.abonnement_actif?.fin_le ?? "sans échéance"}
                </td>
                <td className="px-4 py-2">
                  <div className="flex flex-wrap gap-2 text-xs">
                    <button
                      type="button"
                      disabled={enCours}
                      onClick={() => ouvrirDetails(etablissement)}
                      className="rounded border border-brand-200 px-2 py-1 text-brand-800 hover:bg-brand-50"
                    >
                      Fiche
                    </button>
                    {estSuperAdmin && (
                      <>
                      {!etablissement.provisionne_le && (
                        <button
                          type="button"
                          disabled={enCours}
                          onClick={() => provisionner(etablissement)}
                          className="rounded border border-brand-200 px-2 py-1 text-brand-800 hover:bg-brand-50"
                        >
                          Provisionner
                        </button>
                      )}
                      <button
                        type="button"
                        disabled={enCours}
                        onClick={() => migrer(etablissement)}
                        className="btn-secondary px-2 py-1 text-xs"
                      >
                        Migrer
                      </button>
                      <button
                        type="button"
                        onClick={() => setSuppression(etablissement)}
                        className="rounded border border-red-200 px-2 py-1 text-red-700 hover:bg-red-50"
                      >
                        Supprimer
                      </button>
                      </>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {creation && (
        <Modal title="Ouvrir un établissement" onClose={() => setCreation(null)}>
          <form onSubmit={creer} className="space-y-3">
            {CHAMPS_CREATION.map((champ) => (
              <label key={champ.key} className="block text-sm">
                <span className="mb-1 block font-medium text-ink-700">{champ.label}</span>
                <input
                  type={champ.type ?? "text"}
                  required={champ.requis}
                  value={creation[champ.key] ?? ""}
                  onChange={(e) => setCreation({ ...creation, [champ.key]: e.target.value })}
                  className="field w-full"
                />
              </label>
            ))}

            <p className="text-xs text-ink-500">
              La base de l’établissement est créée et migrée immédiatement, et un compte de
              direction y est amorcé.
            </p>

            <button
              type="submit"
              disabled={enCours}
              className="btn w-full bg-ink-900 text-white hover:bg-ink-800"
            >
              {enCours ? "Provisioning…" : "Créer et provisionner"}
            </button>
          </form>
        </Modal>
      )}

      {identifiants && (
        <Modal title="Identifiants à transmettre" onClose={() => setIdentifiants(null)}>
          <p className="mb-3 text-sm text-ink-700">
            Compte de direction de <strong>{identifiants.code}</strong>. Le mot de passe n’est
            affiché qu’une fois : il n’est conservé que haché.
          </p>
          <dl className="space-y-1 rounded-lg bg-ink-50 p-3 font-mono text-sm">
            <div>{identifiants.email}</div>
            <div>{identifiants.password}</div>
          </dl>
        </Modal>
      )}

      {suppression && (
        <Modal
          title={`Supprimer ${suppression.code}`}
          onClose={() => {
            setSuppression(null);
            setConfirmation("");
          }}
        >
          <form onSubmit={supprimer} className="space-y-3">
            <p className="text-sm text-red-700">
              Cette action supprime la base de données et les fichiers de{" "}
              <strong>{suppression.nom}</strong>. Elle est irréversible. Pour une suspension
              d’abonnement, utilisez plutôt le statut « suspendu ».
            </p>

            <label className="block text-sm">
              <span className="mb-1 block font-medium text-ink-700">
                Retapez le code {suppression.code} pour confirmer
              </span>
              <input
                type="text"
                value={confirmation}
                onChange={(e) => setConfirmation(e.target.value)}
                className="field w-full uppercase"
              />
            </label>

            <button
              type="submit"
              disabled={enCours || confirmation.toUpperCase() !== suppression.code}
              className="btn w-full bg-red-700 text-white hover:bg-red-800"
            >
              Supprimer définitivement
            </button>
          </form>
        </Modal>
      )}

      {details && (
        <Modal
          title={`Fiche établissement — ${details.data.nom}`}
          onClose={() => {
            setDetails(null);
            setEditionDetails(false);
          }}
        >
          {editionDetails ? (
            <form onSubmit={enregistrerDetails} className="space-y-3">
              {CHAMPS_EDITION.map((champ) => (
                <label key={champ.key} className="block text-sm">
                  <span className="mb-1 block font-medium text-ink-700">{champ.label}</span>
                  <input
                    type={champ.type ?? "text"}
                    required={champ.requis}
                    value={details.data[champ.key] ?? ""}
                    onChange={(e) => setDetails({
                      ...details,
                      data: { ...details.data, [champ.key]: e.target.value },
                    })}
                    className="field w-full"
                  />
                </label>
              ))}
              <label className="block text-sm">
                <span className="mb-1 block font-medium text-ink-700">Notes internes</span>
                <textarea
                  rows={3}
                  value={details.data.notes ?? ""}
                  onChange={(e) => setDetails({
                    ...details,
                    data: { ...details.data, notes: e.target.value },
                  })}
                  className="field w-full"
                />
              </label>
              <p className="text-xs text-ink-500">
                Code et nom de base immuables. Les identifiants techniques ne sont jamais relus ; seul un nouveau mot de passe peut être enregistré.
              </p>
              <div className="flex gap-2">
                <button type="button" onClick={() => setEditionDetails(false)} className="btn-secondary flex-1">Annuler</button>
                <button type="submit" disabled={enCours} className="btn flex-1 bg-ink-900 text-white">
                  {enCours ? "Enregistrement…" : "Enregistrer"}
                </button>
              </div>
            </form>
          ) : (
            <div className="space-y-4 text-sm">
              <dl className="grid gap-2 sm:grid-cols-2">
                <Detail label="Code" value={details.data.code} />
                <Detail label="Statut" value={details.data.statut} />
                <Detail label="Ville / pays" value={[details.data.ville, details.data.pays].filter(Boolean).join(", ")} />
                <Detail label="Fuseau" value={details.data.fuseau} />
                <Detail label="Contact" value={details.data.contact_nom} />
                <Detail label="E-mail" value={details.data.contact_email} />
                <Detail label="Téléphone" value={details.data.contact_tel} />
                <Detail label="Base configurée" value={details.data.db_name || `auditron_${details.data.code.toLowerCase()}`} />
                <Detail label="Provisionné le" value={details.data.provisionne_le ? new Date(details.data.provisionne_le).toLocaleString("fr-FR") : "Non provisionné"} />
              </dl>
              {details.data.notes && (
                <div><strong>Notes internes</strong><p className="whitespace-pre-wrap text-ink-600">{details.data.notes}</p></div>
              )}
              {details.usage && (
                <div className="rounded-xl bg-ink-50 p-3">
                  <h3 className="mb-2 font-semibold text-ink-800">Utilisation du compte</h3>
                  {details.usage.injoignable ? (
                    <p className="text-amber-700">Base injoignable — vérifiez la configuration.</p>
                  ) : (
                    <p>{details.usage.personnel} membres du personnel · {details.usage.devices_actifs} appareil(s) actif(s) · {details.usage.presences_30j} pointage(s) sur 30 jours · dernier pointage : {details.usage.derniere_presence ?? "aucun"}</p>
                  )}
                </div>
              )}
              {estSuperAdmin && (
                <button type="button" onClick={() => setEditionDetails(true)} className="btn w-full bg-ink-900 text-white">
                  Modifier la fiche
                </button>
              )}
            </div>
          )}
        </Modal>
      )}
    </div>
  );
}

function Detail({ label, value }) {
  return (
    <div>
      <dt className="text-xs uppercase tracking-wide text-ink-400">{label}</dt>
      <dd className="font-medium text-ink-800">{value || "—"}</dd>
    </div>
  );
}
