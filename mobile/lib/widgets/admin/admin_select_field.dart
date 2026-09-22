import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import 'admin_field_spec.dart';

/// Cache mémoire très simple des options déjà chargées par endpoint —
/// évite de refaire l'appel réseau à chaque ouverture de formulaire dans le
/// même écran (Emplois, Signalements, Programmes... rouvrent souvent le même
/// sélecteur classe/discipline/enseignant).
class _OptionsCache {
  static final Map<String, List<Map<String, dynamic>>> _cache = {};

  static Future<List<Map<String, dynamic>>> load(String endpoint) async {
    if (_cache.containsKey(endpoint)) return _cache[endpoint]!;
    final data = await OfflineCache.instance.readThrough(
      'admin_options_$endpoint',
      () => AdminApiClient.instance.get(endpoint),
    );
    final list = data is Map && data['data'] is List
        ? (data['data'] as List)
        : (data is List ? data : const []);
    final options = list.cast<Map<String, dynamic>>();
    _cache[endpoint] = options;
    return options;
  }

  static void invalidate(String endpoint) => _cache.remove(endpoint);
}

/// Dropdown asynchrone : charge ses options depuis [field.optionsEndpoint]
/// (ou utilise [field.options] si statique), équivalent Flutter du `select`
/// à `optionsUrl` de `ResourceTable.jsx`.
class AdminSelectField extends StatelessWidget {
  final AdminFieldSpec field;
  final dynamic value;
  final ValueChanged<dynamic> onChanged;

  const AdminSelectField({
    super.key,
    required this.field,
    required this.value,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    if (field.options != null) {
      var selectedValue = value;
      if (field.key == 'section' && value is String) {
        final normalized = value.trim().toLowerCase();
        if (normalized == 'enseignement général' ||
            normalized == 'enseignement general') {
          selectedValue = field.options!
              .where((option) => option.value == 'Générale')
              .firstOrNull
              ?.value;
        }
      }
      final matchingItems = field.options!
          .where((option) => option.value == selectedValue)
          .length;
      return _FormSearchableSelect(
        initialValue: matchingItems == 1 ? selectedValue : null,
        label: field.label + (field.required ? ' *' : ''),
        options: field.options!,
        onChanged: onChanged,
        validator: field.required
            ? (selected) => selected == null ? 'Champ requis' : null
            : null,
      );
    }

    final endpoint = field.optionsEndpoint!;
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _OptionsCache.load(endpoint),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return InputDecorator(
            decoration: InputDecoration(
              labelText: field.label + (field.required ? ' *' : ''),
            ),
            child: const SizedBox(
              height: 20,
              child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
            ),
          );
        }
        final items = snapshot.data!;
        final currentValid = items.any(
          (i) => (field.optionValue?.call(i) ?? i['id']) == value,
        );
        return _FormSearchableSelect(
          initialValue: currentValid ? value : null,
          label: field.label + (field.required ? ' *' : ''),
          options: items
              .map(
                (item) => AdminFieldOption(
                  field.optionValue?.call(item) ?? item['id'],
                  field.optionLabel?.call(item) ??
                      (item['nom']?.toString() ??
                          item['label']?.toString() ??
                          '—'),
                ),
              )
              .toList(),
          onChanged: onChanged,
          validator: field.required
              ? (selected) => selected == null ? 'Champ requis' : null
              : null,
        );
      },
    );
  }

  static void invalidateCache(String endpoint) =>
      _OptionsCache.invalidate(endpoint);
}

class _FormSearchableSelect extends StatelessWidget {
  final dynamic initialValue;
  final String label;
  final List<AdminFieldOption> options;
  final ValueChanged<dynamic> onChanged;
  final String? Function(dynamic)? validator;

  const _FormSearchableSelect({
    required this.initialValue,
    required this.label,
    required this.options,
    required this.onChanged,
    this.validator,
  });

  @override
  Widget build(BuildContext context) {
    return FormField<dynamic>(
      initialValue: initialValue,
      validator: validator,
      builder: (state) => AdminSearchableSelect(
        value: state.value,
        label: label,
        options: options,
        errorText: state.errorText,
        onChanged: (selected) {
          state.didChange(selected);
          onChanged(selected);
        },
      ),
    );
  }
}

class AdminSearchableSelect extends StatelessWidget {
  final dynamic value;
  final String label;
  final List<AdminFieldOption> options;
  final String? errorText;
  final ValueChanged<dynamic> onChanged;

  const AdminSearchableSelect({
    super.key,
    required this.value,
    required this.label,
    required this.options,
    required this.onChanged,
    this.errorText,
  });

  Future<void> _openPicker(BuildContext context) async {
    final selected = await showModalBottomSheet<dynamic>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _SearchableOptionsSheet(
        label: label,
        options: options,
        selectedValue: value,
      ),
    );
    if (selected != null) onChanged(selected);
  }

  @override
  Widget build(BuildContext context) {
    final selectedLabel = options
        .where((option) => option.value == value)
        .map((option) => option.label)
        .firstOrNull;
    return InkWell(
      onTap: () => _openPicker(context),
      borderRadius: BorderRadius.circular(4),
      child: InputDecorator(
        isEmpty: selectedLabel == null,
        decoration: InputDecoration(
          labelText: label,
          errorText: errorText,
          suffixIcon: const Icon(Icons.keyboard_arrow_down),
        ),
        child: Text(
          selectedLabel ?? 'Sélectionner',
          overflow: TextOverflow.ellipsis,
          style: selectedLabel == null
              ? TextStyle(color: Theme.of(context).hintColor)
              : null,
        ),
      ),
    );
  }
}

class _SearchableOptionsSheet extends StatefulWidget {
  final String label;
  final List<AdminFieldOption> options;
  final dynamic selectedValue;

  const _SearchableOptionsSheet({
    required this.label,
    required this.options,
    required this.selectedValue,
  });

  @override
  State<_SearchableOptionsSheet> createState() =>
      _SearchableOptionsSheetState();
}

class _SearchableOptionsSheetState extends State<_SearchableOptionsSheet> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = _searchController.text.trim().toLowerCase();
    final filteredOptions = widget.options
        .where((option) => option.label.toLowerCase().contains(query))
        .toList();
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: SafeArea(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxHeight: 560),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: TextField(
                  controller: _searchController,
                  autofocus: true,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: 'Rechercher ${widget.label.toLowerCase()}',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                            },
                          ),
                  ),
                ),
              ),
              Flexible(
                child: filteredOptions.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.all(24),
                        child: Text('Aucun résultat'),
                      )
                    : ListView.builder(
                        shrinkWrap: true,
                        itemCount: filteredOptions.length,
                        itemBuilder: (context, index) {
                          final option = filteredOptions[index];
                          return ListTile(
                            title: Text(option.label),
                            trailing: option.value == widget.selectedValue
                                ? const Icon(Icons.check)
                                : null,
                            onTap: () => Navigator.pop(context, option.value),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
