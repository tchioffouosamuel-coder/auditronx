import 'package:flutter/material.dart';
import '../models/presence.dart';
import '../services/presence_repository.dart';

/// Historique personnel (§4.1) : consultation de ses propres présences/retards
/// du mois en cours.
class HistoriqueScreen extends StatefulWidget {
  const HistoriqueScreen({super.key});

  @override
  State<HistoriqueScreen> createState() => _HistoriqueScreenState();
}

class _HistoriqueScreenState extends State<HistoriqueScreen> {
  final _repository = PresenceRepository();
  late Future<List<PresenceEntry>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<PresenceEntry>> _load() async {
    return _repository.load();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<PresenceEntry>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      Text('${snapshot.error}', textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: _refresh,
                        child: const Text('Réessayer'),
                      ),
                    ],
                  ),
                ),
              ],
            );
          }

          final entries = snapshot.data!;
          if (entries.isEmpty) {
            return ListView(
              children: const [
                Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('Aucune présence ce mois-ci.'),
                ),
              ],
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: entries.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final e = entries[i];
              return Card(
                child: ListTile(
                  title: Text(e.date),
                  subtitle: Text(
                    'Arrivée : ${e.heureArrivee ?? '—'}   Départ : ${e.heureDepart ?? '—'}',
                  ),
                  trailing: e.enRetard
                      ? Chip(
                          label: Text('Retard ${e.minutesRetard} min'),
                          backgroundColor: Colors.orange.shade100,
                        )
                      : const Icon(Icons.check_circle, color: Colors.green),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
