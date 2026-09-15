import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
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
  final _toleranceController = TextEditingController(text: '10');

  late Future<List<dynamic>> _future;
  bool _savingTolerance = false;
  bool _downloadingCumule = false;
  String? _downloadingRowId;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _toleranceController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load() async {
    final results = await Future.wait([
      OfflineCache.instance.readThrough(
        'admin_retards_${_isoDate(_debut)}_${_isoDate(_fin)}',
        () => AdminApiClient.instance.get('/retards', query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)}),
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

  Future<void> _pickDate({required bool isDebut}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: isDebut ? _debut : _fin,
      firstDate: DateTime(2020),
      lastDate: DateTime(DateTime.now().year + 1),
    );
    if (picked == null) return;
    setState(() {
      if (isDebut) {
        _debut = picked;
      } else {
        _fin = picked;
      }
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
      await AdminApiClient.instance.put('/retards/parametres', {'tolerance_minutes': minutes});
      if (mounted) _showMessage('Tolérance enregistrée.');
      await _refresh();
    } on ApiException catch (e) {
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
      await _sharePdf(bytes, 'bilan-retards-${_isoDate(_debut)}-${_isoDate(_fin)}.pdf');
    } on ApiException catch (e) {
      if (mounted) _showMessage(e.message);
    } finally {
      if (mounted) setState(() => _downloadingCumule = false);
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _FilterBar(
          debut: _debut,
          fin: _fin,
          toleranceController: _toleranceController,
          savingTolerance: _savingTolerance,
          downloadingCumule: _downloadingCumule,
          onPickDebut: () => _pickDate(isDebut: true),
          onPickFin: () => _pickDate(isDebut: false),
          onSaveTolerance: _saveTolerance,
          onDownloadCumule: _downloadBilanCumule,
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
                  return ListView(children: [Padding(padding: const EdgeInsets.all(24), child: Text('Erreur : ${snapshot.error}'))]);
                }
                final lignes = snapshot.data ?? [];
                if (lignes.isEmpty) {
                  return ListView(children: const [Padding(padding: EdgeInsets.all(24), child: Text('Aucun retard sur cette période.'))]);
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
                        leading: const Icon(Icons.schedule_outlined, color: AuditronColors.gold600),
                        title: Text('${l['nom'] ?? '—'}'),
                        subtitle: Text('${l['matricule'] ?? '—'} · ${l['section'] ?? '—'}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              '${l['jours_retard'] ?? 0}j · ${l['minutes_retard_total'] ?? 0}min',
                              style: const TextStyle(fontSize: 12, color: AuditronColors.ink500),
                            ),
                            const SizedBox(width: 4),
                            isDownloading
                                ? const SizedBox(
                                    width: 36,
                                    height: 36,
                                    child: Padding(
                                      padding: EdgeInsets.all(8),
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    ),
                                  )
                                : IconButton(
                                    icon: const Icon(Icons.picture_as_pdf_outlined, size: 20),
                                    tooltip: 'Fiche PDF',
                                    onPressed: () => _downloadBilanEnseignant(l),
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
  final DateTime debut;
  final DateTime fin;
  final TextEditingController toleranceController;
  final bool savingTolerance;
  final bool downloadingCumule;
  final VoidCallback onPickDebut;
  final VoidCallback onPickFin;
  final VoidCallback onSaveTolerance;
  final VoidCallback onDownloadCumule;

  const _FilterBar({
    required this.debut,
    required this.fin,
    required this.toleranceController,
    required this.savingTolerance,
    required this.downloadingCumule,
    required this.onPickDebut,
    required this.onPickFin,
    required this.onSaveTolerance,
    required this.onDownloadCumule,
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
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onPickDebut,
                  icon: const Icon(Icons.calendar_today, size: 16),
                  label: Text('Début : ${_isoDate(debut)}'),
                  style: OutlinedButton.styleFrom(foregroundColor: AuditronColors.ink700, side: const BorderSide(color: AuditronColors.ink100)),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onPickFin,
                  icon: const Icon(Icons.calendar_today, size: 16),
                  label: Text('Fin : ${_isoDate(fin)}'),
                  style: OutlinedButton.styleFrom(foregroundColor: AuditronColors.ink700, side: const BorderSide(color: AuditronColors.ink100)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Text('Tolérance (min)', style: TextStyle(color: AuditronColors.ink700, fontSize: 13)),
              const SizedBox(width: 8),
              SizedBox(
                width: 64,
                child: TextField(
                  controller: toleranceController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(isDense: true, contentPadding: EdgeInsets.symmetric(horizontal: 10, vertical: 8)),
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                onPressed: savingTolerance ? null : onSaveTolerance,
                child: savingTolerance
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
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
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.picture_as_pdf_outlined, size: 18),
              label: const Text('Bilan PDF cumulé'),
            ),
          ),
        ],
      ),
    );
  }
}
