import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';
import 'admin_field_spec.dart';
import 'admin_select_field.dart';

/// Écran CRUD générique (liste + création + édition + suppression),
/// équivalent Flutter de `ResourceTable.jsx` côté web : piloté par une liste
/// de [AdminFieldSpec] plutôt que réécrit à la main pour chaque entité
/// simple (Accréditations, Classes, Disciplines, Fériés, Signalements...).
class AdminCrudScreen extends StatefulWidget {
  final String title;
  final String resourcePath;
  final String cacheKey;
  final List<AdminFieldSpec> fields;
  final String Function(Map<String, dynamic> item) itemTitle;
  final String? Function(Map<String, dynamic> item)? itemSubtitle;
  final IconData icon;
  final String idKey;

  /// Boutons additionnels affichés dans l'en-tête, à côté de "Nouveau"
  /// (ex : import/export XLSX).
  final List<Widget> Function(BuildContext context, VoidCallback refresh)?
  headerActions;

  /// Actions additionnelles par ligne, insérées avant "Éditer"/"Suppr."
  /// (ex : gestion de la photo pour Personnel).
  final List<Widget> Function(BuildContext context, Map<String, dynamic> item)?
  extraRowActions;

  /// Remplace l'icône fixe par un widget par élément (ex : miniature photo).
  final Widget Function(BuildContext context, Map<String, dynamic> item)?
  leadingBuilder;
  final Future<void> Function(BuildContext context, Map<String, dynamic> item)?
  onItemTap;

  /// Désactive la création/édition/suppression (utilisé pour des vues
  /// lecture seule qui n'ont pas besoin d'un écran dédié).
  final bool readOnly;

  const AdminCrudScreen({
    super.key,
    required this.title,
    required this.resourcePath,
    required this.cacheKey,
    required this.fields,
    required this.itemTitle,
    this.itemSubtitle,
    this.icon = Icons.list_alt,
    this.idKey = 'id',
    this.headerActions,
    this.extraRowActions,
    this.leadingBuilder,
    this.onItemTap,
    this.readOnly = false,
  });

  @override
  State<AdminCrudScreen> createState() => AdminCrudScreenState();
}

