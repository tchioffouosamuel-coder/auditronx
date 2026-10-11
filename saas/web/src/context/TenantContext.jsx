import { createContext, useCallback, useContext, useEffect, useState } from "react";
import api from "../lib/api";
import apiCentral from "../lib/apiCentral";
import {
  brandingEnCache,
  codeEtablissement,
  definirEtablissement,
  oublierEtablissement,
} from "../lib/tenant";

const TenantContext = createContext(null);

/**
 * Établissement courant du portail unique : son code, son branding, et la
 * liste des établissements proposés à la connexion.
 *
 * Le branding mis en cache est affiché immédiatement, puis rafraîchi depuis
 * l'API : un changement de logo ou de couleurs côté éditeur se propage sans
 * que personne n'ait à vider son navigateur, mais l'écran de connexion
 * s'affiche déjà habillé, sans attendre le réseau.
 */
export function TenantProvider({ children }) {
  const [code, setCode] = useState(() => codeEtablissement());
  const [branding, setBranding] = useState(() => brandingEnCache());
  const [catalogue, setCatalogue] = useState([]);
  const [listePublique, setListePublique] = useState(true);
  const [chargement, setChargement] = useState(Boolean(codeEtablissement()));

  useEffect(() => {
    apiCentral
      .get("/catalogue")
      .then(({ data }) => {
        setCatalogue(data?.data ?? []);
        setListePublique(data?.liste_publique !== false);
      })
      .catch(() => {
        // Catalogue indisponible : la saisie manuelle du code reste possible,
        // c'est précisément à ça qu'elle sert.
        setCatalogue([]);
      });
  }, []);

  useEffect(() => {
    if (!code) {
      setChargement(false);
      return;
    }

    let annule = false;
    setChargement(true);

    api
      .get("/etablissement")
      .then(({ data }) => {
        if (annule) return;
        setBranding(data?.data ?? null);
        definirEtablissement(code, data?.data ?? null);
      })
      .catch(() => {
        // L'intercepteur de api.js a déjà traité les cas 402/404 ; ici, on ne
        // fait que ne pas écraser le branding en cache.
      })
      .finally(() => !annule && setChargement(false));

    return () => {
      annule = true;
    };
  }, [code]);

  // Couleurs de l'établissement appliquées comme variables CSS : les pages
  // utilisent `var(--couleur-etablissement)` sans savoir chez qui elles
  // tournent.
  useEffect(() => {
    const racine = document.documentElement;

    if (branding?.couleur_primaire) {
      racine.style.setProperty("--couleur-etablissement", branding.couleur_primaire);
    } else {
      racine.style.removeProperty("--couleur-etablissement");
    }

    if (branding?.couleur_secondaire) {
      racine.style.setProperty("--couleur-etablissement-2", branding.couleur_secondaire);
    } else {
      racine.style.removeProperty("--couleur-etablissement-2");
    }

    document.title = branding?.nom ? `${branding.nom} — Auditron X` : "Auditron X";
  }, [branding]);

  const choisir = useCallback((nouveauCode, brandingConnu = null) => {
    const normalise = definirEtablissement(nouveauCode, brandingConnu);

    if (!normalise) return "";

    setBranding(brandingConnu ?? null);
    setCode(normalise);

    return normalise;
  }, []);

  /**
   * Changer d'établissement remet l'application à zéro, par un rechargement
   * complet.
   *
   * Vider le stockage et poser `code` à "" ne suffisait pas : `AuthContext`
   * garde l'utilisateur dans son propre état React, initialisé une seule fois
   * au démarrage. Le jeton disparaissait du stockage mais l'interface
   * continuait de croire la session ouverte — la sidebar restait en place, la
   * redirection vers l'écran de connexion n'avait pas lieu, et le bouton
   * paraissait sans effet.
   *
   * Un rechargement plutôt qu'un câblage entre les deux contextes : tout ce
   * qui est en mémoire est propre à l'établissement quitté — session, branding,
   * listes déjà chargées, caches des pages ouvertes. Les remettre à zéro une à
   * une laisserait forcément un oubli, et une donnée d'un lycée affichée sous
   * le nom d'un autre est exactement ce qu'on ne peut pas se permettre.
   */
  const changerEtablissement = useCallback(() => {
    oublierEtablissement();
    setBranding(null);
    setCode("");
    window.location.assign("/");
  }, []);

  return (
    <TenantContext.Provider
      value={{
        code,
        branding,
        catalogue,
        listePublique,
        chargement,
        choisir,
        changerEtablissement,
      }}
    >
      {children}
    </TenantContext.Provider>
  );
}

export function useTenant() {
  return useContext(TenantContext);
}
