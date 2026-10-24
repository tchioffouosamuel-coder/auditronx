<?php

/**
 * Identité de l'établissement imprimée en entête de tous les documents
 * générés (§4.4). Les valeurs vivaient en dur dans deux vues PDF sur six :
 * les quatre autres sortaient sans entête officiel, et un déploiement dans un
 * autre établissement imposait d'éditer les Blade. On centralise ici, avec des
 * surcharges `.env` pour chaque installation.
 */
return [
    'pays' => env('ETABLISSEMENT_PAYS', 'REPUBLIQUE DU CAMEROUN'),
    'devise' => env('ETABLISSEMENT_DEVISE', 'Paix - Travail - Patrie'),
    'nom' => env('ETABLISSEMENT_NOM', 'LYCEE TECHNIQUE DE MEIGANGA'),

    'pays_en' => env('ETABLISSEMENT_PAYS_EN', 'REPUBLIC OF CAMEROON'),
    'devise_en' => env('ETABLISSEMENT_DEVISE_EN', 'Peace - Work - Fatherland'),
    'nom_en' => env('ETABLISSEMENT_NOM_EN', 'G.T.H.S MEIGANGA'),

    /** Fichier attendu dans `public/` ; l'entête s'imprime sans logo s'il manque. */
    'logo' => env('ETABLISSEMENT_LOGO', 'logo.png'),
];
