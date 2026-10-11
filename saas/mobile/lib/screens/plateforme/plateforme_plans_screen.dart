import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

class PlateformePlansScreen extends StatefulWidget {
  const PlateformePlansScreen({super.key, required this.estSuperAdmin});
  final bool estSuperAdmin;

  @override
  State<PlateformePlansScreen> createState() => _PlateformePlansScreenState();
}

class _PlateformePlansScreenState extends State<PlateformePlansScreen> {
  List<Map<String, dynamic>> _plans = [];
  List<Map<String, dynamic>> _subscriptions = [];
  List<Map<String, dynamic>> _tenants = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  Future<void> _load() async {
    try {
      final responses = await Future.wait([
        PlateformeApiClient.instance.get('/plans'),
        PlateformeApiClient.instance.get('/abonnements'),
        PlateformeApiClient.instance.get('/etablissements'),
      ]);
      List<Map<String, dynamic>> rows(dynamic result) =>
          ((result as Map<String, dynamic>)['data'] as List? ?? const [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
      if (mounted)
        setState(() {
          _plans = rows(responses[0]);
          _subscriptions = rows(responses[1]);
          _tenants = rows(responses[2]);
          _loading = false;
          _error = null;
        });
    } on ApiException catch (error) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = error.message;
        });
    } catch (error) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = 'Chargement impossible : $error';
        });
    }
  }

  Future<void> _editPlan([Map<String, dynamic>? plan]) async {
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _PlanDialog(plan: plan),
    );
    if (values == null) return;
    await _perform(() async {
      if (plan == null) {
        await PlateformeApiClient.instance.post('/plans', values);
      } else {
        await PlateformeApiClient.instance.put('/plans/${plan['id']}', values);
      }
      await _load();
    });
  }

  Future<void> _removePlan(Map<String, dynamic> plan) async {
    final accepted = await _confirm(
      'Désactiver l’offre',
      'Désactiver ou supprimer « ${plan['nom']} » ? Les plans liés à des abonnements seront conservés et désactivés.',
    );
    if (!accepted) return;
    await _perform(() async {
      await PlateformeApiClient.instance.delete('/plans/${plan['id']}');
      await _load();
    });
  }

  Future<void> _createSubscription() async {
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _SubscriptionDialog(
        tenants: _tenants,
        plans: _plans.where((plan) => plan['actif'] == true).toList(),
      ),
    );
    if (values == null) return;
    final accepted = await _confirm(
      'Souscrire un abonnement',
      'Cette action clôt l’abonnement actif précédent de l’établissement choisi.',
    );
    if (!accepted) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post('/abonnements', values);
      await _load();
    });
  }

  Future<void> _renew(Map<String, dynamic> subscription) async {
    final accepted = await _confirm(
      'Renouveler',
      'Prolonger ${subscription['etablissement']?['nom'] ?? 'cet abonnement'} d’une période ?',
    );
    if (!accepted) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post(
        '/abonnements/${subscription['id']}/renouvelle',
      );
      await _load();
    });
  }

  Future<void> _editSubscription(Map<String, dynamic> subscription) async {
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _SubscriptionEditDialog(subscription: subscription),
    );
    if (values == null) return;
    await _perform(() async {
      await PlateformeApiClient.instance.put(
        '/abonnements/${subscription['id']}',
        values,
      );
      await _load();
    });
  }

  Future<bool> _confirm(String title, String message) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Retour'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirmer'),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _perform(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = 'Opération impossible : $error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          if (_busy) const LinearProgressIndicator(),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Offres',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (widget.estSuperAdmin)
                FilledButton.icon(
                  onPressed: _busy ? null : () => _editPlan(),
                  icon: const Icon(Icons.add),
                  label: const Text('Créer'),
                ),
            ],
          ),
          for (final plan in _plans)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            plan['nom']?.toString() ?? '',
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        if (plan['actif'] != true)
                          const Chip(
                            label: Text('Inactif'),
                            visualDensity: VisualDensity.compact,
                          ),
                      ],
                    ),
                    Text(
                      '${_money(plan['prix'])} ${plan['devise'] ?? 'XAF'} / ${_period(plan['periodicite'])}',
                      style: const TextStyle(color: AuditronColors.brand700),
                    ),
                    if (plan['description'] != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(plan['description'].toString()),
                      ),
                    const SizedBox(height: 5),
                    Text(
                      'Personnel : ${plan['max_personnel'] ?? 'illimité'} · Bornes : ${plan['max_bornes'] ?? 'illimité'}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AuditronColors.ink500,
                      ),
                    ),
                    if (plan['fonctionnalites'] is List &&
                        (plan['fonctionnalites'] as List).isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 5),
                        child: Text(
                          (plan['fonctionnalites'] as List).join(' · '),
                          style: const TextStyle(fontSize: 12),
                        ),
                      ),
                    if (widget.estSuperAdmin)
                      Wrap(
                        spacing: 6,
                        children: [
                          TextButton.icon(
                            onPressed: _busy ? null : () => _editPlan(plan),
                            icon: const Icon(Icons.edit_outlined, size: 18),
                            label: const Text('Modifier'),
                          ),
                          TextButton.icon(
                            onPressed: _busy ? null : () => _removePlan(plan),
                            icon: const Icon(Icons.archive_outlined, size: 18),
                            label: Text(
                              plan['actif'] == true
                                  ? 'Désactiver'
                                  : 'Supprimer',
                            ),
                            style: TextButton.styleFrom(
                              foregroundColor: Colors.red,
                            ),
                          ),
                        ],
                      ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Abonnements (${_subscriptions.length})',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (widget.estSuperAdmin)
                IconButton.filledTonal(
                  onPressed: _busy ? null : _createSubscription,
                  tooltip: 'Souscrire',
                  icon: const Icon(Icons.add),
                ),
            ],
          ),
          for (final subscription in _subscriptions)
            Card(
              child: ListTile(
                title: Text(
                  subscription['etablissement']?['nom']?.toString() ??
                      'Établissement',
                ),
                subtitle: Text(
                  '${subscription['plan']?['nom'] ?? 'Plan'} · ${subscription['statut']}\n'
                  '${_date(subscription['debut_le'])} → ${_date(subscription['fin_le'])}',
                ),
                isThreeLine: true,
                trailing: widget.estSuperAdmin
                    ? PopupMenuButton<String>(
                        onSelected: (value) {
                          if (value == 'renew') _renew(subscription);
                          if (value == 'edit') _editSubscription(subscription);
                        },
                        itemBuilder: (context) => const [
                          PopupMenuItem(
                            value: 'renew',
                            child: Text('Renouveler'),
                          ),
                          PopupMenuItem(value: 'edit', child: Text('Modifier')),
                        ],
                      )
                    : Text(_money(subscription['montant'])),
              ),
            ),
        ],
      ),
    );
  }
}

