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

DateTime _startOfMonth() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, 1);
}

DateTime _endOfMonth() {
  final now = DateTime.now();
  return DateTime(now.year, now.month + 1, 0);
}

String _isoDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

List<dynamic> _asList(dynamic data) {
  if (data is List) return data;
  if (data is Map && data['data'] is List) return data['data'] as List<dynamic>;
  return const [];
}

/// Classement de ponctualité (§admin-mobile) — équivalent mobile de
/// PonctualitePage.jsx : jours prévus / jours ponctuels, taux, et score
/// (coefficient régressif sur les minutes de retard cumulées) pour départager
/// les enseignants à nombre égal de jours ponctuels.
class AdminPonctualiteScreen extends StatefulWidget {
  const AdminPonctualiteScreen({super.key});

  @override
  State<AdminPonctualiteScreen> createState() =>
      _AdminPonctualiteScreenState();
}

class _AdminPonctualiteScreenState extends State<AdminPonctualiteScreen> {
  final DateTime _debut = _startOfMonth();
  final DateTime _fin = _endOfMonth();
  final _coefficientController = TextEditingController(text: '30');
  final _searchController = TextEditingController();

  late Future<List<dynamic>> _future;
  int _toleranceMinutes = 10;
  bool _savingCoefficient = false;
  bool _downloadingPdf = false;

  bool get _busy => _savingCoefficient || _downloadingPdf;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _coefficientController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load() async {
    final results = await Future.wait([
      OfflineCache.instance.readThrough(
        'admin_ponctualite_${_isoDate(_debut)}_${_isoDate(_fin)}',
        () => AdminApiClient.instance.get(
          '/retards/classement-ponctualite',
          query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)},
        ),
      ),
      OfflineCache.instance.readThrough(
        'admin_retards_parametres',
        () => AdminApiClient.instance.get('/retards/parametres'),
      ),
    ]);

    final parametres = results[1];
    if (parametres is Map && parametres['coefficient_minutes'] != null) {
      _coefficientController.text = '${parametres['coefficient_minutes']}';
    }
    if (parametres is Map && parametres['tolerance_minutes'] != null) {
      _toleranceMinutes = parametres['tolerance_minutes'] as int;
    }
    return _asList(results[0]);
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _saveCoefficient() async {
    final minutes = int.tryParse(_coefficientController.text.trim());
    if (minutes == null || minutes < 1) {
      _showMessage('Le coefficient doit être un entier positif.');
      return;
    }
    setState(() => _savingCoefficient = true);
    try {
      await AdminApiClient.instance.put('/retards/parametres', {
        'tolerance_minutes': _toleranceMinutes,
        'coefficient_minutes': minutes,
      });
      await _refresh();
      if (mounted) _showMessage('Coefficient enregistré.');
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        await OfflineCache.instance.overwrite('admin_retards_parametres', {
          'coefficient_minutes': minutes,
        });
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          method: 'PUT',
          path: '/retards/parametres',
          body: {'coefficient_minutes': minutes},
          label: 'Mettre à jour le coefficient de ponctualité',
        );
        await SyncEngine.instance.notifyEnqueued();
        if (mounted) _showMessage('Coefficient enregistré hors ligne.');
      } else if (mounted) {
        _showMessage(e.message);
      }
    } finally {
      if (mounted) setState(() => _savingCoefficient = false);
    }
  }

  Future<void> _downloadClassementPdf() async {
    setState(() => _downloadingPdf = true);
    try {
      final bytes = await AdminApiClient.instance.getBytes(
        // Le classement seul : le bilan cumulé y mêlait retards, absences
        // et périodes à rattraper, où le classement se cherchait.
        '/retards/classement-ponctualite/pdf',
        query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)},
      );
      final dir = await getTemporaryDirectory();
      final file = File(
        '${dir.path}/classement-ponctualite-${_isoDate(_debut)}-${_isoDate(_fin)}.pdf',
      );
      await file.writeAsBytes(bytes, flush: true);
      await Share.shareXFiles([XFile(file.path)]);
    } on PlatformException {
      if (mounted) {
        _showMessage('Impossible d’ouvrir le partage du PDF.');
      }
    } finally {
      if (mounted) setState(() => _downloadingPdf = false);
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

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
                  const Text(
                    'Classe les enseignants par taux de ponctualité. À nombre '
                    'égal de jours ponctuels, un coefficient régressif sur les '
                    'minutes de retard cumulées départage le classement.',
                    style: TextStyle(color: AuditronColors.ink500),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _coefficientController,
                          enabled: !_busy,
                          keyboardType: TextInputType.number,
                          inputFormatters: [
                            FilteringTextInputFormatter.digitsOnly,
                          ],
                          decoration: const InputDecoration(
                            labelText: 'Coefficient régressif (min)',
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton(
                        onPressed: _busy ? null : _saveCoefficient,
                        child: _savingCoefficient
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Enregistrer'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: _busy ? null : _downloadClassementPdf,
                    icon: _downloadingPdf
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.picture_as_pdf_outlined),
                    label: const Text('Télécharger le classement (PDF)'),
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
                          ? 'Aucune donnée sur cette période.'
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
                    return Card(
                      clipBehavior: Clip.antiAlias,
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                CircleAvatar(
                                  radius: 14,
                                  backgroundColor: AuditronColors.brand100,
                                  child: Text(
                                    '${i + 1}',
                                    style: const TextStyle(
                                      color: AuditronColors.brand700,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    '${l['nom'] ?? '—'}',
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(
                                          color: AuditronColors.ink900,
                                        ),
                                  ),
                                ),
                              ],
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
                              '${l['jours_ponctuels'] ?? 0}/${l['jours_prevus'] ?? 0} jours ponctuels · ${l['taux_ponctualite'] ?? 0}%',
                              style: const TextStyle(
                                color: AuditronColors.brand700,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${l['total_retard_minutes'] ?? 0} min de retard cumulées · score ${l['score_ponctualite'] ?? 0}',
                              style: const TextStyle(
                                color: AuditronColors.ink500,
                                fontSize: 12,
                              ),
                            ),
                          ],
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
