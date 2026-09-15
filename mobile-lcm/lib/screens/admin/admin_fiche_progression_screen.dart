import 'package:flutter/material.dart';

import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';

/// Fiche de progression (§admin-mobile) — équivalent mobile de
/// `FicheProgressionPage.jsx` : onglet "Fiche" (rapport calculé, lecture
/// seule) + onglet "Programmes officiels" (CRUD via [AdminCrudScreen]).
class AdminFicheProgressionScreen extends StatelessWidget {
  const AdminFicheProgressionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Text('Fiche de progression', style: Theme.of(context).textTheme.titleLarge),
          ),
          TabBar(
            labelColor: AuditronColors.brand700,
            unselectedLabelColor: AuditronColors.ink500,
            indicatorColor: AuditronColors.brand700,
            tabs: const [
              Tab(text: 'Fiche de progression'),
              Tab(text: 'Programmes officiels'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                const _FicheTab(),
                AdminCrudScreen(
                  title: '',
                  resourcePath: '/programmes',
                  cacheKey: 'admin_programmes',
                  icon: Icons.auto_stories,
                  headerActions: (context, refresh) => [
                    SpreadsheetActionsBar(entity: 'progressions', onImported: refresh),
                  ],
                  fields: [
                    AdminFieldSpec(
                      key: 'classe_id',
                      label: 'Classe',
                      type: AdminFieldType.select,
                      required: true,
                      optionsEndpoint: '/classes',
                    ),
                    AdminFieldSpec(
                      key: 'discipline_id',
                      label: 'Discipline',
                      type: AdminFieldType.select,
                      required: true,
                      optionsEndpoint: '/disciplines',
                    ),
                    const AdminFieldSpec(
                      key: 'annee_scolaire',
                      label: 'Année scolaire',
                      type: AdminFieldType.text,
                      required: true,
                      helperText: 'Format "2026-2027"',
                    ),
                    const AdminFieldSpec(
                      key: 'nb_seances_prevues',
                      label: 'Séances prévues',
                      type: AdminFieldType.number,
                      required: true,
                    ),
                  ],
                  itemTitle: (item) {
                    final classe = item['classe'];
                    final discipline = item['discipline'];
                    final classeLabel = (classe is Map ? classe['nom'] : null) ?? item['classe_id'];
                    final disciplineLabel = (discipline is Map ? discipline['nom'] : null) ?? item['discipline_id'];
                    return '$classeLabel — $disciplineLabel';
                  },
                  itemSubtitle: (item) => '${item['annee_scolaire']} · ${item['nb_seances_prevues']} séances prévues',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FicheTab extends StatefulWidget {
  const _FicheTab();

  @override
  State<_FicheTab> createState() => _FicheTabState();
}

class _FicheTabState extends State<_FicheTab> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_fiche_progression',
      () => AdminApiClient.instance.get('/fiche-progression'),
    );
    if (data is List) return data;
    if (data is Map && data['data'] is List) return data['data'] as List<dynamic>;
    return const [];
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              children: [Padding(padding: const EdgeInsets.all(24), child: Text('Erreur : ${snapshot.error}'))],
            );
          }

          final items = snapshot.data ?? [];
          if (items.isEmpty) {
            return ListView(
              children: const [Padding(padding: EdgeInsets.all(24), child: Text('Aucune donnée.'))],
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final item = items[i] as Map<String, dynamic>;
              final classe = item['classe'];
              final discipline = item['discipline'];
              final classeLabel = classe is Map ? classe['nom'] : null;
              final disciplineLabel = discipline is Map ? discipline['nom'] : null;
              final enRetard = item['en_retard'] == true;

              return Card(
                child: ListTile(
                  title: Text('${classeLabel ?? '—'} — ${disciplineLabel ?? '—'}'),
                  subtitle: Text(
                    '${item['annee_scolaire'] ?? '—'} · ${item['nb_seances_realisees'] ?? 0}/${item['nb_seances_prevues'] ?? 0} séances',
                  ),
                  trailing: Text(
                    '${item['taux_avancement'] ?? 0}%',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: enRetard ? Colors.red.shade600 : AuditronColors.brand600,
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
