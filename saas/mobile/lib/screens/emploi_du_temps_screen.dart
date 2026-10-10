import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../services/offline/offline_cache.dart';
import '../theme.dart';

class EmploiDuTempsScreen extends StatefulWidget {
  final int enseignantId;

  const EmploiDuTempsScreen({super.key, required this.enseignantId});

  @override
  State<EmploiDuTempsScreen> createState() => _EmploiDuTempsScreenState();
}

class _EmploiDuTempsScreenState extends State<EmploiDuTempsScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await OfflineCache.instance.readThrough(
      'teacher_schedule_${widget.enseignantId}',
      () => ApiClient.instance.get(
        '/emplois',
        query: {'enseignant_id': widget.enseignantId.toString()},
      ),
    );
    final page = Map<String, dynamic>.from(response as Map);
    return (page['data'] as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((course) => Map<String, dynamic>.from(course))
        .toList();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mon emploi du temps')),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<Map<String, dynamic>>>(
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
                    child: Text(
                      'Impossible de charger l’emploi du temps.',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                ],
              );
            }

            final coursesByDay = <int, List<Map<String, dynamic>>>{};
            for (final course in snapshot.data ?? const []) {
              final day = (course['jour'] as num?)?.toInt();
              if (day == null || day < 1 || day > 7) continue;
              coursesByDay.putIfAbsent(day, () => []).add(course);
            }

            if (coursesByDay.isEmpty) {
              return ListView(
                children: const [
                  Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('Aucun cours planifié.'),
                  ),
                ],
              );
            }

            final today = DateTime.now().weekday;
            final days = coursesByDay.keys.toList()
              ..sort(
                (a, b) => ((a - today + 7) % 7).compareTo((b - today + 7) % 7),
              );

            return ListView(
              padding: const EdgeInsets.symmetric(vertical: 8),
              children: [
                for (final day in days)
                  ExpansionTile(
                    initiallyExpanded: day == today,
                    leading: Icon(
                      Icons.calendar_today_outlined,
                      color: day == today ? AuditronColors.brand700 : null,
                    ),
                    title: Text(
                      _dayName(day),
                      style: TextStyle(
                        fontWeight: day == today
                            ? FontWeight.w700
                            : FontWeight.w500,
                        color: day == today ? AuditronColors.brand700 : null,
                      ),
                    ),
                    subtitle: day == today ? const Text("Aujourd'hui") : null,
                    children: [
                      for (final course
                          in (coursesByDay[day]!..sort(
                            (a, b) => '${a['heure_debut']}'.compareTo(
                              '${b['heure_debut']}',
                            ),
                          )))
                        ListTile(
                          leading: const Icon(Icons.schedule_outlined),
                          title: Text(
                            '${course['heure_debut'] ?? '--:--'} - ${course['heure_fin'] ?? '--:--'}',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          subtitle: Text(
                            '${course['classe']?['nom'] ?? 'Classe'} - ${course['discipline']?['nom'] ?? 'Matière'}${course['salle'] == null ? '' : ' - ${course['salle']}'}',
                          ),
                        ),
                    ],
                  ),
              ],
            );
          },
        ),
      ),
    );
  }

  static String _dayName(int day) =>
      const {
        1: 'Lundi',
        2: 'Mardi',
        3: 'Mercredi',
        4: 'Jeudi',
        5: 'Vendredi',
        6: 'Samedi',
        7: 'Dimanche',
      }[day] ??
      'Jour $day';
}
