import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import 'api_client.dart';

/// Identité telle que la borne la rapporte.
class BorneEtat {
  final String firmware;
  final bool provisionne;
  final String tenant;
  final String tokenMasque;
  final String ssid;
  final String label;
  final String adresseBle;
  final int fileEnAttente;
  final String stockage;

  BorneEtat({
    required this.firmware,
    required this.provisionne,
    required this.tenant,
    required this.tokenMasque,
    required this.ssid,
    required this.label,
    required this.adresseBle,
    required this.fileEnAttente,
    required this.stockage,
  });

  factory BorneEtat.depuisJson(Map<String, dynamic> j) => BorneEtat(
    firmware: j['firmware'] as String? ?? '?',
    provisionne: j['provisionne'] as bool? ?? false,
    tenant: j['tenant'] as String? ?? '',
    tokenMasque: j['token_masque'] as String? ?? '',
    ssid: j['ssid'] as String? ?? '',
    label: j['label'] as String? ?? '',
    adresseBle: j['adresse_ble'] as String? ?? '',
    fileEnAttente: (j['file_en_attente'] as num?)?.toInt() ?? 0,
    stockage: j['stockage'] as String? ?? '?',
  );
}

/// Dialogue avec une borne par son point d'accès WiFi.
///
/// La borne ouvre un réseau « Borne Auditron » pendant les premières minutes
/// après sa mise sous tension (sans limite tant qu'elle n'est pas
/// provisionnée) et sert une petite API sur http://192.168.4.1. C'est la seule
/// voie pour écrire sa mémoire sans ouvrir le boîtier : son port série est
/// l'autre, et elle demande un ordinateur.
///
/// Client volontairement séparé des trois autres : on parle ici à un appareil
/// sur le réseau local, pas à l'API Auditron. Les messages d'erreur de
/// [ApiClient] (« Pas de connexion internet ») répondraient à côté — quand le
/// téléphone est sur l'AP de la borne, il n'a justement pas d'internet, et
/// c'est normal.
class BorneConfigClient {
  BorneConfigClient._();
  static final BorneConfigClient instance = BorneConfigClient._();

  /// Adresse par défaut du mode point d'accès de l'ESP32.
  static const String base = 'http://192.168.4.1';

  /// Nom du réseau ouvert par la borne, affiché à l'utilisateur.
  static const String ssidAp = 'Borne Auditron';

  static const Duration _delai = Duration(seconds: 8);

  Future<BorneEtat> lireEtat() async {
    final reponse = await _protege(
      () => http.get(
        Uri.parse('$base/config'),
        headers: const {'Accept': 'application/json'},
      ),
    );

    final corps = jsonDecode(reponse.body);

    if (corps is! Map<String, dynamic>) {
      throw ApiException('Réponse inattendue de la borne.', 0);
    }

    return BorneEtat.depuisJson(corps);
  }

  /// Écrit les champs fournis et provoque le redémarrage de la borne.
  ///
  /// [confirmeSensible] doit valoir true dès que [tenant] ou [token] est
  /// fourni : la borne refuse ces deux champs sans cette confirmation. C'est
  /// un garde-fou contre un client fautif, pas une autorisation — la borne est
  /// hors ligne sur son propre réseau et ne peut vérifier aucun compte.
  Future<List<String>> ecrire({
    String? ssid,
    String? motDePasseWifi,
    String? tenant,
    String? token,
    String? label,
    bool confirmeSensible = false,
  }) async {
    final charge = <String, dynamic>{
      if (ssid != null && ssid.isNotEmpty) 'ssid': ssid,
      if (motDePasseWifi != null && motDePasseWifi.isNotEmpty)
        'wifi': motDePasseWifi,
      if (tenant != null && tenant.isNotEmpty) 'tenant': tenant,
      if (token != null && token.isNotEmpty) 'token': token,
      if (label != null && label.isNotEmpty) 'label': label,
      if (confirmeSensible) 'confirme_sensible': true,
    };

    final reponse = await _protege(
      () => http.post(
        Uri.parse('$base/config'),
        headers: const {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        body: jsonEncode(charge),
      ),
    );

    final corps = jsonDecode(reponse.body);

    if (corps is Map && corps['champs'] is List) {
      return (corps['champs'] as List).cast<String>();
    }

    return const [];
  }

  /// Centralise les pannes de liaison avec un message qui dit quoi faire.
  Future<http.Response> _protege(
    Future<http.Response> Function() requete,
  ) async {
    try {
      final reponse = await requete().timeout(_delai);

      if (reponse.statusCode >= 400) {
        final corps = reponse.body.isEmpty ? null : jsonDecode(reponse.body);
        throw ApiException(
          corps is Map && corps['erreur'] != null
              ? corps['erreur'] as String
              : 'La borne a refusé la requête.',
          reponse.statusCode,
        );
      }

      return reponse;
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw ApiException(
        'La borne ne répond pas. Son point d’accès se referme après quelques '
        'minutes : coupez puis rétablissez son alimentation, puis '
        'reconnectez-vous au réseau « $ssidAp ».',
        0,
      );
    } on SocketException {
      throw ApiException(
        'Borne introuvable. Vérifiez que le téléphone est bien connecté au '
        'réseau WiFi « $ssidAp » et non à un autre réseau.',
        0,
      );
    } on http.ClientException {
      throw ApiException(
        'Liaison interrompue avec la borne. Rapprochez-vous et réessayez.',
        0,
      );
    }
  }
}
