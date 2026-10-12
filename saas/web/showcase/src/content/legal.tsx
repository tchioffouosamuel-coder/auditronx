import type { ReactNode } from 'react';

export const SUPPORT_EMAIL = 'support@auditronx.com';

export interface LegalDocument {
  kind: 'terms' | 'privacy';
  path: string;
  title: string;
  description: string;
  sections: { id: string; title: string; content: ReactNode }[];
}

const supportLink = <a href={`mailto:${SUPPORT_EMAIL}`}>{SUPPORT_EMAIL}</a>;

export const LEGAL_DOCUMENTS: LegalDocument[] = [
  {
    kind: 'terms',
    path: '/conditions-utilisation/',
    title: 'Conditions d’utilisation',
    description: 'Les règles applicables à la vitrine, au portail de direction et à l’application mobile Auditron-X.',
    sections: [
      {
        id: 'conditions-objet',
        title: 'Objet et périmètre',
        content: <>
          <p>Auditron-X accompagne les établissements scolaires dans le suivi des présences du personnel enseignant et administratif, de l’assiduité et des activités pédagogiques. Le service associe un portail web, une application mobile et, selon l’installation, des bornes de pointage.</p>
          <p>Ces conditions encadrent l’utilisation de ces interfaces. Le contrat conclu avec l’établissement précise les prestations souscrites, les équipements, les engagements de service et les conditions commerciales. Les dispositions légales impératives restent applicables.</p>
        </>,
      },
      {
        id: 'conditions-acces',
        title: 'Comptes et accès',
        content: <>
          <p>L’accès aux données scolaires est réservé aux personnes habilitées par leur établissement. L’activation de l’application utilise le code de l’établissement et les identifiants attribués à l’utilisateur. Les droits varient selon le rôle et les habilitations accordées.</p>
          <p>Chaque utilisateur doit protéger ses identifiants, changer son mot de passe initial, garder son téléphone sécurisé et signaler rapidement toute perte, compromission ou utilisation non autorisée. Le partage d’un compte ou le contournement d’une restriction d’accès est interdit.</p>
          <p>L’établissement est responsable de l’attribution des rôles et du retrait des accès lorsqu’une personne quitte ses fonctions.</p>
        </>,
      },
      {
        id: 'conditions-pointage',
        title: 'Pointage et synchronisation',
        content: <>
          <p>Le pointage mobile repose sur la lecture d’un QR code au point de contrôle et la transmission à une borne de proximité par Bluetooth. Certaines opérations peuvent être enregistrées localement avant leur synchronisation avec le serveur.</p>
          <p>Un envoi accepté par la borne ne signifie pas nécessairement que le portail a déjà reçu et traité le pointage. La disponibilité de la borne, son alimentation, les permissions du téléphone et la connexion de synchronisation influencent le fonctionnement du service.</p>
          <p>Les pointages doivent correspondre à une présence réelle. Une procuration n’est permise qu’aux personnes disposant de l’habilitation prévue et doit comporter un motif. Une anomalie doit être signalée à l’administration de l’établissement pour vérification.</p>
        </>,
      },
      {
        id: 'conditions-responsabilites',
        title: 'Responsabilités de l’établissement',
        content: <>
          <p>L’établissement définit les horaires, emplois du temps, règles d’assiduité et habilitations. Il veille à l’exactitude des informations saisies et informe les personnes concernées des dispositifs de suivi utilisés.</p>
          <p>Il lui appartient de vérifier la licéité des traitements et les formalités applicables, notamment lorsqu’une borne produit des photos de pointage ou qu’un dispositif de contrôle d’identité est envisagé. Les pièces justificatives doivent être limitées aux informations nécessaires.</p>
          <p>Les tableaux de bord, alertes et rapports sont des outils de suivi. Ils ne remplacent pas la vérification humaine, l’examen des justificatifs ni les procédures applicables avant toute décision concernant un membre du personnel.</p>
        </>,
      },
      {
        id: 'conditions-usage',
        title: 'Usages interdits et contenus',
        content: <>
          <p>Il est interdit de falsifier un pointage, usurper une identité, modifier une borne sans autorisation, tenter d’accéder aux données d’un autre établissement, extraire des données sans habilitation ou perturber la sécurité et le fonctionnement du service.</p>
          <p>Les utilisateurs doivent disposer des droits nécessaires sur les documents déposés. Les informations concernant le personnel, les cours et les justificatifs ne doivent être consultées ou diffusées que pour les besoins autorisés de l’établissement.</p>
          <p>La marque, les logiciels et les éléments de présentation d’Auditron-X restent protégés par les droits applicables. L’accès au service n’en transfère pas la propriété. Les données et documents confiés au service ne deviennent pas la propriété d’Auditron-X.</p>
        </>,
      },
      {
        id: 'conditions-abonnement',
        title: 'Offres et abonnements',
        content: <>
          <p>Les fonctionnalités et limites d’utilisation dépendent de l’offre choisie par l’établissement. Les tarifs présentés sur la vitrine doivent être confirmés dans la proposition commerciale ou le contrat avant souscription.</p>
          <p>La durée de l’abonnement, les modalités de paiement, de renouvellement, de résiliation et les éventuelles conditions de remboursement sont celles convenues avec l’établissement. Une demande de démonstration ne constitue pas une souscription.</p>
        </>,
      },
      {
        id: 'conditions-disponibilite',
        title: 'Disponibilité et fin d’accès',
        content: <>
          <p>Des interruptions peuvent survenir lors d’une maintenance, d’une mise à jour, d’un incident technique ou d’une défaillance des équipements ou du réseau. Les engagements particuliers de disponibilité et d’assistance sont précisés dans le contrat de l’établissement.</p>
          <p>Un accès peut être révoqué par l’établissement ou restreint pour protéger le service en cas de compromission ou d’usage non autorisé. Pour préparer une fin d’abonnement, l’établissement doit contacter le support au sujet des exports disponibles et du traitement des données restantes. La clôture d’un accès ne supprime pas automatiquement toutes les données.</p>
        </>,
      },
      {
        id: 'conditions-contact',
        title: 'Confidentialité, évolutions et contact',
        content: <>
          <p>Les traitements de données sont décrits dans la <a href="/politique-confidentialite/">politique de confidentialité</a>. Les présentes conditions peuvent évoluer avec le service ; la date indiquée en haut de page identifie cette version. Les modifications contractuelles restent soumises aux modalités convenues avec l’établissement.</p>
          <p>Pour une question, un incident ou une réclamation, contactez {supportLink}. Les demandes commerciales et de démonstration peuvent être adressées à <a href="mailto:demo@auditronx.com">demo@auditronx.com</a>.</p>
        </>,
      },
    ],
  },
  {
    kind: 'privacy',
    path: '/politique-confidentialite/',
    title: 'Politique de confidentialité',
    description: 'Les données utilisées pour le suivi des présences et des activités pédagogiques, leur utilisation et vos interlocuteurs.',
    sections: [
      {
        id: 'confidentialite-perimetre',
        title: 'Périmètre et interlocuteurs',
        content: <>
          <p>Cette politique concerne la vitrine Auditron-X, le portail web, l’application mobile et les échanges avec les bornes de pointage. Elle s’adresse aux visiteurs, aux membres du personnel et aux personnes habilitées par les établissements.</p>
          <p>L’établissement détermine les finalités du suivi de son personnel, les données saisies et les personnes habilitées. L’équipe Auditron-X assure les fonctions techniques prévues par le service. Le contrat de l’établissement précise les responsabilités de chacun et les instructions applicables au traitement des données.</p>
          <p>Pour une question concernant votre dossier, contactez d’abord l’administration de votre établissement. Pour une demande relative au service ou à cette politique, écrivez à {supportLink}.</p>
        </>,
      },
      {
        id: 'confidentialite-donnees',
        title: 'Données concernées',
        content: <>
          <p>Selon les fonctionnalités utilisées et les informations renseignées par l’établissement, le service peut traiter :</p>
          <ul>
            <li><strong>Identité et compte :</strong> nom, matricule, coordonnées, fonction, section, compte administrateur, rôles et habilitations.</li>
            <li><strong>Présence :</strong> arrivées et départs, horodatages, point de contrôle, retards, absences, procurations et motifs, signalements et justificatifs.</li>
            <li><strong>Suivi pédagogique :</strong> emplois du temps, classes, disciplines, cahier de texte, cours réalisés, validations et progression des leçons.</li>
            <li><strong>Fonctionnement technique :</strong> identifiant de l’appareil, activation, jetons de session et de notification, journaux d’accès et d’opérations.</li>
            <li><strong>Relation avec Auditron-X :</strong> coordonnées et messages transmis pour une démonstration ou une assistance, informations d’abonnement et de facturation de l’établissement.</li>
          </ul>
          <p>Des photos de référence ou des photos associées au pointage peuvent être traitées selon l’équipement et les fonctions utilisés. Les bornes équipées d’une caméra peuvent transmettre une photo prise au moment du pointage. Le scan QR sur le téléphone ne constitue pas, à lui seul, une reconnaissance faciale.</p>
          <p>Si un dispositif biométrique est déployé, l’établissement doit fournir une information spécifique sur les données, la finalité, le fondement applicable et la conservation avant sa mise en service. Cette politique ne vaut pas consentement à un traitement biométrique.</p>
        </>,
      },
      {
        id: 'confidentialite-finalites',
        title: 'Finalités et fondement des traitements',
        content: <>
          <p>Les données servent à gérer les accès, enregistrer et vérifier les présences, traiter les anomalies, organiser le suivi pédagogique, produire les rapports autorisés, transmettre les notifications et assurer l’assistance et la sécurité du service.</p>
          <p>L’établissement doit identifier et communiquer le fondement légal applicable à son suivi du personnel. Une autorisation technique du téléphone ou l’utilisation de l’application ne remplace pas ce fondement. Les données transmises lors d’un contact avec Auditron-X servent à traiter cette demande et la relation qui en découle.</p>
        </>,
      },
      {
        id: 'confidentialite-permissions',
        title: 'Permissions du téléphone',
        content: <>
          <ul>
            <li><strong>Caméra :</strong> lecture des QR codes de pointage. Les photos produites par une borne relèvent du dispositif de l’établissement, distinct de cette lecture.</li>
            <li><strong>Bluetooth et appareils à proximité :</strong> détection et communication locale avec la borne.</li>
            <li><strong>Localisation sur Android :</strong> selon le système, son activation ou son autorisation peut être nécessaire à la recherche Bluetooth. Le parcours de pointage mobile actuel ne collecte pas de coordonnées GPS ni de trajet.</li>
            <li><strong>Notifications :</strong> réception des messages du service, lorsque cette permission est accordée.</li>
          </ul>
          <p>Ces permissions peuvent être gérées dans les réglages du téléphone. Leur refus peut empêcher la fonction correspondante, notamment le scan ou la communication avec la borne.</p>
        </>,
      },
      {
        id: 'confidentialite-stockage',
        title: 'Stockage local et mode hors ligne',
        content: <>
          <p>L’application conserve localement des informations de session, des données mises en cache et des opérations en attente de synchronisation. La borne peut également conserver temporairement des paquets de pointage avant leur transmission au serveur.</p>
          <p>Une donnée visible hors ligne peut ne pas refléter la dernière version du serveur. La suppression de l’application ou la fermeture d’une session ne vaut pas demande de suppression des données déjà enregistrées par l’établissement.</p>
        </>,
      },
      {
        id: 'confidentialite-destinataires',
        title: 'Accès et services tiers',
        content: <>
          <p>Les accès dans Auditron-X sont organisés par établissement et par habilitation : un membre du personnel accède aux fonctions de son compte, tandis que la direction et les administrateurs disposent des droits qui leur ont été attribués. Les personnes autorisées à assurer l’exploitation et l’assistance peuvent intervenir dans le cadre de leurs missions.</p>
          <p>Les notifications mobiles utilisent Firebase Cloud Messaging, un service Google, avec un jeton identifiant l’installation destinataire et les informations nécessaires à la notification. La vitrine charge également ses polices depuis Google Fonts ; cette requête communique au fournisseur des informations techniques telles que l’adresse IP et le navigateur.</p>
          <p>Un contact par e-mail ou WhatsApp utilise les services de messagerie choisis. Leurs propres règles de confidentialité s’appliquent. Certains fournisseurs peuvent traiter des données hors de votre pays ; les conditions d’hébergement et de transfert propres à votre établissement sont à vérifier auprès du support et dans son contrat.</p>
        </>,
      },
      {
        id: 'confidentialite-conservation',
        title: 'Conservation et suppression',
        content: <>
          <p>La durée de conservation dépend de la catégorie de données, des besoins documentés de l’établissement, de ses obligations applicables et des dispositions contractuelles. Cette page ne fixe pas une durée unique pour les présences, les documents pédagogiques, les photos ou les journaux techniques.</p>
          <p>L’établissement doit définir les durées adaptées et organiser l’archivage ou la suppression des données qui ne sont plus nécessaires. Les modalités concernant les sauvegardes, les copies locales et les données restantes à la fin du contrat doivent être précisées avec le support. Une désactivation de compte ne constitue pas un effacement automatique.</p>
        </>,
      },
      {
        id: 'confidentialite-securite',
        title: 'Protection des données',
        content: <>
          <p>Le service utilise une authentification, des habilitations, une séparation des espaces des établissements et un stockage sécurisé des informations de session dans l’application mobile. Ces mesures ne dispensent pas de protéger les téléphones, les comptes administrateurs, les bornes et les exports de rapports.</p>
          <p>Aucun système ne permet de garantir une sécurité absolue. En cas de perte de téléphone, d’accès suspect ou de divulgation involontaire, informez immédiatement votre établissement et {supportLink}, sans envoyer votre mot de passe ni votre jeton de session.</p>
        </>,
      },
      {
        id: 'confidentialite-droits',
        title: 'Vos demandes et vos droits',
        content: <>
          <p>Vous pouvez demander des informations sur vos données et, dans les conditions prévues par la réglementation applicable, leur accès, leur rectification, leur suppression ou vous opposer à certains traitements. Certaines demandes peuvent être limitées par les obligations de conservation de l’établissement ou les droits d’autres personnes.</p>
          <p>Adressez votre demande à l’administration de votre établissement ou à {supportLink}, en précisant l’établissement et la nature de la demande. Une vérification d’identité proportionnée peut être nécessaire ; n’envoyez pas de document sensible avant qu’il soit demandé par un canal adapté.</p>
          <p>Le cadre applicable dépend du traitement et du pays concerné. Au Cameroun, un texte de référence est la <a href="https://prc.cm/fr/multimedia/documents/10258-loi-n-2024-017-du-23-12-2024-web" target="_blank" rel="noopener noreferrer">loi n° 2024/017 du 23 décembre 2024 relative à la protection des données à caractère personnel</a>.</p>
        </>,
      },
      {
        id: 'confidentialite-vitrine',
        title: 'Vitrine et mises à jour',
        content: <>
          <p>La vitrine actuelle ne comporte pas de traceur publicitaire ni d’outil de mesure d’audience. Les liens de démonstration ouvrent votre messagerie ou WhatsApp ; aucun formulaire de demande n’est enregistré directement par cette vitrine. Le chargement des polices externes reste décrit dans la rubrique « Accès et services tiers ».</p>
          <p>Cette politique peut être mise à jour pour refléter une évolution du service ou de ses traitements. La date en haut de page identifie la version publiée. Pour toute précision, contactez {supportLink}.</p>
        </>,
      },
    ],
  },
];
