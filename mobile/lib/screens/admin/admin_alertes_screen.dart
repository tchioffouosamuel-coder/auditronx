import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';

/// Alertes & signalements (§admin-mobile) — équivalent mobile allégé
/// d'AlertesPage.jsx et SignalementsPage.jsx (lecture seule pour l'instant ;
/// la saisie de signalements reste web-only, cf. périmètre v1).
class AdminAlertesScreen extends StatelessWidget {
  const AdminAlertesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          const TabBar(tabs: [Tab(text: 'Alertes absences'), Tab(text: 'Signalements')]),
          const Expanded(
            child: TabBarView(
              children: [_AlertesList(), _SignalementsList()],
            ),
          ),
        ],
      ),
    );
  }
}

class _AlertesList extends StatefulWidget {
  const _AlertesList();

  @override
  State<_AlertesList> createState() => _AlertesListState();
}

class _AlertesListState extends State<_AlertesList> {
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
                  subtitle: Text('Envoyée le ${a['sent_at']}'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _SignalementsList extends StatefulWidget {
  const _SignalementsList();

  @override
  State<_SignalementsList> createState() => _SignalementsListState();
}

class _SignalementsListState extends State<_SignalementsList> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_signalements',
      () => AdminApiClient.instance.get('/signalements'),
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
          final signalements = snapshot.data ?? [];
          if (signalements.isEmpty) {
            return ListView(children: const [Padding(padding: EdgeInsets.all(24), child: Text('Aucun signalement.'))]);
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: signalements.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final s = signalements[i] as Map<String, dynamic>;
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.flag, color: Colors.red),
                  title: Text(s['enseignant']?['nom'] ?? '—'),
                  subtitle: Text('${s['motif']} · ${s['date']}'),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
