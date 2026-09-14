import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';

/// Alertes d'absence (§admin-mobile) — équivalent mobile d'AlertesPage.jsx.
/// Les signalements ont désormais leur propre écran CRUD dédié
/// (voir AdminSignalementsScreen, groupe "Présence").
class AdminAlertesScreen extends StatefulWidget {
  const AdminAlertesScreen({super.key});

  @override
  State<AdminAlertesScreen> createState() => _AdminAlertesScreenState();
}

class _AdminAlertesScreenState extends State<AdminAlertesScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_alertes',
      () => AdminApiClient.instance.get('/absences/alertes'),
    );
    return (data as Map<String, dynamic>)['data'] as List<dynamic>;
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async {
        setState(() => _future = _load());
        await _future;
      },
      child: FutureBuilder<List<dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          final alertes = snapshot.data ?? [];
          if (alertes.isEmpty) {
            return ListView(children: const [Padding(padding: EdgeInsets.all(24), child: Text('Aucune alerte.'))]);
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: alertes.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final a = alertes[i] as Map<String, dynamic>;
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.warning_amber, color: Colors.orange),
                  title: Text(a['enseignant']?['nom'] ?? '—'),
                  subtitle: Text('Envoyée le ${a['sent_at']}${a['canal'] != null ? ' · ${a['canal']}' : ''}'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