class _PlanDialog extends StatefulWidget {
  const _PlanDialog({this.plan});
  final Map<String, dynamic>? plan;
  @override
  State<_PlanDialog> createState() => _PlanDialogState();
}

class _PlanDialogState extends State<_PlanDialog> {
  late final Map<String, TextEditingController> _fields;
  late String _periodicity;
  late bool _active;
  bool get _editing => widget.plan != null;

  @override
  void initState() {
    super.initState();
    _fields = {
      for (final key in [
        'code',
        'nom',
        'description',
        'prix',
        'devise',
        'max_personnel',
        'max_bornes',
        'fonctionnalites',
      ])
        key: TextEditingController(
          text: switch (key) {
            'fonctionnalites' =>
              (widget.plan?['fonctionnalites'] as List? ?? const []).join(', '),
            _ =>
              widget.plan?[key]?.toString() ??
                  (key == 'devise'
                      ? 'XAF'
                      : key == 'prix'
                      ? '0'
                      : ''),
          },
        ),
    };
    _periodicity = widget.plan?['periodicite']?.toString() ?? 'mensuel';
    _active = widget.plan?['actif'] as bool? ?? true;
  }

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(_editing ? 'Modifier une offre' : 'Créer une offre'),
    content: SizedBox(
      width: 440,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final field in [
              'code',
              'nom',
              'description',
              'prix',
              'devise',
            ])
              Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: TextField(
                  controller: _fields[field],
                  keyboardType: field == 'prix'
                      ? const TextInputType.numberWithOptions(decimal: true)
                      : TextInputType.text,
                  decoration: InputDecoration(labelText: _planLabel(field)),
                ),
              ),
            DropdownButtonFormField<String>(
              initialValue: _periodicity,
              decoration: const InputDecoration(labelText: 'Périodicité'),
              items: const [
                DropdownMenuItem(value: 'mensuel', child: Text('Mensuelle')),
                DropdownMenuItem(
                  value: 'trimestriel',
                  child: Text('Trimestrielle'),
                ),
                DropdownMenuItem(value: 'annuel', child: Text('Annuelle')),
              ],
              onChanged: (value) =>
                  setState(() => _periodicity = value ?? 'mensuel'),
            ),
            const SizedBox(height: 9),
            for (final field in ['max_personnel', 'max_bornes'])
              Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: TextField(
                  controller: _fields[field],
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: '${_planLabel(field)} (vide = illimité)',
                  ),
                ),
              ),
            TextField(
              controller: _fields['fonctionnalites'],
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Fonctionnalités séparées par des virgules',
              ),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: const Text('Offre active'),
              value: _active,
              onChanged: (value) => setState(() => _active = value),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Annuler'),
      ),
      FilledButton(
        onPressed: () {
          final price = double.tryParse(
            _fields['prix']!.text.replaceAll(',', '.'),
          );
          if (_fields['nom']!.text.trim().isEmpty ||
              (!_editing && _fields['code']!.text.trim().isEmpty) ||
              price == null ||
              price < 0) {
            return;
          }
          final features = _fields['fonctionnalites']!.text
              .split(RegExp(r'[\n,]'))
              .map((feature) => feature.trim())
              .where((feature) => feature.isNotEmpty)
              .toList();
          Navigator.pop(context, {
            if (!_editing || _fields['code']!.text.trim().isNotEmpty)
              'code': _fields['code']!.text.trim(),
            if (_fields['nom']!.text.trim().isNotEmpty)
              'nom': _fields['nom']!.text.trim(),
            'description': _fields['description']!.text.trim(),
            'prix': price,
            'devise': _fields['devise']!.text.trim().isEmpty
                ? 'XAF'
                : _fields['devise']!.text.trim().toUpperCase(),
            'periodicite': _periodicity,
            'max_personnel': _parseIntOrNull(_fields['max_personnel']!.text),
            'max_bornes': _parseIntOrNull(_fields['max_bornes']!.text),
            'fonctionnalites': features,
            'actif': _active,
          });
        },
        child: const Text('Enregistrer'),
      ),
    ],
  );
}

