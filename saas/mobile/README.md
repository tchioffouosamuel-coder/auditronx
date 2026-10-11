# Auditron X — application mobile

Application Flutter multi-espace :

- **Établissement** : espace enseignant et administration du tenant sélectionné.
- **Équipe Auditron** : portail central du personnel Auditron, connecté aux
  routes `/api/central/*` sans en-tête de tenant.

Depuis l'écran d'activation, choisir « Équipe Auditron ». Une session Auditron
reste distincte des sessions enseignant et établissement. Le tableau de bord
central, le parc, les présences, les factures, les offres et abonnements sont
accessibles au personnel connecté. La gestion des établissements, des paiements,
des offres et des comptes de l'équipe est réservée au rôle `super_admin`, comme
sur l'API et le portail web.

Les mots de passe initiaux générés lors de la création d'un compte ne sont
affichés qu'une seule fois. Les factures PDF sont partagées via le sélecteur
d'applications du téléphone.

Sur l'écran d'activation, les établissements ne sont pas listés : le code doit
être saisi ou lu depuis le QR communiqué par l'établissement.

## Développement

L'URL de l'API peut être remplacée au lancement :

```sh
flutter run --dart-define=AUDITRON_API_URL=http://10.0.2.2:8000/api
```
