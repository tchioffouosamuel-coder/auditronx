import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';

const _cacheKey = 'admin_devices';

/// Gestion des appareils (§admin-mobile) — équivalent mobile de l'onglet
/// "Devices" d'AppareilsPage.jsx : liste + révocation.
class AdminDevicesScreen extends StatefulWidget {
  const AdminDevicesScreen({super.key});

  @override
  State<AdminDevicesScreen> createState() => _AdminDevicesScreenState();
}

class _AdminDevicesScreenState extends State<AdminDevicesScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      _cacheKey,
      () => AdminApiClient.instance.get('/devices', query: {'revoked': 'false'}),
    );
    return (data as Map<String, dynamic>)['data'] as List<dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _revoke(Map<String, dynamic> device) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Révoquer ce device ?'),
        content: Text("${device['teacher']?['nom'] ?? device['device_uuid']} devra se réactiver."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Révoquer')),
        ],
      ),
    );
    if (confirmed != true) return;

    // Optimiste (§offline-sync) : un device révoqué ne doit plus apparaître
    // comme actif à l'écran, même si l'appel réseau part en file d'attente.
    final devices = List<dynamic>.from(await _future)..removeWhere((d) => d['id'] == device['id']);
    await OfflineCache.instance.overwrite(_cacheKey, {'data': devices});
    setState(() => _future = Future.value(devices));

    try {
      await AdminApiClient.instance.post('/devices/${device['id']}/revoke', {});
    } on ApiException catch (e) {
      if (e.statusCode != 0) rethrow;
      await PendingActionsQueue.instance.enqueue(
        authMode: AuthMode.admin,
        path: '/devices/${device['id']}/revoke',
        body: const {},
        label: "Révoquer le device de ${device['teacher']?['nom'] ?? device['device_uuid']}",
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

          final devices = snapshot.data ?? [];
          if (devices.isEmpty) {
            return ListView(children: const [Padding(padding: EdgeInsets.all(24), child: Text('Aucun device actif.'))]);
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: devices.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final d = devices[i] as Map<String, dynamic>;
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.phone_android, color: AuditronColors.brand700),
                  title: Text(d['teacher']?['nom'] ?? '—'),
                  subtitle: Text('${d['device_type']} · ${d['device_uuid']}'),
                  trailing: TextButton(onPressed: () => _revoke(d), child: const Text('Révoquer')),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
