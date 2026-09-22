import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';

/// Gestion des signalements (§admin-mobile) — équivalent mobile de
/// SignalementsPage.jsx, avec l'action de création groupée
/// (`POST /signalements/bulk`).
class AdminSignalementsScreen extends StatelessWidget {
  const AdminSignalementsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Signalements',
      resourcePath: '/signalements',
      cacheKey: 'admin_signalements',
      icon: Icons.flag,
      fields: [
        AdminFieldSpec(
          key: 'enseignant_id',
          label: 'Enseignant',
          type: AdminFieldType.select,
          required: true,
          optionsEndpoint: '/personnel?per_page=500',
        ),
        const AdminFieldSpec(key: 'date', label: 'Date', type: AdminFieldType.date, required: true),
        const AdminFieldSpec(key: 'motif', label: 'Motif', required: true),
        const AdminFieldSpec(key: 'duree_jours', label: 'Durée (jours)', type: AdminFieldType.number),
      ],
      itemTitle: (item) => item['enseignant']?['nom']?.toString() ?? '—',
      itemSubtitle: (item) => [
        if (item['date'] != null) item['date'],
        if (item['motif'] != null) item['motif'],
      ].join(' · '),
      headerActions: (context, refresh) => [
        IconButton(
          tooltip: 'Signalement groupé',
          icon: const Icon(Icons.group_add_outlined, size: 20),
          onPressed: () => _openBulkForm(context, refresh),
        ),
      ],
    );
  }

  Future<void> _openBulkForm(BuildContext context, VoidCallback refresh) async {
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => _BulkSignalementForm(onDone: refresh),
    );
  }
}

class _BulkSignalementForm extends StatefulWidget {
  final VoidCallback onDone;
  const _BulkSignalementForm({required this.onDone});

  @override
  State<_BulkSignalementForm> createState() => _BulkSignalementFormState();
}

class _BulkSignalementFormState extends State<_BulkSignalementForm> {
  late Future<List<Map<String, dynamic>>> _enseignants;
  final Set<int> _selected = {};
  final _dateCtrl = TextEditingController(text: DateTime.now().toIso8601String().substring(0, 10));
  final _motifCtrl = TextEditingController();
  final _dureeCtrl = TextEditingController();
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _enseignants = _load();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_options_/personnel?per_page=500',
      () => AdminApiClient.instance.get('/personnel?per_page=500'),
    );
    final list = data is Map && data['data'] is List ? data['data'] as List : const [];
    return list.cast<Map<String, dynamic>>();
  }

  Future<void> _submit() async {
    if (_selected.isEmpty || _motifCtrl.text.trim().isEmpty) return;
    setState(() => _busy = true);
    final body = {
      'enseignant_ids': _selected.toList(),
      'date': _dateCtrl.text.trim(),
      'motif': _motifCtrl.text.trim(),
      if (_dureeCtrl.text.trim().isNotEmpty) 'duree_jours': int.tryParse(_dureeCtrl.text.trim()),
    };
    try {
      await AdminApiClient.instance.post('/signalements/bulk', body);
      widget.onDone();
      if (mounted) Navigator.pop(context);
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          path: '/signalements/bulk',
          body: body,
          label: 'Créer un signalement groupé',
        );
        await SyncEngine.instance.notifyEnqueued();
        widget.onDone();
        if (mounted) Navigator.pop(context);
        return;
      }
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 16, right: 16, top: 16, bottom: MediaQuery.of(context).viewInsets.bottom + 16),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Signalement groupé', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            TextField(controller: _dateCtrl, decoration: const InputDecoration(labelText: 'Date (AAAA-MM-JJ)')),
            const SizedBox(height: 12),
            TextField(controller: _motifCtrl, decoration: const InputDecoration(labelText: 'Motif')),
            const SizedBox(height: 12),
            TextField(controller: _dureeCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Durée (jours)')),
            const SizedBox(height: 12),
            FutureBuilder<List<Map<String, dynamic>>>(
              future: _enseignants,
              builder: (context, snapshot) {
                if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
                return SizedBox(
                  height: 260,
                  child: ListView(
                    children: snapshot.data!.map((e) {
                      final id = e['id'] as int;
                      return CheckboxListTile(
                        dense: true,
                        title: Text(e['nom']?.toString() ?? '—'),
                        value: _selected.contains(id),
                        onChanged: (v) => setState(() => v == true ? _selected.add(id) : _selected.remove(id)),
                      );
                    }).toList(),
                  ),
                );
              },
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: _busy ? null : _submit,
                child: _busy
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : Text('Créer pour ${_selected.length} enseignant(s)'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
