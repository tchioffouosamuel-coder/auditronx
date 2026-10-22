import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:in_app_update/in_app_update.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

/// Mise à jour de l'application depuis le Play Store (§maj-app).
///
/// L'app est distribuée en AAB sur le Play Store : c'est Play qui connaît la
/// version publiée, pas l'API Auditron. L'API « In-App Updates » est donc
/// interrogée directement — aucun endpoint serveur à maintenir, et aucun
/// numéro de version à recopier quelque part à chaque publication.
///
/// Le téléchargement est confié à Play (`performImmediateUpdate`) : il
/// fonctionne même si l'app est fermée en cours de route, et l'installation
/// n'exige pas d'autoriser les « sources inconnues ». Quand l'API n'est pas
/// disponible — build installé hors Play Store, Play Services absents — on
/// retombe sur l'ouverture de la fiche Play.
class AppUpdateService {
  AppUpdateService._();
  static final AppUpdateService instance = AppUpdateService._();

  /// Identifiant Play de l'application, pour le lien de repli.
  static const String _idPlayStore = 'com.auditronx.auditron_x_app';

  /// Au-delà de ce seuil, la mise à jour est imposée : la modale ne propose
  /// plus « Plus tard ». La priorité est fixée à la publication, côté Play
  /// Console (`inAppUpdatePriority`, 0 à 5) ; elle vaut 0 par défaut, donc une
  /// publication ordinaire reste reportable.
  static const int _prioriteBloquante = 4;

  /// Délai minimal entre deux interrogations de Play. L'app revérifie à chaque
  /// retour au premier plan ; sans ce palier, un simple aller-retour vers le
  /// scanner de QR relancerait l'appel (et la modale) en boucle.
  static const Duration _delaiEntreVerifications = Duration(minutes: 30);

  DateTime? _derniereVerification;
  String? _versionInstallee;

  /// Version lisible de l'app installée (« 1.0.2 (3) »), pour l'afficher dans
  /// la modale : l'utilisateur qui signale un problème peut la citer.
  Future<String> get versionInstallee async {
    if (_versionInstallee != null) return _versionInstallee!;
    try {
      final info = await PackageInfo.fromPlatform();
      return _versionInstallee = '${info.version} (${info.buildNumber})';
    } catch (_) {
      return _versionInstallee = '';
    }
  }

  /// Cherche une version plus récente sur le Play Store.
  ///
  /// Renvoie null quand il n'y a rien à proposer : plateforme non Android,
  /// app déjà à jour, ou Play injoignable. Une panne de la vérification ne
  /// doit jamais bloquer le démarrage, donc toute erreur est avalée.
  Future<AppUpdateDisponible?> rechercher({bool forcer = false}) async {
    if (kIsWeb || !Platform.isAndroid) return null;

    final derniere = _derniereVerification;
    if (!forcer &&
        derniere != null &&
        DateTime.now().difference(derniere) < _delaiEntreVerifications) {
      return null;
    }
    _derniereVerification = DateTime.now();

    try {
      final info = await InAppUpdate.checkForUpdate();
      if (info.updateAvailability != UpdateAvailability.updateAvailable) {
        return null;
      }
      return AppUpdateDisponible(
        versionDisponible: info.availableVersionCode,
        bloquante: (info.updatePriority) >= _prioriteBloquante,
        installationImmediatePossible: info.immediateUpdateAllowed,
      );
    } catch (erreur) {
      debugPrint('Vérification de mise à jour impossible : $erreur');
      return null;
    }
  }

  /// Lance la mise à jour. Renvoie false si rien n'a pu être engagé, pour que
  /// la modale puisse en informer l'utilisateur au lieu de se fermer sur un
  /// écran inchangé.
  Future<bool> installer(AppUpdateDisponible maj) async {
    if (maj.installationImmediatePossible) {
      try {
        final resultat = await InAppUpdate.performImmediateUpdate();
        // Un refus de l'utilisateur dans la boîte Play n'est pas un échec : on
        // le traite comme tel pour laisser la modale ouverte.
        return resultat == AppUpdateResult.success;
      } catch (erreur) {
        debugPrint('Mise à jour in-app impossible : $erreur');
      }
    }
    return ouvrirFichePlayStore();
  }

  /// Repli : ouvre la fiche de l'app dans Play Store (ou le navigateur).
  Future<bool> ouvrirFichePlayStore() async {
    for (final url in [
      Uri.parse('market://details?id=$_idPlayStore'),
      Uri.parse('https://play.google.com/store/apps/details?id=$_idPlayStore'),
    ]) {
      try {
        if (await launchUrl(url, mode: LaunchMode.externalApplication)) {
          return true;
        }
      } catch (_) {
        // Play Store absent : on tente l'URL suivante.
      }
    }
    return false;
  }

  /// Oublie le palier anti-rebond, pour qu'un « Rechercher les mises à jour »
  /// déclenché à la main interroge réellement Play.
  void reinitialiserPalier() => _derniereVerification = null;
}

/// Mise à jour proposée par le Play Store.
@immutable
class AppUpdateDisponible {
  const AppUpdateDisponible({
    required this.versionDisponible,
    required this.bloquante,
    required this.installationImmediatePossible,
  });

  /// `versionCode` publié sur Play, quand Play le communique.
  final int? versionDisponible;

  /// Vrai quand la publication a été marquée prioritaire : la modale n'offre
  /// alors aucune échappatoire.
  final bool bloquante;

  /// Vrai quand Play accepte de télécharger et installer sans quitter l'app.
  final bool installationImmediatePossible;
}
