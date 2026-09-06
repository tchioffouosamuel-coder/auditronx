import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';

/// Tableau de bord admin (§admin-mobile) — équivalent mobile allégé de
/// DashboardPage.jsx : KPIs du jour, sans le classement détaillé par section
/// (réservé au dashboard web pour l'instant, cf. périmètre v1).
class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key});

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_dashboard',
      () => AdminApiClient.instance.get('/dashboard'),
    );
    return data as Map<String, dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
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
            (_) => _KpiCard(label: 'Effectif', value: '${d['effectif']}', icon: Icons.groups, color: AuditronColors.brand700),
            (_) => _KpiCard(label: 'Présents', value: '${d['presents']}', icon: Icons.check_circle, color: AuditronColors.brand600),
            (_) => _KpiCard(label: 'Absents', value: '${d['absents']}', icon: Icons.cancel, color: Colors.red),
            (_) => _KpiCard(label: 'En retard', value: '${d['retardataires']}', icon: Icons.schedule, color: AuditronColors.gold600),
          ];

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text('Aujourd\'hui — ${d['date']}', style: Theme.of(context).textTheme.titleMedium),
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
            ],
          );
        },
      ),
    );
  }
}

class _KpiCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _KpiCard({required this.label, required this.value, required this.icon, required this.color});

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
              child: Text(value, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900)),
            ),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: AuditronColors.ink500, fontSize: 13),
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
