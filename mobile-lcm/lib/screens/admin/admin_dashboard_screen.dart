import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';

/// Tableau de bord admin (§admin-mobile) — équivalent mobile de
/// DashboardPage.jsx : KPIs + sélecteur de date + classement par section.
class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key});

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
            (_) => _KpiCard(
              label: 'Effectif',
              value: '${d['effectif']}',
              icon: Icons.groups,
              color: AuditronColors.brand700,
            ),
            (_) => _KpiCard(
              label: 'Présents',
              value: '${d['presents']}',
              icon: Icons.check_circle,
              color: AuditronColors.brand600,
            ),
            (_) => _KpiCard(
              label: 'Absents',
              value: '${d['absents']}',
              icon: Icons.cancel,
              color: Colors.red,
            ),
            (_) => _KpiCard(
              label: 'En retard',
              value: '${d['retardataires']}',
              icon: Icons.schedule,
              color: AuditronColors.gold600,
            ),
          ];
          final sections =
              (d['classement_par_section'] as List?)
                  ?.cast<Map<String, dynamic>>() ??
              const [];
          final scannes = _dashboardPeople(d['scannes']);
          final absents = _dashboardPeople(d['absents_liste']);
          final retardataires = _dashboardPeople(d['retardataires_liste']);

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Situation du ${d['date'] ?? _dateStr}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  TextButton.icon(
                    onPressed: _pickDate,
                    icon: const Icon(Icons.calendar_today, size: 16),
                    label: const Text('Changer'),
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
                childAspectRatio: 1.15,
                children: cards.map((c) => c(null)).toList(),
              ),
              const SizedBox(height: 20),
              Text(
                "Taux d'assiduité par section",
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
                SizedBox(
                  height: 220,
                  child: _SectionBarChart(sections: sections),
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
                color: AuditronColors.gold600,
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
    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 8),
                Text(
                  '$title (${people.length})',
                  style: Theme.of(context).textTheme.titleMedium,
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
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _KpiCard({
    required this.label,
    required this.value,
    required this.icon,
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
            Icon(icon, color: color, size: 24),
            const SizedBox(height: 4),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                style: const TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: AuditronColors.ink500,
                fontSize: 13,
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

/// Graphique "taux d'assiduité par section" (§admin-mobile) — équivalent
/// mobile du BarChart Recharts de DashboardPage.jsx.
class _SectionBarChart extends StatelessWidget {
  final List<Map<String, dynamic>> sections;

  const _SectionBarChart({required this.sections});

  @override
  Widget build(BuildContext context) {
    return BarChart(
      BarChartData(
        maxY: 100,
        gridData: const FlGridData(show: true, drawVerticalLine: false),
        borderData: FlBorderData(show: false),
        barTouchData: BarTouchData(
          touchTooltipData: BarTouchTooltipData(
            getTooltipItem: (group, groupIndex, rod, rodIndex) =>
                BarTooltipItem(
                  '${rod.toY.toStringAsFixed(0)}%',
                  const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                  ),
                ),
          ),
        ),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 32,
              interval: 25,
            ),
          ),
          rightTitles: const AxisTitles(
            sideTitles: SideTitles(showTitles: false),
          ),
          topTitles: const AxisTitles(
            sideTitles: SideTitles(showTitles: false),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 36,
              getTitlesWidget: (value, meta) {
                final i = value.toInt();
                if (i < 0 || i >= sections.length)
                  return const SizedBox.shrink();
                final label = sections[i]['section']?.toString() ?? '—';
                return Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    label.length > 8 ? '${label.substring(0, 8)}…' : label,
                    style: const TextStyle(
                      fontSize: 10,
                      color: AuditronColors.ink500,
                    ),
                  ),
                );
              },
            ),
          ),
        ),
        barGroups: [
          for (var i = 0; i < sections.length; i++)
            BarChartGroupData(
              x: i,
              barRods: [
                BarChartRodData(
                  toY: (num.tryParse('${sections[i]['taux_assiduite']}') ?? 0)
                      .toDouble(),
                  color: AuditronColors.brand700,
                  width: 18,
                  borderRadius: BorderRadius.circular(4),
                ),
              ],
            ),
        ],
      ),
    );
  }
}
