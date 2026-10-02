import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';

const _cacheKey = 'admin_devices';

/// Gestion des appareils (§admin-mobile) — équivalent mobile de l'onglet
/// "Devices" d'AppareilsPage.jsx : liste + révocation, avec recherche locale.
class AdminDevicesScreen extends StatefulWidget {
  const AdminDevicesScreen({super.key});

  @override
  State<AdminDevicesScreen> createState() => _AdminDevicesScreenState();
}

class _AdminDevicesScreenState extends State<AdminDevicesScreen> {
  final _searchController = TextEditingController();
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      _cacheKey,
      () => AdminApiClient.instance.get(
        '/devices',
        query: {'revoked': 'false', 'device_type': 'mobile'},
      ),
    );
    return ((data as Map<String, dynamic>)['data'] as List<dynamic>)
        .where((device) => device['device_type'] == 'mobile')
        .toList();
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
        content: Text(
          "${device['teacher']?['nom'] ?? device['device_uuid']} devra se réactiver.",
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Révoquer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    // Optimiste (§offline-sync) : un device révoqué ne doit plus apparaître
    // comme actif à l'écran, même si l'appel réseau part en file d'attente.
    final devices = List<dynamic>.from(await _future)
      ..removeWhere((d) => d['id'] == device['id']);
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
        label:
            "Révoquer le device de ${device['teacher']?['nom'] ?? device['device_uuid']}",
      );
      await SyncEngine.instance.notifyEnqueued();
    }
  }

  Future<void> _rotateToken(Map<String, dynamic> device) async {
    try {
      final response = await AdminApiClient.instance.post(
        '/devices/${device['id']}/rotate-token',
        {},
      );
      final token = (response as Map<String, dynamic>)['token'] as String;
      if (!mounted) return;
      await showDialog<void>(
        context: context,
        builder: (_) => AlertDialog(
          title: const Text('Nouveau token'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                "Ce token ne sera plus jamais affiché. Copiez-le maintenant et transmettez-le à la borne relais.",
              ),
              const SizedBox(height: 12),
              SelectableText(
                token,
                style: const TextStyle(
                  fontFamily: 'monospace',
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          actions: [
            Builder(
              builder: (dialogContext) => TextButton(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: token));
                  if (!dialogContext.mounted) return;
                  ScaffoldMessenger.of(
                    dialogContext,
                  ).showSnackBar(const SnackBar(content: Text('Token copié.')));
                },
                child: const Text('Copier'),
              ),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Fermer'),
            ),
          ],
        ),
      );
      await _refresh();
    } on ApiException catch (e) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: TextField(
            controller: _searchController,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              labelText: 'Rechercher un device',
              hintText: 'Nom, matricule, téléphone ou identifiant',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _searchController.text.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.clear),
                      tooltip: 'Effacer la recherche',
                      onPressed: () {
                        _searchController.clear();
                        setState(() {});
                      },
                    ),
            ),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<List<dynamic>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }

                final tous = snapshot.data ?? [];
                final query = _searchController.text.trim().toLowerCase();
                final devices = query.isEmpty
                    ? tous
                    : tous.where((d) => _matches(d, query)).toList();
                if (devices.isEmpty) {
                  return _message(
                    query.isEmpty
                        ? 'Aucun device actif.'
                        : 'Aucun device trouvé pour « ${_searchController.text.trim()} ».',
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  // +1 : ligne de compteur en tête de liste.
                  itemCount: devices.length + 1,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    if (i == 0) {
                      return Text(
                        query.isEmpty
                            ? '${tous.length} device(s) actif(s)'
                            : '${devices.length} sur ${tous.length} device(s) actif(s)',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AuditronColors.ink700,
                        ),
                      );
                    }
                    final d = devices[i - 1] as Map<String, dynamic>;
                    return Card(
                      child: ListTile(
                        leading: Icon(
                          d['device_type'] == 'relay_gateway'
                              ? Icons.router
                              : Icons.phone_android,
                          color: AuditronColors.brand700,
                        ),
                        title: Text(
                          d['teacher']?['nom'] ?? d['device_type'] ?? '—',
                        ),
                        subtitle: Text(
                          '${d['device_type']} · ${d['device_uuid']}',
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (d['device_type'] == 'relay_gateway')
                              IconButton(
                                tooltip: 'Régénérer le token',
                                icon: const Icon(
                                  Icons.vpn_key_outlined,
                                  size: 20,
                                ),
                                onPressed: () => _rotateToken(d),
                              ),
                            TextButton(
                              onPressed: () => _revoke(d),
                              child: const Text('Révoquer'),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ),
      ],
    );
  }

  /// Recherche locale : enseignant lié (nom, matricule, téléphone) ou
  /// identifiant du device.
  bool _matches(dynamic device, String query) {
    if (device is! Map) return false;
    final teacher = device['teacher'];
    return [
      device['device_uuid'],
      if (teacher is Map) ...[
        teacher['nom'],
        teacher['matricule'],
        teacher['tel'],
      ],
    ].any((value) => '${value ?? ''}'.toLowerCase().contains(query));
  }

  /// ListView (et non Center) pour garder le tirer-pour-rafraîchir actif.
  Widget _message(String message) {
    return ListView(
      children: [
        Padding(padding: const EdgeInsets.all(24), child: Text(message)),
      ],
    );
  }
}
