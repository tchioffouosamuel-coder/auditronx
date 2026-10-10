<?php

/*
|--------------------------------------------------------------------------
| Partage de ressources entre origines (CORS)
|--------------------------------------------------------------------------
|
| Le portail est servi depuis app.auditronx.com et appelle api.auditronx.com :
| toutes ses requêtes sont donc des requêtes entre origines, et sans ces
| en-têtes le navigateur les bloque avant même que l'API ne réponde.
|
| Publié explicitement plutôt que laissé aux valeurs par défaut du framework,
| pour que la liste des origines autorisées soit un réglage visible et
| modifiable sans toucher au code.
|
*/

$origines = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('AUDITRON_CORS_ORIGINS', '*'))
)));

return [

    // Seules les routes d'API sont concernées : la racine et les éventuelles
    // routes web n'ont aucune raison d'être appelées depuis un autre domaine.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * `*` par défaut : l'API s'authentifie par jeton Bearer, pas par cookie de
     * session, donc une origine tierce ne gagne rien à appeler l'API sans
     * jeton valide. Restreindre reste possible, et recommandé une fois les
     * domaines définitifs connus :
     *
     *   AUDITRON_CORS_ORIGINS=https://app.auditronx.com
     */
    'allowed_origins' => $origines,

    'allowed_origins_patterns' => [],

    // Doit inclure `X-Tenant` et `Authorization` : c'est `*` qui s'en charge.
    'allowed_headers' => ['*'],

    // Rendu lisible au client : permet au portail de vérifier sur quel
    // établissement l'API a travaillé (utile au support).
    'exposed_headers' => ['X-Tenant'],

    'max_age' => 3600,

    // Aucun cookie n'est échangé : les jetons voyagent dans l'en-tête
    // Authorization. Passer ceci à true interdirait d'ailleurs `*` ci-dessus.
    'supports_credentials' => false,

];
