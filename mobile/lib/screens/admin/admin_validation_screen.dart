import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';

const _cacheKey = 'admin_validation';

/// Validation des présences (§admin-mobile) — équivalent mobile de
/// ValidationPage.jsx : calendrier des cours du jour, bascule fait/non_fait.
class AdminValidationScreen extends StatefulWidget {
  const AdminValidationScreen({super.key});

  @override
  State<AdminValidationScreen> createState() => _AdminValidationScreenState();
}

class _AdminValidationScreenState extends State<AdminValidationScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      _cacheKey,
      () => AdminApiClient.instance.get('/presences/validation'),
    );
    return (data as Map<String, dynamic>)['cours'] as List<dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  /// Bascule optimiste (§offline-sync) : le statut change à l'écran tout de
  /// suite, hors-ligne ou pas — l'appel réseau qui échoue est mis en file
  /// d'attente plutôt que de faire échouer l'action pour l'utilisateur.
  Future<void> _toggle(Map<String, dynamic> cours) async {
    final date = DateTime.now().toIso8601String().substring(0, 10);
    final body = {'emploi_du_temps_id': cours['emploi_du_temps_id'], 'date': date};

    final cachedCours = List<dynamic>.from(await _future);
    final index = cachedCours.indexWhere((c) => c['emploi_du_temps_id'] == cours['emploi_du_temps_id']);
    if (index != -1) {
      final updated = Map<String, dynamic>.from(cachedCours[index] as Map<String, dynamic>);
      updated['status'] = updated['status'] == 'fait' ? 'non_fait' : 'fait';
      cachedCours[index] = updated;
      await OfflineCache.instance.overwrite(_cacheKey, {'cours': cachedCours});
      setState(() => _future = Future.value(cachedCours));
    }

    try {
      await AdminApiClient.instance.post('/presences/validation/toggle', body);
    } on ApiException catch (e) {
      if (e.statusCode != 0) rethrow;
      await PendingActionsQueue.instance.enqueue(
        authMode: AuthMode.admin,
        path: '/presences/validation/toggle',
        body: body,
        label: 'Validation ${cours['discipline']} — ${cours['classe']}',
      );
      await SyncEngine.instance.notifyEnqueued();
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(children: [Padding(padding: const EdgeInsets.all(24), child: Text('${snapshot.error}'))]);
          }

          final cours = snapshot.data ?? [];
          if (cours.isEmpty) {
            return ListView(children: const [Padding(padding: EdgeInsets.all(24), child: Text("Aucun cours aujourd'hui."))]);
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: cours.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final c = cours[i] as Map<String, dynamic>;
              final fait = c['status'] == 'fait';

              return Card(
                child: ListTile(
                  leading: Icon(fait ? Icons.check_circle : Icons.radio_button_unchecked, color: fait ? AuditronColors.brand600 : AuditronColors.ink500),
                  title: Text('${c['discipline']} — ${c['classe']}'),
                  subtitle: Text('${c['enseignant']} · ${c['heure_debut']}–${c['heure_fin']}'),
                  onTap: () => _toggle(c),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
