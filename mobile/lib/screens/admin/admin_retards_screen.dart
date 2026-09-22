import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';

DateTime _startOfMonth() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, 1);
}

DateTime _endOfMonth() {
  final now = DateTime.now();
  return DateTime(now.year, now.month + 1, 0);
}

String _isoDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

String _monthLabel(DateTime month) {
  final label = DateFormat('MMMM yyyy', 'fr').format(month);
  return label[0].toUpperCase() + label.substring(1);
}

const _bilanSections = [
  'Industrielle',
  'STT',
  'Administration',
  'Générale',
  'Anglophone',
  'Francophone',
];

List<DateTime> _lastTwelveMonths() {
  final now = DateTime.now();
  return List.generate(12, (index) => DateTime(now.year, now.month - index, 1));
}

List<dynamic> _asList(dynamic data) {
  if (data is List) return data;
  if (data is Map && data['data'] is List) return data['data'] as List<dynamic>;
  return const [];
}

/// Retards & bilans (§admin-mobile) — équivalent mobile de RetardsPage.jsx.
/// Filtre de période + tolérance, liste, et téléchargement/partage des
/// bilans PDF (cumulé ou par enseignant) — il n'y a pas de visualiseur PDF
/// dans l'app, donc on passe par [Share.shareXFiles] pour ouvrir/enregistrer.
class AdminRetardsScreen extends StatefulWidget {
  const AdminRetardsScreen({super.key});

  @override
  State<AdminRetardsScreen> createState() => _AdminRetardsScreenState();
}

class _AdminRetardsScreenState extends State<AdminRetardsScreen> {
  DateTime _debut = _startOfMonth();
  DateTime _fin = _endOfMonth();
  DateTime _selectedMonth = DateTime(
    DateTime.now().year,
    DateTime.now().month,
    1,
  );
  final _toleranceController = TextEditingController(text: '10');
  final _searchController = TextEditingController();

  late Future<List<dynamic>> _future;
  bool _savingTolerance = false;
  bool _downloadingCumule = false;
  bool _downloadingSection = false;
  String? _downloadingRowId;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _toleranceController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load() async {
    final results = await Future.wait([
      OfflineCache.instance.readThrough(
        'admin_retards_${_isoDate(_debut)}_${_isoDate(_fin)}',
        () => AdminApiClient.instance.get(
          '/retards',
          query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)},
        ),
      ),
      OfflineCache.instance.readThrough(
        'admin_retards_parametres',
        () => AdminApiClient.instance.get('/retards/parametres'),
      ),
    ]);

    final parametres = results[1];
    if (parametres is Map && parametres['tolerance_minutes'] != null) {
      _toleranceController.text = '${parametres['tolerance_minutes']}';
    }
    return _asList(results[0]);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  void _selectMonth(DateTime month) {
    setState(() {
      _selectedMonth = month;
      _debut = month;
      _fin = DateTime(month.year, month.month + 1, 0);
      _future = _load();
    });
  }

  Future<void> _saveTolerance() async {
    final minutes = int.tryParse(_toleranceController.text.trim());
    if (minutes == null) {
      _showMessage('Tolérance invalide.');
      return;
    }
    setState(() => _savingTolerance = true);
    try {
      await AdminApiClient.instance.put('/retards/parametres', {
        'tolerance_minutes': minutes,
      });
      if (mounted) _showMessage('Tolérance enregistrée.');
      await _refresh();
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        await OfflineCache.instance.overwrite('admin_retards_parametres', {
          'tolerance_minutes': minutes,
        });
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          method: 'PUT',
          path: '/retards/parametres',
          body: {'tolerance_minutes': minutes},
          label: 'Mettre à jour la tolérance des retards',
        );
        await SyncEngine.instance.notifyEnqueued();
        if (mounted) _showMessage('Tolérance enregistrée hors ligne.');
        return;
      }
      if (mounted) _showMessage(e.message);
    } finally {
      if (mounted) setState(() => _savingTolerance = false);
    }
  }

  Future<void> _sharePdf(List<int> bytes, String filename) async {
    final dir = await getTemporaryDirectory();
    final file = File('${dir.path}/$filename');
    await file.writeAsBytes(bytes, flush: true);
    await Share.shareXFiles([XFile(file.path)]);
  }

  Future<void> _downloadBilanCumule() async {
    setState(() => _downloadingCumule = true);
    try {
      final bytes = await AdminApiClient.instance.getBytes(
        '/retards/bilan-cumule',
        query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)},
      );
      await _sharePdf(
        bytes,
        'bilan-retards-${_isoDate(_debut)}-${_isoDate(_fin)}.pdf',
      );
    } on ApiException catch (e) {
      if (mounted) _showMessage(e.message);
    } finally {
      if (mounted) setState(() => _downloadingCumule = false);
    }
  }

  Future<void> _downloadBilanSection(String section) async {
    setState(() => _downloadingSection = true);
    try {
      final bytes = await AdminApiClient.instance.getBytes(
        '/retards/bilan-cumule',
        query: {
          'debut': _isoDate(_debut),
          'fin': _isoDate(_fin),
          'section': section,
        },
      );
      await _sharePdf(
        bytes,
        'bilan-retards-${section.toLowerCase()}-${_isoDate(_debut)}-${_isoDate(_fin)}.pdf',
      );
    } on ApiException catch (e) {
      if (mounted) _showMessage(e.message);
    } finally {
      if (mounted) setState(() => _downloadingSection = false);
    }
  }

  Future<void> _downloadBilanEnseignant(Map<String, dynamic> ligne) async {
    final id = '${ligne['enseignant_id']}';
    setState(() => _downloadingRowId = id);
    try {
      final bytes = await AdminApiClient.instance.getBytes(
        '/retards/bilan/$id',
        query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)},
      );
      final matricule = ligne['matricule'] ?? id;
      await _sharePdf(bytes, 'bilan-$matricule.pdf');
    } on ApiException catch (e) {
      if (mounted) _showMessage(e.message);
    } finally {
      if (mounted) setState(() => _downloadingRowId = null);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _FilterBar(
          selectedMonth: _selectedMonth,
          toleranceController: _toleranceController,
          searchController: _searchController,
          savingTolerance: _savingTolerance,
          downloadingCumule: _downloadingCumule,
          downloadingSection: _downloadingSection,
          onSelectMonth: _selectMonth,
          onSearchChanged: (_) => setState(() {}),
          onSaveTolerance: _saveTolerance,
          onDownloadCumule: _downloadBilanCumule,
          onDownloadSection: _downloadBilanSection,
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<List<dynamic>>(
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
                        child: Text('Erreur : ${snapshot.error}'),
                      ),
                    ],
                  );
                }
                final allLignes = snapshot.data ?? [];
                final query = _searchController.text.trim().toLowerCase();
                final lignes = query.isEmpty
                    ? allLignes
                    : allLignes.where((item) {
                        if (item is! Map) return false;
                        return ['nom', 'matricule', 'section'].any(
                          (key) => '${item[key] ?? ''}'.toLowerCase().contains(
                            query,
                          ),
                        );
                      }).toList();
                if (lignes.isEmpty) {
                  return ListView(
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          query.isEmpty
                              ? 'Aucun retard sur cette période.'
                              : 'Aucun enseignant trouvé pour « ${_searchController.text.trim()} ».',
                        ),
                      ),
                    ],
                  );
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  itemCount: lignes.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final l = lignes[i] as Map<String, dynamic>;
                    final id = '${l['enseignant_id']}';
                    final isDownloading = _downloadingRowId == id;
                    return Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.schedule_outlined,
                          color: AuditronColors.gold600,
                        ),
                        title: Text('${l['nom'] ?? '—'}'),
                        subtitle: Text(
                          '${l['matricule'] ?? '—'} · ${l['section'] ?? '—'}',
                        ),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              '${l['jours_retard'] ?? 0}j · ${l['minutes_retard_total'] ?? 0}min',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 12,
                                color: AuditronColors.ink500,
                              ),
                            ),
                            const SizedBox(width: 4),
                            isDownloading
                                ? const SizedBox(
                                    width: 36,
                                    height: 36,
                                    child: Padding(
                                      padding: EdgeInsets.all(8),
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    ),
                                  )
                                : IconButton(
                                    icon: const Icon(
                                      Icons.picture_as_pdf_outlined,
                                      size: 20,
                                    ),
                                    tooltip: 'Fiche PDF',
                                    onPressed: () =>
                                        _downloadBilanEnseignant(l),
                                  ),
                          ],
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

