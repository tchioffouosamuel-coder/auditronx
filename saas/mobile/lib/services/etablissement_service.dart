import 'api_client.dart';

/// Choix et identité de l'établissement, pour l'application unique.
///
/// Une seule app sur le Play Store pour tous les abonnés : l'enseignant
/// désigne son établissement une fois, à l'activation, et le code est ensuite
/// envoyé dans l'en-tête `X-Tenant` de chaque requête (voir [ApiClient]).
///
/// Le code est saisi manuellement ou lu depuis le QR d'enrôlement affiché par
/// le backoffice. Le catalogue public et la recherche inversée par téléphone
/// ne sont pas utilisés par l'application, afin de ne pas exposer la liste des
/// clients sur l'écran de connexion.
class EtablissementService {
  EtablissementService._();
  static final EtablissementService instance = EtablissementService._();

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
      } else {
        await ApiClient.instance.oublierEtablissement();
      }

      return branding;
    } on ApiException {
      await ApiClient.instance.oublierEtablissement();
      rethrow;
    }
  }
}
