import 'package:flutter/material.dart';
import '../../services/admin_api_client.dart';
import 'admin_field_spec.dart';

/// Cache mémoire très simple des options déjà chargées par endpoint —
/// évite de refaire l'appel réseau à chaque ouverture de formulaire dans le
/// même écran (Emplois, Signalements, Programmes... rouvrent souvent le même
/// sélecteur classe/discipline/enseignant).
class _OptionsCache {
  static final Map<String, List<Map<String, dynamic>>> _cache = {};

  static Future<List<Map<String, dynamic>>> load(String endpoint) async {
    if (_cache.containsKey(endpoint)) return _cache[endpoint]!;
    final data = await AdminApiClient.instance.get(endpoint);
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

  const AdminSelectField({super.key, required this.field, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    if (field.options != null) {
      return DropdownButtonFormField<dynamic>(
        initialValue: value,
        decoration: InputDecoration(labelText: field.label + (field.required ? ' *' : '')),
        items: field.options!
            .map((o) => DropdownMenuItem(value: o.value, child: Text(o.label)))
            .toList(),
        onChanged: onChanged,
        validator: field.required ? (v) => v == null ? 'Champ requis' : null : null,
      );
    }

    final endpoint = field.optionsEndpoint!;
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: _OptionsCache.load(endpoint),
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return InputDecorator(
            decoration: InputDecoration(labelText: field.label + (field.required ? ' *' : '')),
            child: const SizedBox(height: 20, child: Center(child: CircularProgressIndicator(strokeWidth: 2))),
          );
        }
        final items = snapshot.data!;
        final currentValid = items.any((i) => (field.optionValue?.call(i) ?? i['id']) == value);
        return DropdownButtonFormField<dynamic>(
          initialValue: currentValid ? value : null,
          decoration: InputDecoration(labelText: field.label + (field.required ? ' *' : '')),
          items: items
              .map((i) => DropdownMenuItem(
                    value: field.optionValue?.call(i) ?? i['id'],
                    child: Text(field.optionLabel?.call(i) ?? (i['nom']?.toString() ?? i['label']?.toString() ?? '—')),
                  ))
              .toList(),
          onChanged: onChanged,
          validator: field.required ? (v) => v == null ? 'Champ requis' : null : null,
        );
      },
    );
  }

  static void invalidateCache(String endpoint) => _OptionsCache.invalidate(endpoint);
}