class _SubscriptionDialog extends StatefulWidget {
  const _SubscriptionDialog({required this.tenants, required this.plans});
  final List<Map<String, dynamic>> tenants;
  final List<Map<String, dynamic>> plans;
  @override
  State<_SubscriptionDialog> createState() => _SubscriptionDialogState();
}

class _SubscriptionDialogState extends State<_SubscriptionDialog> {
  String? _tenant;
  String? _plan;
  DateTime _start = DateTime.now();
  DateTime? _end;

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Souscrire un abonnement'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        DropdownButtonFormField<String>(
          initialValue: _tenant,
          decoration: const InputDecoration(labelText: 'Établissement'),
          items: [
            for (final tenant in widget.tenants)
              DropdownMenuItem(
                value: tenant['id'].toString(),
                child: Text('${tenant['nom']} (${tenant['code']})'),
              ),
          ],
          onChanged: (value) => setState(() => _tenant = value),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          initialValue: _plan,
          decoration: const InputDecoration(labelText: 'Offre'),
          items: [
            for (final plan in widget.plans)
              DropdownMenuItem(
                value: plan['id'].toString(),
                child: Text(plan['nom'].toString()),
              ),
          ],
          onChanged: (value) => setState(() => _plan = value),
        ),
        const SizedBox(height: 8),
        _DatePickerRow(
          label: 'Début',
          value: _start,
          onChanged: (date) {
            if (date != null) {
              setState(() {
                _start = date;
                if (_end != null && !_end!.isAfter(_start)) _end = null;
              });
            }
          },
        ),
        _DatePickerRow(
          label: 'Échéance facultative',
          value: _end,
          onChanged: (date) => setState(() => _end = date),
          clearable: true,
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Annuler'),
      ),
      FilledButton(
        onPressed:
            _tenant == null ||
                _plan == null ||
                (_end != null && !_end!.isAfter(_start))
            ? null
            : () => Navigator.pop(context, {
                'etablissement_id': _tenant,
                'plan_id': _plan,
                'debut_le': _isoDate(_start),
                if (_end != null) 'fin_le': _isoDate(_end!),
              }),
        child: const Text('Souscrire'),
      ),
    ],
  );
}

