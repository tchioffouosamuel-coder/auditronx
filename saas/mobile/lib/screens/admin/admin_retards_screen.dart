import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/admin_select_field.dart';

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

  bool get _busy =>
      _savingTolerance ||
      _downloadingCumule ||
      _downloadingSection ||
      _downloadingRowId != null;

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
    if (!mounted) return;
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

  Future<String> _saveTolerance() async {
    final minutes = int.tryParse(_toleranceController.text.trim());
    if (minutes == null || minutes < 0 || minutes > 120) {
      throw ApiException(
        'La tolérance doit être comprise entre 0 et 120 minutes.',
        422,
      );
    }
    setState(() => _savingTolerance = true);
    try {
      await AdminApiClient.instance.put('/retards/parametres', {
        'tolerance_minutes': minutes,
      });
      await _refresh();
      return 'Tolérance enregistrée.';
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
        return 'Tolérance enregistrée hors ligne.';
      }
      rethrow;
    } finally {
      if (mounted) setState(() => _savingTolerance = false);
    }
  }

  Future<void> _sharePdf(List<int> bytes, String filename) async {
    if (!mounted) return;
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
    } finally {
      if (mounted) setState(() => _downloadingSection = false);
    }
  }

  Future<void> _downloadBilanEnseignant(Map<String, dynamic> ligne) async {
    if (_busy) return;
    FocusScope.of(context).unfocus();
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
    } on FileSystemException {
      if (mounted) {
        _showMessage('Impossible d’enregistrer la fiche PDF sur ce téléphone.');
      }
    } on PlatformException {
      if (mounted) {
        _showMessage('Impossible d’ouvrir le partage de la fiche PDF.');
      }
    } finally {
      if (mounted) setState(() => _downloadingRowId = null);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _showBilans() => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    backgroundColor: Colors.white,
    builder: (_) => _BilanSheet(
      selectedMonth: _selectedMonth,
      toleranceController: _toleranceController,
      onSelectMonth: _selectMonth,
      onSaveTolerance: _saveTolerance,
      onDownloadCumule: _downloadBilanCumule,
      onDownloadSection: _downloadBilanSection,
    ),
  );

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: CustomScrollView(
        keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    _monthLabel(_selectedMonth),
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  const SizedBox(height: 8),
                  FilledButton.icon(
                    onPressed: _busy ? null : _showBilans,
                    icon: const Icon(Icons.download_outlined),
                    label: const Text('Bilans PDF'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _searchController,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      labelText: 'Rechercher un enseignant',
                      hintText: 'Nom, matricule ou section',
                      prefixIcon: const Icon(Icons.search),
                      suffixIcon: _searchController.text.isEmpty
                          ? null
                          : IconButton(
                              icon: const Icon(Icons.clear),
                              tooltip: 'Effacer la recherche',
                              onPressed: () {
                                _searchController.clear();
                                setState(() {});
                              },
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          FutureBuilder<List<dynamic>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const SliverFillRemaining(
                  hasScrollBody: false,
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              if (snapshot.hasError) {
                return SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text('Erreur : ${snapshot.error}'),
                  ),
                );
              }
              final allLignes = snapshot.data ?? [];
              final query = _searchController.text.trim().toLowerCase();
              final lignes = query.isEmpty
                  ? allLignes
                  : allLignes.where((item) {
                      if (item is! Map) return false;
                      return ['nom', 'matricule', 'section'].any(
                        (key) =>
                            '${item[key] ?? ''}'.toLowerCase().contains(query),
                      );
                    }).toList();
              if (lignes.isEmpty) {
                return SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(
                      query.isEmpty
                          ? 'Aucun retard sur cette période.'
                          : 'Aucun enseignant trouvé pour « ${_searchController.text.trim()} ».',
                    ),
                  ),
                );
              }
              return SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                sliver: SliverList.separated(
                  itemCount: lignes.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final l = lignes[i] as Map<String, dynamic>;
                    final id = '${l['enseignant_id']}';
                    final isDownloading = _downloadingRowId == id;
                    return Semantics(
                      button: true,
                      enabled: !_busy,
                      hint: 'Télécharger la fiche PDF',
                      child: Card(
                        clipBehavior: Clip.antiAlias,
                        child: InkWell(
                          onTap: _busy
                              ? null
                              : () => _downloadBilanEnseignant(l),
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Text(
                                  '${l['nom'] ?? '—'}',
                                  style: Theme.of(context).textTheme.titleMedium
                                      ?.copyWith(color: AuditronColors.ink900),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  '${l['matricule'] ?? '—'} · ${l['section'] ?? '—'}',
                                  style: const TextStyle(
                                    color: AuditronColors.ink500,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  '${l['jours_retard'] ?? 0} jours de retard · ${l['minutes_retard_total'] ?? 0} min cumulées',
                                  style: const TextStyle(
                                    color: AuditronColors.orange700,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                if (isDownloading) ...[
                                  const SizedBox(height: 12),
                                  const LinearProgressIndicator(
                                    color: AuditronColors.orange700,
                                    backgroundColor: AuditronColors.orange100,
                                  ),
                                  const SizedBox(height: 6),
                                  const Text(
                                    'Téléchargement de la fiche…',
                                    style: TextStyle(
                                      color: AuditronColors.orange700,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _BilanSheet extends StatefulWidget {
  final DateTime selectedMonth;
  final TextEditingController toleranceController;
  final ValueChanged<DateTime> onSelectMonth;
  final Future<String> Function() onSaveTolerance;
  final Future<void> Function() onDownloadCumule;
  final Future<void> Function(String) onDownloadSection;

  const _BilanSheet({
    required this.selectedMonth,
    required this.toleranceController,
    required this.onSelectMonth,
    required this.onSaveTolerance,
    required this.onDownloadCumule,
    required this.onDownloadSection,
  });

  @override
  State<_BilanSheet> createState() => _BilanSheetState();
}

class _BilanSheetState extends State<_BilanSheet> {
  late DateTime _selectedMonth;
  String? _selectedSection;
  String? _action;
  String? _message;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _selectedMonth = widget.selectedMonth;
  }

  Future<void> _runAction(
    String action,
    Future<String> Function() execute,
  ) async {
    if (_action != null) return;
    setState(() {
      _action = action;
      _message = null;
      _hasError = false;
    });
    try {
      final message = await execute();
      if (mounted) setState(() => _message = message);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _message = e.message;
          _hasError = true;
        });
      }
    } on FileSystemException {
      if (mounted) {
        setState(() {
          _message = 'Impossible d’enregistrer le PDF sur ce téléphone.';
          _hasError = true;
        });
      }
    } on PlatformException {
      if (mounted) {
        setState(() {
          _message = action == 'tolerance'
              ? 'Impossible d’enregistrer la tolérance sur ce téléphone.'
              : 'Impossible d’ouvrir le partage du PDF.';
          _hasError = true;
        });
      }
    } finally {
      if (mounted) setState(() => _action = null);
    }
  }

  Widget _progressIcon(IconData icon, String action) => _action == action
      ? const SizedBox(
          width: 18,
          height: 18,
          child: CircularProgressIndicator(
            strokeWidth: 2,
            color: AuditronColors.orange700,
          ),
        )
      : Icon(icon, size: 20);

  @override
  Widget build(BuildContext context) {
    final busy = _action != null;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      'Télécharger un bilan',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                  ),
                  IconButton(
                    tooltip: 'Fermer les bilans',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              ExcludeFocus(
                excluding: busy,
                child: IgnorePointer(
                  ignoring: busy,
                  child: AdminSearchableSelect(
                    value: _selectedMonth,
                    label: 'Période du bilan',
                    options: [
                      for (final month in _lastTwelveMonths())
                        AdminFieldOption(month, _monthLabel(month)),
                    ],
                    onChanged: (value) {
                      final month = value as DateTime;
                      setState(() {
                        _selectedMonth = month;
                        _message = null;
                      });
                      widget.onSelectMonth(month);
                    },
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Text(
                'Du ${DateFormat('d MMMM yyyy', 'fr').format(_selectedMonth)} au ${DateFormat('d MMMM yyyy', 'fr').format(DateTime(_selectedMonth.year, _selectedMonth.month + 1, 0))}',
                style: const TextStyle(color: AuditronColors.ink500),
              ),
              const SizedBox(height: 20),
              TextField(
                controller: widget.toleranceController,
                enabled: !busy,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: const InputDecoration(
                  labelText: 'Tolérance des retards (min)',
                  helperText: '0 à 120 minutes',
                ),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: busy
                    ? null
                    : () {
                        FocusScope.of(context).unfocus();
                        _runAction('tolerance', widget.onSaveTolerance);
                      },
                icon: _progressIcon(Icons.save_outlined, 'tolerance'),
                label: const Text('Enregistrer la tolérance'),
              ),
              const Divider(height: 32),
              Text(
                'Tout le personnel',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 6),
              const Text(
                'Jours et minutes de retard cumulés sur la période sélectionnée.',
                style: TextStyle(color: AuditronColors.ink500),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: busy
                    ? null
                    : () => _runAction('cumule', () async {
                        await widget.onDownloadCumule();
                        return 'PDF prêt à enregistrer ou partager.';
                      }),
                icon: _progressIcon(Icons.picture_as_pdf_outlined, 'cumule'),
                label: Text(
                  _action == 'cumule'
                      ? 'Préparation du PDF…'
                      : 'Bilan PDF cumulé',
                ),
              ),
              const Divider(height: 32),
              Text(
                'Par section',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 12),
              ExcludeFocus(
                excluding: busy,
                child: IgnorePointer(
                  ignoring: busy,
                  child: AdminSearchableSelect(
                    value: _selectedSection,
                    label: 'Section du bilan',
                    options: [
                      for (final section in _bilanSections)
                        AdminFieldOption(section, section),
                    ],
                    onChanged: (section) => setState(() {
                      _selectedSection = section as String;
                      _message = null;
                    }),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: busy || _selectedSection == null
                    ? null
                    : () => _runAction('section', () async {
                        await widget.onDownloadSection(_selectedSection!);
                        return 'PDF prêt à enregistrer ou partager.';
                      }),
                icon: _progressIcon(Icons.picture_as_pdf_outlined, 'section'),
                label: Text(
                  _action == 'section'
                      ? 'Préparation du PDF…'
                      : 'Bilan PDF de la section',
                ),
              ),
              if (_message != null) ...[
                const SizedBox(height: 16),
                Semantics(
                  liveRegion: true,
                  child: Text(
                    _message!,
                    style: TextStyle(
                      color: _hasError
                          ? Colors.red.shade700
                          : AuditronColors.brand700,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
