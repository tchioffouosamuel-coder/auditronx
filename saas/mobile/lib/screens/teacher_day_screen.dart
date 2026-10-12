import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/presence.dart';
import '../services/api_client.dart';
import '../services/offline/offline_cache.dart';
import '../services/presence_repository.dart';
import '../theme.dart';
import '../utils/date_format_utils.dart';
import '../widgets/assiduite_mensuelle_card.dart';

class TeacherDayScreen extends StatefulWidget {
  final Future<void> Function() onScan;
  final VoidCallback onHistory;
  final VoidCallback onCourses;
  final VoidCallback onSchedule;

  const TeacherDayScreen({
    super.key,
    required this.onScan,
    required this.onHistory,
    required this.onCourses,
    required this.onSchedule,
  });

  @override
  State<TeacherDayScreen> createState() => _TeacherDayScreenState();
}

class _TeacherDayScreenState extends State<TeacherDayScreen> {
  late Future<List<PresenceEntry>> _presences;
  late Future<Map<String, dynamic>> _courses;
  int _revision = 0;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _presences = PresenceRepository().load();
    _courses = _loadCourses();
  }

  Future<Map<String, dynamic>> _loadCourses() async {
    final response = await OfflineCache.instance.readThrough(
      'teacher_courses_today',
      () => ApiClient.instance.get('/mes-cours-du-jour'),
    );
    return Map<String, dynamic>.from(response as Map);
  }

  Future<void> _refresh() async {
    setState(() {
      _reload();
      _revision++;
    });
    // Chaque panneau garde son propre etat d'erreur, sans masquer le scan.
    await Future.wait<void>([
      _presences.then<void>((_) {}, onError: (Object _) {}),
      _courses.then<void>((_) {}, onError: (Object _) {}),
    ]);
  }

  Future<void> _scan() async {
    await widget.onScan();
    if (mounted) await _refresh();
  }

  String _courseTime(dynamic value) {
    final raw = value?.toString();
    if (raw == null) return '—';
    try {
      return DateFormat(
        'HH:mm',
      ).format(DateFormat('HH:mm:ss').parseStrict(raw));
    } on FormatException {
      return formatTime(raw);
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          FutureBuilder<List<PresenceEntry>>(
            future: _presences,
            builder: (context, snapshot) {
              final today = DateTime.now().toIso8601String().substring(0, 10);
              PresenceEntry? presence;
              for (final entry in snapshot.data ?? <PresenceEntry>[]) {
                if (entry.date == today) {
                  presence = entry;
                  break;
                }
              }
              return _TodayPresence(
                presence: presence,
                loading: snapshot.connectionState != ConnectionState.done,
                unavailable: snapshot.hasError,
              );
            },
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: _scan,
            icon: const Icon(Icons.qr_code_scanner, size: 20),
            label: const Text('Scanner pour pointer'),
            style: FilledButton.styleFrom(
              backgroundColor: AuditronColors.orange500,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Mes cours du jour',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              IconButton(
                onPressed: widget.onSchedule,
                tooltip: 'Mon emploi du temps',
                icon: const Icon(Icons.calendar_month_outlined, size: 20),
              ),
            ],
          ),
          FutureBuilder<Map<String, dynamic>>(
            future: _courses,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: LinearProgressIndicator(minHeight: 2),
                );
              }
              final today = DateTime.now().toIso8601String().substring(0, 10);
              if (snapshot.hasError || snapshot.data?['date'] != today) {
                return _CourseMessage(
                  message: 'Cours du jour indisponibles.',
                  onRetry: _refresh,
                );
              }
              final courses =
                  (snapshot.data?['cours'] as List?)
                      ?.whereType<Map>()
                      .toList() ??
                  [];
              if (courses.isEmpty) {
                return const _CourseMessage(
                  message: 'Aucun cours prévu aujourd’hui.',
                );
              }
              return Column(
                children: [
                  for (final course in courses)
                    InkWell(
                      onTap: widget.onCourses,
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        decoration: const BoxDecoration(
                          border: Border(
                            bottom: BorderSide(color: AuditronColors.ink100),
                          ),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '${_courseTime(course['heure_debut'])} - ${_courseTime(course['heure_fin'])}',
                              style: const TextStyle(
                                color: AuditronColors.brand700,
                                fontSize: 12,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${course['discipline'] ?? 'Cours'}',
                              style: Theme.of(context).textTheme.titleSmall,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              '${course['classe'] ?? 'Classe non renseignée'}',
                              style: const TextStyle(
                                color: AuditronColors.ink500,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 8),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.history, color: AuditronColors.ink500),
            title: const Text(
              'Historique de mes présences',
              style: TextStyle(fontSize: 14),
            ),
            trailing: const Icon(Icons.chevron_right, size: 20),
            onTap: widget.onHistory,
          ),
          const SizedBox(height: 16),
          AssiduiteMensuelleCard(key: ValueKey(_revision)),
        ],
      ),
    );
  }
}

class _TodayPresence extends StatelessWidget {
  final PresenceEntry? presence;
  final bool loading;
  final bool unavailable;

  const _TodayPresence({
    required this.presence,
    required this.loading,
    required this.unavailable,
  });

  @override
  Widget build(BuildContext context) {
    final hasArrival = presence?.heureArrivee != null;
    final hasDeparture = presence?.heureDepart != null;
    final label = loading
        ? 'Chargement de la présence'
        : unavailable
        ? 'Présence indisponible'
        : hasDeparture
        ? 'Départ enregistré'
        : hasArrival
        ? 'Arrivée enregistrée'
        : 'Pas encore de pointage';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AuditronColors.brand700,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                hasArrival ? Icons.check_circle_outline : Icons.schedule,
                size: 18,
                color: Colors.white,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(color: Colors.white, fontSize: 13),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            formatTime(
              hasDeparture ? presence?.heureDepart : presence?.heureArrivee,
            ),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            hasDeparture
                ? 'Arrivée à ${formatTime(presence?.heureArrivee)}'
                : presence?.enRetard == true
                ? 'Retard de ${presence!.minutesRetard} min'
                : 'Pointage du jour',
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _CourseMessage extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const _CourseMessage({required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: AuditronColors.ink500,
                fontSize: 13,
              ),
            ),
          ),
          if (onRetry != null)
            IconButton(
              onPressed: onRetry,
              tooltip: 'Réessayer',
              icon: const Icon(Icons.refresh),
            ),
        ],
      ),
    );
  }
}
