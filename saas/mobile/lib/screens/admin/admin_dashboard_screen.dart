import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';

/// Tableau de bord admin (§admin-mobile) — équivalent mobile de
/// DashboardPage.jsx : KPIs + sélecteur de date + classement par section.
class AdminDashboardScreen extends StatefulWidget {
  final VoidCallback? onSignalements;
  final VoidCallback? onAssiduite;

  const AdminDashboardScreen({
    super.key,
    this.onSignalements,
    this.onAssiduite,
  });

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  late Future<Map<String, dynamic>> _future;
  DateTime _date = DateTime.now();

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  String get _dateStr => _date.toIso8601String().substring(0, 10);

  Future<Map<String, dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_dashboard_$_dateStr',
      () =>
          AdminApiClient.instance.get('/dashboard', query: {'date': _dateStr}),
    );
    return data as Map<String, dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(_date.year - 1),
      lastDate: DateTime(_date.year + 1),
    );
    if (picked != null) {
      setState(() {
        _date = picked;
        _future = _load();
      });
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
            return ListView(children: [_ErrorTile(error: snapshot.error)]);
          }

          final d = snapshot.data!;
          final cards = [
            _KpiCard(
              label: 'Effectif total',
              value: '${d['effectif']}',
              color: AuditronColors.ink700,
            ),
            _KpiCard(
              label: 'Présents',
              value: '${d['presents']}',
              color: AuditronColors.brand600,
            ),
            _KpiCard(
              label: 'Absents',
              value: '${d['absents']}',
              color: Colors.red,
            ),
            _KpiCard(
              label: 'Retardataires',
              value: '${d['retardataires']}',
              color: AuditronColors.orange700,
            ),
          ];
          final sections =
              (d['classement_par_section'] as List?)
                  ?.cast<Map<String, dynamic>>() ??
              const [];
          final scannes = _dashboardPeople(d['scannes']);
          final signales = _dashboardPeople(d['signales_liste']);
          final absents = _dashboardPeople(d['absents_liste']);
          final retardataires = _dashboardPeople(d['retardataires_liste']);

          return ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  // La date et le bouton se partagent la ligne : le texte doit
                  // céder, pas déborder.
                  Expanded(
                    child: Text(
                      'Situation du ${DateFormat('d MMMM yyyy', 'fr').format(DateTime.tryParse('${d['date']}') ?? _date)}',
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                  ),
                  IconButton(
                    onPressed: _pickDate,
                    icon: const Icon(Icons.calendar_today, size: 16),
                    tooltip: 'Choisir une date',
                  ),
                ],
              ),
              const SizedBox(height: 12),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                mainAxisExtent: MediaQuery.textScalerOf(context).scale(104),
                children: cards,
              ),
              const SizedBox(height: 20),
              Text(
                'Assiduité par section',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 12),
              if (sections.isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Text(
                    'Aucune donnée pour cette date.',
                    style: TextStyle(color: AuditronColors.ink500),
                  ),
                )
              else
                _SectionAttendance(sections: sections),
              const SizedBox(height: 20),
              const Divider(height: 1),
              if (widget.onSignalements != null)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(
                    Icons.flag_outlined,
                    color: AuditronColors.ink500,
                  ),
                  title: const Text('Signalements et justificatifs'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: widget.onSignalements,
                ),
              if (widget.onAssiduite != null)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(
                    Icons.insights_outlined,
                    color: AuditronColors.ink500,
                  ),
                  title: const Text('Bilans d’assiduité et de retards'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: widget.onAssiduite,
                ),
              const SizedBox(height: 20),
              _DashboardPeopleSection(
                title: 'Déjà scannés',
                people: scannes,
                icon: Icons.check_circle,
                color: AuditronColors.brand600,
              ),
              _DashboardPeopleSection(
                title: 'Absents selon l\'emploi du temps',
                people: absents,
                icon: Icons.cancel,
                color: Colors.red,
              ),
              _DashboardPeopleSection(
                title: 'Retardataires',
                people: retardataires,
                icon: Icons.schedule,
                color: AuditronColors.orange700,
              ),
              _DashboardPeopleSection(
                title: 'Signalés',
                people: signales,
                icon: Icons.flag_outlined,
                color: AuditronColors.ink500,
              ),
            ],
          );
        },
      ),
    );
  }
}

