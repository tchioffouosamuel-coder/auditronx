import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

class PlateformeDashboardScreen extends StatefulWidget {
  const PlateformeDashboardScreen({super.key});

  @override
  State<PlateformeDashboardScreen> createState() =>
      _PlateformeDashboardScreenState();
}

class _PlateformeDashboardScreenState extends State<PlateformeDashboardScreen> {
  Map<String, dynamic>? _data;
  String? _error;

  Future<void> _load() async {
    try {
      final result =
          await PlateformeApiClient.instance.get('/tableau-de-bord')
              as Map<String, dynamic>;
      if (mounted)
        setState(() {
          _data = result;
          _error = null;
        });
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = 'Chargement impossible : $error');
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    if (data == null) {
      return Center(
        child: _error == null
            ? const CircularProgressIndicator()
            : _ErrorMessage(message: _error!, onRetry: _load),
      );
    }

    final etablissements = data['etablissements'] as Map? ?? const {};
    final abonnements = data['abonnements'] as Map? ?? const {};
    final facturation = data['facturation'] as Map? ?? const {};
    final offres = data['offres'] as Map? ?? const {};
    final encaisse = facturation['encaisse_mois'] as Map? ?? const {};
    final historique = (data['historique'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    final usage = (data['usage'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    final encaissement = encaisse.entries
        .map((entry) => '${_money(entry.value)} ${entry.key}')
        .join(' · ');

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_error != null) _ErrorBanner(message: _error!),
          Text(
            'Vue d’ensemble du parc',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MetricCard(
                label: 'Établissements',
                value: '${etablissements['total'] ?? 0}',
                detail:
                    '${(etablissements['par_statut'] as Map?)?['actif'] ?? 0} actifs',
              ),
              _MetricCard(
                label: 'Abonnements actifs',
                value: '${abonnements['actifs'] ?? 0}',
                detail:
                    '${abonnements['echeance_30j'] ?? 0} échéance(s) sous 30 j',
              ),
              _MetricCard(
                label: 'Factures en retard',
                value: '${facturation['impayees'] ?? 0}',
              ),
              _MetricCard(
                label: 'Offres actives',
                value: '${offres['actives'] ?? 0}',
              ),
              _MetricCard(
                label: 'Encaissé ce mois',
                value: encaissement.isEmpty ? '0' : encaissement,
              ),
            ],
          ),
          const SizedBox(height: 20),
          _SectionCard(
            title: 'Activité historique — 6 derniers mois',
            child: historique.isEmpty
                ? const Text('Aucun historique disponible.')
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Ouvertures et factures encaissées',
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      const SizedBox(height: 16),
                      SizedBox(
                        height: 190,
                        child: BarChart(
                          BarChartData(
                            barGroups: [
                              for (var i = 0; i < historique.length; i++)
                                BarChartGroupData(
                                  x: i,
                                  barsSpace: 4,
                                  barRods: [
                                    BarChartRodData(
                                      toY: _number(
                                        historique[i]['etablissements_ouverts'],
                                      ),
                                      color: AuditronColors.brand700,
                                      width: 8,
                                      borderRadius: BorderRadius.circular(3),
                                    ),
                                    BarChartRodData(
                                      toY: _number(
                                        historique[i]['factures_payees'],
                                      ),
                                      color: AuditronColors.gold600,
                                      width: 8,
                                      borderRadius: BorderRadius.circular(3),
                                    ),
                                  ],
                                ),
                            ],
                            titlesData: FlTitlesData(
                              topTitles: const AxisTitles(
                                sideTitles: SideTitles(showTitles: false),
                              ),
                              rightTitles: const AxisTitles(
                                sideTitles: SideTitles(showTitles: false),
                              ),
                              bottomTitles: AxisTitles(
                                sideTitles: SideTitles(
                                  showTitles: true,
                                  getTitlesWidget: (value, meta) {
                                    final index = value.toInt();
                                    if (index < 0 ||
                                        index >= historique.length) {
                                      return const SizedBox.shrink();
                                    }
                                    final month =
                                        historique[index]['mois']?.toString() ??
                                        '';
                                    return Padding(
                                      padding: const EdgeInsets.only(top: 6),
                                      child: Text(
                                        month.split('/').first,
                                        style: const TextStyle(fontSize: 9),
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ),
                            gridData: const FlGridData(show: true),
                            borderData: FlBorderData(show: false),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Wrap(
                        spacing: 16,
                        children: [
                          _Legend(
                            color: AuditronColors.brand700,
                            label: 'Ouvertures',
                          ),
                          _Legend(
                            color: AuditronColors.gold600,
                            label: 'Paiements',
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      Text(
                        'Pointages remontés',
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      SizedBox(
                        height: 170,
                        child: LineChart(
                          LineChartData(
                            lineBarsData: [
                              LineChartBarData(
                                spots: [
                                  for (var i = 0; i < historique.length; i++)
                                    FlSpot(
                                      i.toDouble(),
                                      _number(historique[i]['pointages']),
                                    ),
                                ],
                                isCurved: true,
                                color: AuditronColors.brand700,
                                dotData: const FlDotData(show: false),
                                belowBarData: BarAreaData(
                                  show: true,
                                  color: AuditronColors.brand100,
                                ),
                              ),
                            ],
                            titlesData: FlTitlesData(
                              topTitles: const AxisTitles(
                                sideTitles: SideTitles(showTitles: false),
                              ),
                              rightTitles: const AxisTitles(
                                sideTitles: SideTitles(showTitles: false),
                              ),
                              bottomTitles: AxisTitles(
                                sideTitles: SideTitles(
                                  showTitles: true,
                                  getTitlesWidget: (value, meta) {
                                    final index = value.toInt();
                                    if (index < 0 ||
                                        index >= historique.length) {
                                      return const SizedBox.shrink();
                                    }
                                    return Text(
                                      (historique[index]['mois']?.toString() ??
                                              '')
                                          .split('/')
                                          .first,
                                      style: const TextStyle(fontSize: 9),
                                    );
                                  },
                                ),
                              ),
                            ),
                            gridData: const FlGridData(show: true),
                            borderData: FlBorderData(show: false),
                          ),
                        ),
                      ),
                      if (historique.any(
                        (item) => _number(item['bases_injoignables']) > 0,
                      ))
                        const Text(
                          'Certaines bases sont injoignables : les totaux sont incomplets.',
                          style: TextStyle(
                            color: Colors.deepOrange,
                            fontSize: 12,
                          ),
                        ),
                    ],
                  ),
          ),
          const SizedBox(height: 16),
          _SectionCard(
            title: 'Usage par établissement',
            child: usage.isEmpty
                ? const Text('Aucune donnée d’usage disponible.')
                : Column(
                    children: [
                      for (final item in usage)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(item['nom']?.toString() ?? '—'),
                          subtitle: Text(
                            '${item['code'] ?? ''} · ${item['statut'] ?? ''}',
                          ),
                          isThreeLine: true,
                          trailing: item['injoignable'] == true
                              ? const Icon(
                                  Icons.cloud_off_outlined,
                                  color: Colors.deepOrange,
                                )
                              : Text('${item['personnel'] ?? '—'} pers.'),
                          subtitleTextStyle: const TextStyle(
                            color: AuditronColors.ink500,
                            height: 1.6,
                          ),
                          leading: const Icon(Icons.apartment_outlined),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value, this.detail});

  final String label;
  final String value;
  final String? detail;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: (MediaQuery.sizeOf(context).width - 42) / 2,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 11,
                color: AuditronColors.ink500,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              value,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 19,
                fontWeight: FontWeight.w800,
                color: AuditronColors.brand900,
              ),
            ),
            if (detail != null)
              Text(detail!, style: const TextStyle(fontSize: 10)),
          ],
        ),
      ),
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 14),
          child,
        ],
      ),
    ),
  );
}

class _Legend extends StatelessWidget {
  const _Legend({required this.color, required this.label});
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(width: 9, height: 9, color: color),
      const SizedBox(width: 5),
      Text(label, style: const TextStyle(fontSize: 11)),
    ],
  );
}

class _ErrorMessage extends StatelessWidget {
  const _ErrorMessage({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      Text(message, textAlign: TextAlign.center),
      TextButton(onPressed: onRetry, child: const Text('Réessayer')),
    ],
  );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(message, style: const TextStyle(color: Colors.red)),
  );
}

double _number(dynamic value) => value is num
    ? value.toDouble()
    : double.tryParse(value?.toString() ?? '') ?? 0;

String _money(dynamic value) => _number(value)
    .toStringAsFixed(0)
    .replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (match) => '${match[1]} ');
