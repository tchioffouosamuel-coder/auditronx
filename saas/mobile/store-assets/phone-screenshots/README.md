# Captures de telephone pour Google Play

Sept PNG de 1080 x 1920 px (9:16), chacun de moins de 8 Mo.
Importer les fichiers PNG dans leur ordre numerique, pas ce fichier README.

1. Journee enseignant : presence enregistree et cours du jour.
2. Cours du jour : seances et acces au cahier de texte.
3. Historique des presences : arrivees et ponctualite.
4. Tableau de bord direction : effectif, presences et assiduite par section.
5. Personnel : fiches sur une seule colonne.
6. Retards : bilan individuel et periode de suivi.
7. Bilans PDF : options de telechargement dans le panneau.

Les images sont rendues par les vrais composants Flutter, avec les donnees
de demonstration des tests. Aucun ecran n'est etire, recadre ou dessine
pour remplacer l'interface. Ce ne sont pas des captures d'un appareil physique.

Pour les regenerer, depuis `saas/mobile` :

```powershell
flutter test test/showcase_ui_test.dart --dart-define=AUDITRON_STORE_SCREENSHOTS=true --plain-name "export Play Store" --reporter expanded
```
