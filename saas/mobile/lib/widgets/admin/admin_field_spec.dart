/// Type de champ pour un formulaire généré par [AdminCrudScreen] — miroir
/// simplifié des `type` supportés par `ResourceTable.jsx` côté web.
enum AdminFieldType { text, number, date, time, select, checkbox, password }

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

  /// Éclate la valeur choisie en plusieurs clés JSON au lieu de la seule
  /// [key]. Une période de cours est un couple début/fin : la choisir doit
  /// renseigner `heure_debut` et `heure_fin` d'un coup, deux listes
  /// indépendantes laissant composer des créneaux impossibles.
  final Map<String, dynamic> Function(dynamic value)? expandValue;

  /// Reconstruit la valeur du champ à partir de l'élément édité, quand elle ne
  /// se lit pas simplement sur [key] (pendant de [expandValue]).
  final dynamic Function(Map<String, dynamic> item)? valueFrom;

  /// Valeur pré-remplie à la création d'un nouvel élément (ignorée en
  /// édition, où [valueFrom] ou [key] priment).
  final dynamic defaultValue;

  /// Clés d'autres champs du même formulaire dont un changement recalcule
  /// celui-ci via [compute]. Un champ qui en déclare un devient lecture
  /// seule : sa valeur ne se saisit plus, elle se déduit (ex. l'heure de fin,
  /// déduite du début et d'un nombre de périodes).
  final List<String>? recomputeFrom;

  /// Recalcule la valeur texte de ce champ à partir des valeurs texte
  /// actuelles des autres champs du formulaire, indexées par clé. Appelé à
  /// chaque changement d'un champ listé dans [recomputeFrom], et à
  /// l'ouverture du formulaire.
  final String Function(Map<String, String> valeurs)? compute;

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
    this.expandValue,
    this.valueFrom,
    this.defaultValue,
    this.recomputeFrom,
    this.compute,
  });
}
