/// Type de champ pour un formulaire généré par [AdminCrudScreen] — miroir
/// simplifié des `type` supportés par `ResourceTable.jsx` côté web.
enum AdminFieldType { text, number, date, select, checkbox, password }

/// Une option statique pour un champ [AdminFieldType.select].
class AdminFieldOption {
  final dynamic value;
  final String label;
  const AdminFieldOption(this.value, this.label);
}

/// Description d'un champ de formulaire admin, utilisée à la fois pour
/// générer le formulaire de création/édition et pour construire le corps de
/// la requête envoyée à l'API (clé JSON = [key]).
class AdminFieldSpec {
  final String key;
  final String label;
  final AdminFieldType type;
  final bool required;

  /// Options statiques (ex: jours de la semaine). Ignoré si [optionsEndpoint]
  /// est renseigné.
  final List<AdminFieldOption>? options;

  /// Endpoint GET (chemin complet, query string incluse si besoin, ex.
  /// `/personnel?per_page=500`) dont la réponse (liste ou `{data: [...]}`)
  /// alimente un champ [AdminFieldType.select] de façon asynchrone.
  final String? optionsEndpoint;

  /// Extrait la valeur (id) d'un élément renvoyé par [optionsEndpoint].
  final dynamic Function(Map<String, dynamic> item)? optionValue;

  /// Extrait le libellé affiché d'un élément renvoyé par [optionsEndpoint].
  final String Function(Map<String, dynamic> item)? optionLabel;

  /// Aide affichée sous le champ (ex: "laisser vide pour ne pas changer").
  final String? helperText;

  const AdminFieldSpec({
    required this.key,
    required this.label,
    this.type = AdminFieldType.text,
    this.required = false,
    this.options,
    this.optionsEndpoint,
    this.optionValue,
    this.optionLabel,
    this.helperText,
  });
}
