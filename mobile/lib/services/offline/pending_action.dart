/// Qui rejoue l'action à la synchro (§offline-sync) — chaque mode d'auth a son
/// propre client HTTP/token (voir [ApiClient] et [AdminApiClient]).
enum AuthMode { teacher, admin }

/// Une action d'écriture mise en file d'attente faute de réseau au moment où
/// l'utilisateur l'a déclenchée (§offline-sync). Rejouée dans l'ordre de
/// création dès le retour de la connectivité — "dernier écrit gagne" : pas de
/// fusion, l'action est simplement envoyée telle quelle, quel que soit l'état
/// du serveur entre-temps.
class PendingAction {
  final String id;
  final AuthMode authMode;
  final String path;
  final Map<String, dynamic> body;
  final String label;
  final DateTime createdAt;

  PendingAction({
    required this.id,
    required this.authMode,
    required this.path,
    required this.body,
    required this.label,
    required this.createdAt,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'authMode': authMode.name,
        'path': path,
        'body': body,
        'label': label,
        'createdAt': createdAt.toIso8601String(),
      };

  factory PendingAction.fromJson(Map<String, dynamic> json) => PendingAction(
        id: json['id'] as String,
        authMode: AuthMode.values.byName(json['authMode'] as String),
        path: json['path'] as String,
        body: Map<String, dynamic>.from(json['body'] as Map),
        label: json['label'] as String,
        createdAt: DateTime.parse(json['createdAt'] as String),
      );
}