class _SubscriptionEditDialog extends StatefulWidget {
  const _SubscriptionEditDialog({required this.subscription});
  final Map<String, dynamic> subscription;
  @override
  State<_SubscriptionEditDialog> createState() =>
      _SubscriptionEditDialogState();
}

class _SubscriptionEditDialogState extends State<_SubscriptionEditDialog> {
  late final TextEditingController _amount;
  late final TextEditingController _notes;
  late DateTime? _end;
  late String _status;

  @override
  void initState() {
    super.initState();
    _amount = TextEditingController(
      text: widget.subscription['montant']?.toString() ?? '',
    );
    _notes = TextEditingController(
      text: widget.subscription['notes']?.toString() ?? '',
    );
    _end = DateTime.tryParse(widget.subscription['fin_le']?.toString() ?? '');
    _status = widget.subscription['statut']?.toString() ?? 'actif';
  }

  @override
  void dispose() {
    _amount.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Modifier l’abonnement'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        DropdownButtonFormField<String>(
          initialValue: _status,
          decoration: const InputDecoration(labelText: 'Statut'),
          items: const [
            DropdownMenuItem(value: 'actif', child: Text('Actif')),
            DropdownMenuItem(value: 'expire', child: Text('Expiré')),
            DropdownMenuItem(value: 'resilie', child: Text('Résilié')),
          ],
          onChanged: (value) => setState(() => _status = value ?? 'actif'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _amount,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Montant'),
        ),
        _DatePickerRow(
          label: 'Échéance',
          value: _end,
          clearable: true,
          onChanged: (value) => setState(() => _end = value),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _notes,
          maxLines: 2,
          decoration: const InputDecoration(labelText: 'Notes'),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Annuler'),
      ),
      FilledButton(
        onPressed: () {
          final amount = double.tryParse(_amount.text.replaceAll(',', '.'));
          if (amount == null || amount < 0) return;
          Navigator.pop(context, {
            'statut': _status,
            'montant': amount,
            'fin_le': _end == null ? null : _isoDate(_end!),
            'notes': _notes.text.trim(),
          });
        },
        child: const Text('Enregistrer'),
      ),
    ],
  );
}

class _DatePickerRow extends StatelessWidget {
  const _DatePickerRow({
    required this.label,
    required this.value,
    required this.onChanged,
    this.clearable = false,
  });
  final String label;
  final DateTime? value;
  final ValueChanged<DateTime?> onChanged;
  final bool clearable;

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: EdgeInsets.zero,
    title: Text(label),
    subtitle: Text(_date(value)),
    trailing: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (clearable && value != null)
          IconButton(
            onPressed: () => onChanged(null),
            tooltip: 'Effacer',
            icon: const Icon(Icons.clear),
          ),
        IconButton(
          onPressed: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: value ?? DateTime.now(),
              firstDate: DateTime(2020),
              lastDate: DateTime(2100),
              locale: const Locale('fr'),
            );
            if (date != null) onChanged(date);
          },
          icon: const Icon(Icons.calendar_month_outlined),
        ),
      ],
    ),
  );
}

String _planLabel(String key) => switch (key) {
  'code' => 'Code',
  'nom' => 'Nom',
  'description' => 'Description',
  'prix' => 'Prix',
  'devise' => 'Devise',
  'max_personnel' => 'Quota personnel',
  _ => 'Quota bornes',
};

int? _parseIntOrNull(String value) =>
    value.trim().isEmpty ? null : int.tryParse(value.trim());

String _money(dynamic value) => NumberFormat(
  '#,##0.##',
  'fr_FR',
).format(value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0);

String _period(dynamic value) => switch (value) {
  'trimestriel' => 'trimestre',
  'annuel' => 'an',
  _ => 'mois',
};

String _date(dynamic value) {
  if (value == null) return 'sans échéance';
  final date = value is DateTime ? value : DateTime.tryParse(value.toString());
  return date == null
      ? value.toString()
      : DateFormat('dd/MM/yyyy').format(date);
}

String _isoDate(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-'
    '${date.month.toString().padLeft(2, '0')}-'
    '${date.day.toString().padLeft(2, '0')}';
