import 'api_client.dart';

/// Choix et identité de l'établissement, pour l'application unique.
///
/// Une seule app sur le Play Store pour tous les abonnés : l'enseignant
/// désigne son établissement une fois, à l'activation, et le code est ensuite
/// envoyé dans l'en-tête `X-Tenant` de chaque requête (voir [ApiClient]).
///
/// Trois chemins mènent au bon établissement, du plus simple au plus
/// dépannage : la liste publique des abonnés, la saisie du code, et la
/// recherche par numéro de téléphone pour qui a oublié son code. Le QR
/// d'enrôlement affiché par le backoffice encode ce même code.
class EtablissementService {
  EtablissementService._();
  static final EtablissementService instance = EtablissementService._();

  /// Liste publique des établissements abonnés (code, nom, ville, logo).
  ///
  /// Peut être vide — l'éditeur peut choisir de ne pas publier ses clients
  /// (`AUDITRON_CATALOGUE_PUBLIC=false`) : la saisie du code reste alors le
  /// seul chemin, et c'est un cas normal, pas une erreur.
  Future<List<Map<String, dynamic>>> catalogue({String? recherche}) async {
    final reponse = await ApiClient.instance.get(
      '/central/catalogue',
      query: (recherche != null && recherche.isNotEmpty)
          ? {'recherche': recherche}
          : null,
    );

    return _liste(reponse);
  }

  /// Établissements associés à un numéro de téléphone (annuaire central, qui
  /// ne stocke qu'un haché du numéro).
  Future<List<Map<String, dynamic>>> parTelephone(String tel) async {
    final reponse = await ApiClient.instance.post('/central/annuaire/resolve', {
      'tel': tel,
    });

    return _liste(reponse);
  }

  /// Vérifie un code saisi à la main et renvoie le branding de
  /// l'établissement, ou null si le code ne correspond à rien de servi.
  ///
  /// Le code est mémorisé avant l'appel, puisque c'est l'en-tête qui le
  /// transporte ; il est oublié si l'API le refuse, pour ne pas laisser
  /// l'application coincée sur un établissement inexistant.
  Future<Map<String, dynamic>?> verifier(String code) async {
    final normalise = await ApiClient.instance.definirEtablissement(code);

    if (normalise.isEmpty) return null;

    try {
      final reponse = await ApiClient.instance.get('/etablissement');
      final branding = (reponse is Map && reponse['data'] is Map)
          ? Map<String, dynamic>.from(reponse['data'] as Map)
          : null;

      if (branding != null) {
        await ApiClient.instance.definirEtablissement(
          normalise,
          branding: branding,
        );
      }

      return branding;
    } on ApiException {
      await ApiClient.instance.oublierEtablissement();
      rethrow;
    }
  }

  List<Map<String, dynamic>> _liste(dynamic reponse) {
    if (reponse is! Map || reponse['data'] is! List) return [];

    return (reponse['data'] as List)
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }
}
