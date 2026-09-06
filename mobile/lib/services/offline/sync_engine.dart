import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

import '../admin_api_client.dart';
import '../api_client.dart';
import 'pending_action.dart';
import 'pending_actions_queue.dart';

/// Moteur de synchro pull/push (§offline-sync) : écoute la connectivité et
/// rejoue automatiquement la file d'actions en attente dès son retour.
/// "Dernier écrit gagne" : les actions sont rejouées telles quelles, dans leur
/// ordre de création, sans tentative de fusion avec un éventuel changement
/// serveur survenu entre-temps.
class SyncEngine extends ChangeNotifier {
  SyncEngine._();
  static final SyncEngine instance = SyncEngine._();

  bool _online = true;
  int _pendingCount = 0;
  bool _syncing = false;
  StreamSubscription<List<ConnectivityResult>>? _sub;

  bool get online => _online;
  int get pendingCount => _pendingCount;
  bool get syncing => _syncing;

  Future<void> start() async {
    await _refreshPendingCount();

    final results = await Connectivity().checkConnectivity();
    _online = !results.contains(ConnectivityResult.none);

    _sub = Connectivity().onConnectivityChanged.listen((results) {
      final wasOffline = !_online;
      _online = !results.contains(ConnectivityResult.none);
      notifyListeners();

      if (wasOffline && _online) flush();
    });

    if (_online) flush();
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  Future<void> _refreshPendingCount() async {
    _pendingCount = (await PendingActionsQueue.instance.all()).length;
    notifyListeners();
  }

  /// Rejoue la file dans l'ordre. S'arrête au premier échec réseau (on
  /// réessaiera au prochain retour de connectivité) mais purge les actions
  /// rejetées par le serveur (4xx/5xx) : les rejouer indéfiniment n'aiderait
  /// pas — au pire l'action est perdue, cohérent avec la légèreté du
  /// "dernier écrit gagne" choisi pour ces actions (bascules, valider/refuser,
  /// marquer lu...).
  Future<void> flush() async {
    if (_syncing) return;
    _syncing = true;
    notifyListeners();

    try {
      final actions = await PendingActionsQueue.instance.all();

      for (final action in actions) {
        try {
          await _replay(action);
          await PendingActionsQueue.instance.remove(action.id);
        } on ApiException catch (e) {
          if (e.statusCode == 0) {
            break; // toujours hors-ligne : on retentera au prochain retour réseau
          }
          debugPrint('SyncEngine.flush: action "${action.label}" rejetée par le serveur (${e.statusCode}), abandonnée');
          await PendingActionsQueue.instance.remove(action.id);
        }
      }
    } finally {
      await _refreshPendingCount();
      _syncing = false;
      notifyListeners();
    }
  }

  Future<void> _replay(PendingAction action) {
    return switch (action.authMode) {
      AuthMode.teacher => ApiClient.instance.post(action.path, action.body),
      AuthMode.admin => AdminApiClient.instance.post(action.path, action.body),
    };
  }

  /// À appeler juste après avoir mis une action en file (§offline-sync), pour
  /// que l'indicateur "N en attente" affiché à l'écran se mette à jour tout de
  /// suite plutôt qu'au prochain flush.
  Future<void> notifyEnqueued() => _refreshPendingCount();
}
