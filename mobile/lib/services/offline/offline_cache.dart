import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Cache de lecture générique (§offline-sync) — même principe "réseau
/// d'abord, secours sur le cache local" que [PresenceRepository]
/// (historique enseignant), généralisé pour tous les écrans plutôt que de
/// réécrire ce couple try/catch à chaque fois : dashboard admin, validation,
/// alertes, signalements, demandes d'activation, devices...
///
/// Ne mémorise que la dernière réponse par clé (pas d'historique de versions)
/// — cohérent avec la stratégie de sync "dernier écrit gagne" (§offline-sync) :
/// pas de fusion, la donnée la plus récente écrase la précédente.
class OfflineCache {
  OfflineCache({FlutterSecureStorage? storage}) : _storage = storage ?? const FlutterSecureStorage();

  static final OfflineCache instance = OfflineCache();

  final FlutterSecureStorage _storage;

  String _key(String cacheKey) => 'auditron_cache_$cacheKey';

  /// Tente `fetch()` ; en cas d'échec réseau, retombe sur la dernière réponse
  /// mise en cache sous `cacheKey` si elle existe (sinon relance l'erreur).
  /// `fetch` doit renvoyer une valeur JSON-sérialisable (Map/List/primitif).
  Future<dynamic> readThrough(String cacheKey, Future<dynamic> Function() fetch) async {
    try {
      final data = await fetch();
      await _storage.write(key: _key(cacheKey), value: jsonEncode(data));
      return data;
    } catch (e) {
      final cached = await _storage.read(key: _key(cacheKey));
      if (cached != null) return jsonDecode(cached);
      rethrow;
    }
  }

  /// Écrase le cache local sans appel réseau — utilisé pour la mise à jour
  /// "optimiste" de l'UI après une action mise en file d'attente hors-ligne
  /// (§offline-sync), avant même que la synchro ne l'ait confirmée au serveur.
  Future<void> overwrite(String cacheKey, dynamic data) =>
      _storage.write(key: _key(cacheKey), value: jsonEncode(data));
}