List<Map<String, dynamic>> _dashboardPeople(dynamic value) =>
    (value as List?)
        ?.whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList() ??
    const [];

class _DashboardPeopleSection extends StatelessWidget {
  final String title;
  final List<Map<String, dynamic>> people;
  final IconData icon;
  final Color color;

  const _DashboardPeopleSection({
    required this.title,
    required this.people,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 20),
              const SizedBox(width: 8),
              // « Absents selon l'emploi du temps (12) » ne tient pas à côté
              // de l'icône sur un écran étroit : sans Expanded, le Row
              // débordait au lieu de tronquer.
              Expanded(
                child: Text(
                  '$title (${people.length})',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          ),
          if (people.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Text(
                'Aucune personne.',
                style: TextStyle(color: AuditronColors.ink500),
              ),
            )
          else
            ...people.map((person) {
              final cours =
                  (person['cours'] as List?)
                      ?.whereType<Map>()
                      .map(
                        (c) =>
                            '${c['classe'] ?? '—'} ${c['heure_debut'] ?? ''}-${c['heure_fin'] ?? ''}',
                      )
                      .join(' · ') ??
                  '';
              final horaires = [
                if (person['est_signale'] == true)
                  'Signalé : ${person['type_signalement'] ?? 'Justificatif'}',
                if (person['heure_arrivee'] != null)
                  'Arrivée ${person['heure_arrivee']}',
                if (person['heure_depart'] != null)
                  'Départ ${person['heure_depart']}',
                if (person['minutes_retard'] != null &&
                    person['minutes_retard'] != 0)
                  '${person['minutes_retard']} min de retard',
              ].join(' · ');
              return ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: Icon(icon, color: color, size: 18),
                title: Text(person['nom']?.toString() ?? '—'),
                subtitle: Text(
                  [person['matricule'], horaires, cours]
                      .where((v) => v != null && v.toString().isNotEmpty)
                      .join(' · '),
                  overflow: TextOverflow.ellipsis,
                  maxLines: 2,
                ),
              );
            }),
        ],
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _KpiCard({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                label,
                style: const TextStyle(
                  color: AuditronColors.ink500,
                  fontSize: 12,
                ),
              ),
            ),
            const SizedBox(height: 8),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                style: TextStyle(
                  color: color,
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorTile extends StatelessWidget {
  final Object? error;

  const _ErrorTile({required this.error});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Text('$error', style: const TextStyle(color: Colors.red)),
    );
  }
}

class _SectionAttendance extends StatelessWidget {
  final List<Map<String, dynamic>> sections;

  const _SectionAttendance({required this.sections});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (final section in sections)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Builder(
              builder: (context) {
                final rate = (num.tryParse('${section['taux_assiduite']}') ?? 0)
                    .toDouble();
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            '${section['section'] ?? 'Sans section'}',
                            style: const TextStyle(
                              fontSize: 12,
                              color: AuditronColors.ink500,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '${rate.toStringAsFixed(rate % 1 == 0 ? 0 : 1)} %',
                          style: const TextStyle(
                            fontSize: 12,
                            color: AuditronColors.ink500,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    LinearProgressIndicator(
                      value: (rate / 100).clamp(0, 1),
                      minHeight: 8,
                      borderRadius: BorderRadius.circular(4),
                      color: AuditronColors.brand500,
                      backgroundColor: AuditronColors.ink100,
                      semanticsLabel: '${section['section'] ?? 'Sans section'}',
                    ),
                  ],
                );
              },
            ),
          ),
      ],
    );
  }
}
