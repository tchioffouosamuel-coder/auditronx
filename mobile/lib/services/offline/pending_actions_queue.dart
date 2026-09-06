import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import 'pending_action.dart';

/// File d'actions en attente de synchro (§offline-sync), persistée pour
/// survivre à un redémarrage de l'app avant reconnexion. Partagée entre les
/// deux sessions (enseignant/admin) : chaque action garde son [AuthMode] pour
/// être rejouée avec le bon client à la synchro (voir [SyncEngine]).
class PendingActionsQueue {
  PendingActionsQueue._();
  static final PendingActionsQueue instance = PendingActionsQueue._();

  final _storage = const FlutterSecureStorage();
  static const _key = 'auditron_pending_actions';
  static const _uuid = Uuid();

  Future<List<PendingAction>> all() async {
    final raw = await _storage.read(key: _key);
    if (raw == null) return [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list.map((e) => PendingAction.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<PendingAction> enqueue({
    required AuthMode authMode,
    required String path,
    required Map<String, dynamic> body,
    required String label,
  }) async {
    final action = PendingAction(
      id: _uuid.v4(),
      authMode: authMode,
      path: path,
      body: body,
      label: label,
      createdAt: DateTime.now(),
    );

    final actions = await all()..add(action);
    await _save(actions);
    return action;
  }

  Future<void> remove(String id) async {
    final actions = (await all())..removeWhere((a) => a.id == id);
    await _save(actions);
  }

  Future<void> _save(List<PendingAction> actions) =>
      _storage.write(key: _key, value: jsonEncode(actions.map((a) => a.toJson()).toList()));
}
