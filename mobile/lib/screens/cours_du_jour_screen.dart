import 'package:flutter/material.dart';
import '../services/api_client.dart';

class CoursDuJourScreen extends StatefulWidget {
  const CoursDuJourScreen({super.key});

  @override
  State<CoursDuJourScreen> createState() => _CoursDuJourScreenState();
}

class _CoursDuJourScreenState extends State<CoursDuJourScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    return await ApiClient.instance.get('/mes-cours-du-jour')
        as Map<String, dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _toggle(
    Map<String, dynamic> cours,
    Map<String, dynamic> lecon,
  ) async {
    final faite = lecon['faite'] == true;
    setState(() => lecon['faite'] = !faite);

    try {
      await ApiClient.instance.post('/mes-cours-du-jour/lecon-toggle', {
        'emploi_du_temps_id': cours['emploi_du_temps_id'],
        'progression_lecon_id': lecon['id'],
        'date': DateTime.now().toIso8601String().substring(0, 10),
      });
    } catch (_) {
      if (mounted) setState(() => lecon['faite'] = faite);
      rethrow;
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<Map<String, dynamic>>(
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
                  child: Text('${snapshot.error}'),
                ),
              ],
            );
          }

          final data = snapshot.data!;
          if (data['present'] != true) {
            return ListView(
              children: const [
                Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    'Pointez votre présence pour déclarer les leçons du jour.',
                  ),
                ),
              ],
            );
          }

          final cours = (data['cours'] as List<dynamic>?) ?? [];
          if (cours.isEmpty) {
            return ListView(
              children: const [
                Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('Aucun cours prévu aujourd’hui.'),
                ),
              ],
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: cours.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final coursDuJour = Map<String, dynamic>.from(
                cours[index] as Map<String, dynamic>,
              );
              final lecons = (coursDuJour['lecons'] as List<dynamic>?) ?? [];
              return Card(
                child: Column(
                  children: [
                    ListTile(
                      title: Text(
                        '${coursDuJour['discipline']} - ${coursDuJour['classe']}',
                      ),
                      subtitle: Text(
                        '${coursDuJour['heure_debut']} - ${coursDuJour['heure_fin']}',
                      ),
                    ),
                    ...lecons.map((item) {
                      final lecon = item as Map<String, dynamic>;
                      return CheckboxListTile(
                        value: lecon['faite'] == true,
                        title: Text('${lecon['ordre']}. ${lecon['titre']}'),
                        subtitle: lecon['unite_apprentissage'] == null
                            ? null
                            : Text('${lecon['unite_apprentissage']}'),
                        onChanged: (_) async {
                          try {
                            await _toggle(coursDuJour, lecon);
                            if (mounted) setState(() {});
                          } catch (error) {
                            if (!context.mounted) return;
                            ScaffoldMessenger.of(
                              context,
                            ).showSnackBar(SnackBar(content: Text('$error')));
                          }
                        },
                      );
                    }),
                    if (lecons.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'Aucune progression importée pour cette classe et cette matière.',
                        ),
                      ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
