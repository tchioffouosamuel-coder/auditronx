import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';

const _cacheKey = 'admin_activation_requests';

/// Demandes d'activation (§admin-mobile, §otp-approval) — équivalent mobile de
/// l'onglet "Demandes d'activation" d'AppareilsPage.jsx : Valider pousse l'OTP
/// par notification à l'enseignant, Refuser annule la demande. C'est la même
/// notification de validation (Google-style) qui peut aussi arriver ici sans
/// avoir eu besoin d'ouvrir l'app, mais ce tableau reste consultable pour agir
/// à tout moment.
class AdminActivationRequestsScreen extends StatefulWidget {
  const AdminActivationRequestsScreen({super.key});

  @override
  State<AdminActivationRequestsScreen> createState() => _AdminActivationRequestsScreenState();
}

class _AdminActivationRequestsScreenState extends State<AdminActivationRequestsScreen> {
  late Future<List<dynamic>> _future;
  String? _error;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      _cacheKey,
      () => AdminApiClient.instance.get('/devices/activation-requests', query: {'statut': 'en_attente'}),
    );
    return (data as Map<String, dynamic>)['data'] as List<dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  /// Retire optimistement la demande de la liste affichée/mise en cache
  /// (§offline-sync) : qu'elle parte tout de suite au serveur ou soit mise en
  /// file d'attente hors-ligne, l'admin ne doit pas la revoir comme "en
  /// attente" après avoir déjà tranché.
  Future<void> _removeFromCache(int requestId) async {
    final requests = List<dynamic>.from(await _future)..removeWhere((r) => r['id'] == requestId);
    await OfflineCache.instance.overwrite(_cacheKey, {'data': requests});
    setState(() => _future = Future.value(requests));
  }

  Future<void> _approve(Map<String, dynamic> request) async {
    setState(() => _error = null);
    await _removeFromCache(request['id'] as int);

    try {
      await AdminApiClient.instance.post('/devices/activation-requests/${request['id']}/approve', {});
    } on ApiException catch (e) {
      if (e.statusCode != 0) {
        setState(() => _error = e.message);
        return;
      }
      await PendingActionsQueue.instance.enqueue(
        authMode: AuthMode.admin,
        path: '/devices/activation-requests/${request['id']}/approve',
        body: const {},
        label: "Valider l'activation de ${request['enseignant']?['nom']}",
      );
      await SyncEngine.instance.notifyEnqueued();
    }
  }

  Future<void> _reject(Map<String, dynamic> request) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Refuser la demande ?'),
        content: Text("${request['enseignant']?['nom']} ne recevra pas de code d'activation."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Refuser')),
        ],
      ),
    );
    if (confirmed != true) return;

    await _removeFromCache(request['id'] as int);

    try {
      await AdminApiClient.instance.post('/devices/activation-requests/${request['id']}/reject', {});
    } on ApiException catch (e) {
      if (e.statusCode != 0) rethrow;
      await PendingActionsQueue.instance.enqueue(
        authMode: AuthMode.admin,
        path: '/devices/activation-requests/${request['id']}/reject',
        body: const {},
        label: "Refuser l'activation de ${request['enseignant']?['nom']}",
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

          final requests = snapshot.data ?? [];

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (_error != null) ...[
                Text(_error!, style: const TextStyle(color: Colors.red)),
                const SizedBox(height: 12),
              ],
              if (requests.isEmpty) const Padding(padding: EdgeInsets.all(8), child: Text('Aucune demande en attente.')),
              ...requests.map((r) {
                final request = r as Map<String, dynamic>;
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(request['enseignant']?['nom'] ?? '—', style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(request['enseignant']?['tel'] ?? '', style: const TextStyle(color: AuditronColors.ink500)),
                        if (request['code'] != null) ...[
                          const SizedBox(height: 8),
                          Text(
                            request['code'],
                            style: const TextStyle(fontFamily: 'monospace', fontSize: 20, fontWeight: FontWeight.w900, letterSpacing: 4),
                          ),
                        ],
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: FilledButton(onPressed: () => _approve(request), child: const Text('Valider')),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: OutlinedButton(onPressed: () => _reject(request), child: const Text('Refuser')),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              }),
            ],
          );
        },
      ),
    );
  }
}