class AdminCrudScreenState extends State<AdminCrudScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      widget.cacheKey,
      () => AdminApiClient.instance.getAllPages(widget.resourcePath),
    );
    if (data is Map && data['data'] is List)
      return data['data'] as List<dynamic>;
    if (data is List) return data;
    return const [];
  }

  Future<void> refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _openForm({Map<String, dynamic>? item}) async {
    final saved = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _AdminCrudForm(fields: widget.fields, initial: item),
    );
    if (saved == null) return;

    final isEdit = item != null;
    final items = List<dynamic>.from(await _future);

    try {
      if (isEdit) {
        final updated = await AdminApiClient.instance.put(
          '${widget.resourcePath}/${item[widget.idKey]}',
          saved,
        );
        final merged = updated is Map<String, dynamic>
            ? updated
            : {...item, ...saved};
        final index = items.indexWhere(
          (e) => e[widget.idKey] == item[widget.idKey],
        );
        if (index != -1) items[index] = merged;
      } else {
        final created = await AdminApiClient.instance.post(
          widget.resourcePath,
          saved,
        );
        items.insert(0, created is Map<String, dynamic> ? created : saved);
      }
      await OfflineCache.instance.overwrite(widget.cacheKey, {'data': items});
      if (mounted) setState(() => _future = Future.value(items));
      if (mounted) _showSuccess(isEdit ? 'Modification réussie.' : 'Création réussie.');
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        // Hors-ligne (§offline-sync) : mise à jour optimiste + rejeu différé.
        if (isEdit) {
          final index = items.indexWhere(
            (el) => el[widget.idKey] == item[widget.idKey],
          );
          if (index != -1) items[index] = {...item, ...saved};
        } else {
          items.insert(0, {
            ...saved,
            widget.idKey: 'tmp-${DateTime.now().millisecondsSinceEpoch}',
          });
        }
        await OfflineCache.instance.overwrite(widget.cacheKey, {'data': items});
        if (mounted) setState(() => _future = Future.value(items));
        if (mounted) _showSuccess('${isEdit ? 'Modification' : 'Création'} enregistrée hors ligne.');
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          method: isEdit ? 'PUT' : 'POST',
          path: isEdit
              ? '${widget.resourcePath}/${item[widget.idKey]}'
              : widget.resourcePath,
          body: saved,
          label:
              '${isEdit ? 'Modifier' : 'Créer'} ${widget.title.toLowerCase()}',
        );
        await SyncEngine.instance.notifyEnqueued();
        return;
      }
      if (mounted) _showError(e);
    }
  }

  Future<void> _delete(Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Confirmer la suppression ?'),
        content: Text(widget.itemTitle(item)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    final items = List<dynamic>.from(await _future)
      ..removeWhere((e) => e[widget.idKey] == item[widget.idKey]);
    await OfflineCache.instance.overwrite(widget.cacheKey, {'data': items});
    if (mounted) setState(() => _future = Future.value(items));

    try {
      await AdminApiClient.instance.delete(
        '${widget.resourcePath}/${item[widget.idKey]}',
      );
      if (mounted) _showSuccess('Suppression réussie.');
    } on ApiException catch (e) {
      if (e.statusCode != 0) {
        if (mounted) _showError(e);
        return;
      }
      await PendingActionsQueue.instance.enqueue(
        authMode: AuthMode.admin,
        method: 'DELETE',
        path: '${widget.resourcePath}/${item[widget.idKey]}',
        body: const {},
        label: 'Supprimer ${widget.itemTitle(item)}',
      );
      await SyncEngine.instance.notifyEnqueued();
      if (mounted) _showSuccess('Suppression enregistrée hors ligne.');
    }
  }

  void _showError(ApiException e) {
    String message = e.message;
    if (e.errors is Map && (e.errors as Map).isNotEmpty) {
      final firstFieldErrors = (e.errors as Map).values.first;
      if (firstFieldErrors is List && firstFieldErrors.isNotEmpty) {
        message = firstFieldErrors.first.toString();
      }
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  void _showSuccess(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message), backgroundColor: AuditronColors.brand700));
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  widget.title,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              if (widget.headerActions != null)
                ...widget.headerActions!(context, () => refresh()),
              if (!widget.readOnly) ...[
                const SizedBox(width: 8),
                FilledButton.icon(
                  onPressed: () => _openForm(),
                  icon: const Icon(Icons.add, size: 18),
                  label: const Text('Nouveau'),
                ),
              ],
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: refresh,
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

                final items = snapshot.data ?? [];
                if (items.isEmpty) {
                  return ListView(
                    children: const [
                      Padding(
                        padding: EdgeInsets.all(24),
                        child: Text('Aucun élément.'),
                      ),
                    ],
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final item = items[i] as Map<String, dynamic>;
                    return Card(
                      child: ListTile(
                        onTap: widget.onItemTap == null
                            ? null
                            : () => widget.onItemTap!(context, item),
                        leading:
                            widget.leadingBuilder?.call(context, item) ??
                            Icon(widget.icon, color: AuditronColors.brand700),
                        title: Text(widget.itemTitle(item)),
                        subtitle: widget.itemSubtitle != null
                            ? Text(widget.itemSubtitle!(item) ?? '')
                            : null,
                        trailing: widget.readOnly
                            ? null
                            : Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  if (widget.extraRowActions != null)
                                    ...widget.extraRowActions!(context, item),
                                  IconButton(
                                    icon: const Icon(Icons.edit, size: 20),
                                    onPressed: () => _openForm(item: item),
                                  ),
                                  IconButton(
                                    icon: const Icon(
                                      Icons.delete_outline,
                                      size: 20,
                                    ),
                                    onPressed: () => _delete(item),
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

class _AdminCrudForm extends StatefulWidget {
  final List<AdminFieldSpec> fields;
  final Map<String, dynamic>? initial;

  const _AdminCrudForm({required this.fields, this.initial});

  @override
  State<_AdminCrudForm> createState() => _AdminCrudFormState();
}

class _AdminCrudFormState extends State<_AdminCrudForm> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, dynamic> _values = {};
  final Map<String, TextEditingController> _controllers = {};
  final Map<String, bool> _obscure = {};

  @override
  void initState() {
    super.initState();
    for (final f in widget.fields) {
      final initialValue = widget.initial?[f.key];
      _values[f.key] = f.type == AdminFieldType.checkbox
          ? (initialValue == true)
          : initialValue;
      if (f.type != AdminFieldType.select &&
          f.type != AdminFieldType.checkbox) {
        _controllers[f.key] = TextEditingController(
          text: initialValue?.toString() ?? '',
        );
      }
      if (f.type == AdminFieldType.password) _obscure[f.key] = true;
    }
  }

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  Map<String, dynamic> _buildBody() {
    final body = <String, dynamic>{};
    for (final f in widget.fields) {
      switch (f.type) {
        case AdminFieldType.select:
        case AdminFieldType.checkbox:
          if (_values[f.key] != null) body[f.key] = _values[f.key];
          break;
        case AdminFieldType.number:
          final text = _controllers[f.key]!.text.trim();
          body[f.key] = text.isEmpty ? null : num.tryParse(text);
          break;
        case AdminFieldType.password:
          final text = _controllers[f.key]!.text;
          if (text.isNotEmpty) body[f.key] = text;
          break;
        case AdminFieldType.date:
        case AdminFieldType.text:
          final text = _controllers[f.key]!.text.trim();
          body[f.key] = text.isEmpty ? null : text;
          break;
      }
    }
    return body;
  }

  Future<void> _pickDate(AdminFieldSpec f) async {
    final now = DateTime.now();
    final current = DateTime.tryParse(_controllers[f.key]!.text);
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 5),
    );
    if (picked != null) {
      setState(
        () =>
            _controllers[f.key]!.text = DateFormat('yyyy-MM-dd').format(picked),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final isEdit = widget.initial != null;
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                isEdit ? 'Modifier' : 'Nouveau',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              for (final f in widget.fields) ...[
                _buildField(f),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () {
                    if (_formKey.currentState!.validate()) {
                      Navigator.pop(context, _buildBody());
                    }
                  },
                  child: const Text('Enregistrer'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildField(AdminFieldSpec f) {
    switch (f.type) {
      case AdminFieldType.select:
        return AdminSelectField(
          field: f,
          value: _values[f.key],
          onChanged: (v) => setState(() => _values[f.key] = v),
        );
      case AdminFieldType.checkbox:
        return CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(f.label),
          value: _values[f.key] == true,
          onChanged: (v) => setState(() => _values[f.key] = v ?? false),
        );
      case AdminFieldType.date:
        return TextFormField(
          controller: _controllers[f.key],
          readOnly: true,
          onTap: () => _pickDate(f),
          decoration: InputDecoration(
            labelText: f.label + (f.required ? ' *' : ''),
            suffixIcon: const Icon(Icons.calendar_today, size: 18),
          ),
          validator: f.required
              ? (v) => (v == null || v.isEmpty) ? 'Champ requis' : null
              : null,
        );
      case AdminFieldType.password:
        return TextFormField(
          controller: _controllers[f.key],
          obscureText: _obscure[f.key] ?? true,
          decoration: InputDecoration(
            labelText: f.label,
            helperText: f.helperText,
            suffixIcon: IconButton(
              icon: Icon(
                (_obscure[f.key] ?? true)
                    ? Icons.visibility_off
                    : Icons.visibility,
                size: 18,
              ),
              onPressed: () =>
                  setState(() => _obscure[f.key] = !(_obscure[f.key] ?? true)),
            ),
          ),
        );
      case AdminFieldType.number:
        return TextFormField(
          controller: _controllers[f.key],
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: f.label + (f.required ? ' *' : ''),
          ),
          validator: f.required
              ? (v) => (v == null || v.isEmpty) ? 'Champ requis' : null
              : null,
        );
      case AdminFieldType.text:
        return TextFormField(
          controller: _controllers[f.key],
          decoration: InputDecoration(
            labelText: f.label + (f.required ? ' *' : ''),
            helperText: f.helperText,
          ),
          validator: f.required
              ? (v) => (v == null || v.isEmpty) ? 'Champ requis' : null
              : null,
        );
    }
  }
}