class _FilterBar extends StatelessWidget {
  final DateTime selectedMonth;
  final TextEditingController toleranceController;
  final TextEditingController searchController;
  final bool savingTolerance;
  final bool downloadingCumule;
  final bool downloadingSection;
  final ValueChanged<DateTime> onSelectMonth;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onSaveTolerance;
  final VoidCallback onDownloadCumule;
  final ValueChanged<String> onDownloadSection;

  const _FilterBar({
    required this.selectedMonth,
    required this.toleranceController,
    required this.searchController,
    required this.savingTolerance,
    required this.downloadingCumule,
    required this.downloadingSection,
    required this.onSelectMonth,
    required this.onSearchChanged,
    required this.onSaveTolerance,
    required this.onDownloadCumule,
    required this.onDownloadSection,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AuditronColors.ink100),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DropdownButtonFormField<DateTime>(
            initialValue: selectedMonth,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Période du bilan',
              prefixIcon: Icon(Icons.calendar_month_outlined),
            ),
            items: [
              for (final month in _lastTwelveMonths())
                DropdownMenuItem(value: month, child: Text(_monthLabel(month))),
            ],
            onChanged: (month) {
              if (month != null) onSelectMonth(month);
            },
          ),
          const SizedBox(height: 8),
          TextField(
            controller: searchController,
            onChanged: onSearchChanged,
            decoration: InputDecoration(
              labelText: 'Rechercher un enseignant',
              hintText: 'Nom, matricule ou section',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: searchController.text.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.clear),
                      tooltip: 'Effacer la recherche',
                      onPressed: () {
                        searchController.clear();
                        onSearchChanged('');
                      },
                    ),
            ),
          ),
          const SizedBox(height: 8),
          // Wrap plutôt que Row : sur un écran étroit (ex. iPhone SE, ~320px),
          // libellé + champ + bouton dépassent la largeur disponible — Wrap
          // renvoie l'excédent à la ligne suivante au lieu de déborder.
          Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              const Text(
                'Tolérance (min)',
                style: TextStyle(color: AuditronColors.ink700, fontSize: 13),
              ),
              SizedBox(
                width: 64,
                child: TextField(
                  controller: toleranceController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                  ),
                ),
              ),
              OutlinedButton(
                onPressed: savingTolerance ? null : onSaveTolerance,
                child: savingTolerance
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Enregistrer'),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: downloadingCumule ? null : onDownloadCumule,
              icon: downloadingCumule
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.picture_as_pdf_outlined, size: 18),
              label: const Text('Bilan PDF cumulé'),
            ),
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            initialValue: null,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Section du bilan',
              prefixIcon: Icon(Icons.groups_outlined),
            ),
            items: [
              for (final section in _bilanSections)
                DropdownMenuItem(value: section, child: Text(section)),
            ],
            onChanged: downloadingSection
                ? null
                : (section) {
                    if (section != null) onDownloadSection(section);
                  },
          ),
        ],
      ),
    );
  }
}
