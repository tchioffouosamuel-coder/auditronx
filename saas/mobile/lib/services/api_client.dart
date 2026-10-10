import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'secure_storage_safe.dart';

/// Point d'accès unique à l'API Auditron X. Attache le token Bearer stocké de
/// façon sécurisée (device_uuid + token émis à l'activation, §4.1) à chaque
/// requête, et lève une [ApiException] normalisée sur toute erreur HTTP.
class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  /// URL unique de l'API, partagée par tous les établissements abonnés :
  /// l'établissement est désigné par l'en-tête `X-Tenant`, jamais par le
  /// domaine. Surchargeable au build pour pointer un environnement de test,
  /// sans variante d'application :
  ///
  ///   flutter run --dart-define=AUDITRON_API_URL=http://10.0.2.2:8000/api
  static const String baseUrl = String.fromEnvironment(
    'AUDITRON_API_URL',
    defaultValue: 'https://api.auditronx.com/public/api',
  );

  /// En-tête qui désigne l'établissement courant (voir [etablissement]).
  static const String enteteEtablissement = 'X-Tenant';

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auditron_token';
  static const _deviceUuidKey = 'auditron_device_uuid';
  static const _etablissementKey = 'auditron_etablissement';
  static const _brandingKey = 'auditron_etablissement_branding';

  /// Token gardé en mémoire dès qu'il est connu : les requêtes ne dépendent
  /// plus d'une relecture du stockage sécurisé, qui peut échouer sur certains
  /// téléphones (voir `readOrNull`) — la requête partait alors sans token et
  /// le 401 du serveur s'affichait comme une session expirée.
  String? _cachedToken;

  /// Appelé quand l'API refuse la session (401) : [Session] s'y abonne pour
  /// renvoyer vers l'écran de connexion. Sans ça, l'app restait sur l'écran
  /// principal sans token, à répéter « Session expirée » à chaque action.
  void Function()? onSessionExpired;

  /// Appelé quand l'établissement mémorisé n'est plus servi (code devenu
  /// invalide, abonnement suspendu) : l'app doit redemander le choix de
  /// l'établissement, sinon l'utilisateur reste bloqué sur une erreur qu'aucun
  /// écran ne lui permet de corriger.
  void Function(String message)? onEtablissementInvalide;

  /// Code de l'établissement, gardé en mémoire pour la même raison que le
  /// token : une relecture du stockage sécurisé peut échouer sur certains
  /// téléphones, et une requête partie sans en-tête se verrait refusée avec
  /// un « Établissement non précisé » incompréhensible pour l'utilisateur.
  String? _cachedEtablissement;

  Future<String?> get token async =>
      _cachedToken ??= await _storage.readOrNull(_tokenKey);
  Future<String?> get deviceUuid => _storage.readOrNull(_deviceUuidKey);

  Future<String?> get etablissement async =>
      _cachedEtablissement ??= await _storage.readOrNull(_etablissementKey);

  /// Mémorise l'établissement choisi à l'activation. Normalise le code comme
  /// le fait l'API (majuscules, alphabet restreint) pour qu'une saisie
  /// « ltm » ou « LTM » aboutisse au même endroit.
  Future<String> definirEtablissement(
    String code, {
    Map<String, dynamic>? branding,
  }) async {
    final normalise = normaliserCodeEtablissement(code);

    if (normalise.isEmpty) return '';

    _cachedEtablissement = normalise;
    await _storage.write(key: _etablissementKey, value: normalise);

    if (branding != null) {
      await _storage.write(key: _brandingKey, value: jsonEncode(branding));
    }

    return normalise;
  }

  /// Branding mis en cache : affiche l'écran de connexion aux couleurs de
  /// l'établissement sans attendre le réseau.
  Future<Map<String, dynamic>?> get brandingEnCache async {
    final brut = await _storage.readOrNull(_brandingKey);

    if (brut == null) return null;

    try {
      return jsonDecode(brut) as Map<String, dynamic>;
    } catch (_) {
      await _storage.delete(key: _brandingKey);
      return null;
    }
  }

  /// Changer d'établissement invalide la session : les tokens vivent dans la
  /// base de chaque établissement et ne valent rien ailleurs.
  Future<void> oublierEtablissement() async {
    _cachedEtablissement = null;
    await _storage.delete(key: _etablissementKey);
    await _storage.delete(key: _brandingKey);
    await clearSession();
  }

  static String normaliserCodeEtablissement(String code) =>
      code.toUpperCase().replaceAll(RegExp(r'[^A-Z0-9_-]'), '');

  Future<void> saveSession({
    required String token,
    required String deviceUuid,
  }) async {
    _cachedToken = token;
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _deviceUuidKey, value: deviceUuid);
  }

  Future<void> clearSession() async {
    _cachedToken = null;
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _deviceUuidKey);
  }

  Future<bool> get isActivated async => (await token) != null;

  Future<Map<String, String>> _headers() async {
    final t = await token;
    final e = await etablissement;
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (t != null) 'Authorization': 'Bearer $t',
      if (e != null) enteteEtablissement: e,
    };
  }

  Future<bool> hasConnectivity() async {
    final results = await Connectivity().checkConnectivity();
    return !results.contains(ConnectivityResult.none);
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) async {
    final uri = Uri.parse('$baseUrl$path').replace(queryParameters: query);
    final headers = await _headers();
    final response = await _guarded(() => http.get(uri, headers: headers));
    return _decode(response, 'GET $path', headers);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final uri = Uri.parse('$baseUrl$path');
    final headers = await _headers();
    final response = await _guarded(
      () => http.post(uri, headers: headers, body: jsonEncode(body)),
    );
    return _decode(response, 'POST $path', headers);
  }

  Future<dynamic> put(String path, Map<String, dynamic> body) async {
    final uri = Uri.parse('$baseUrl$path');
    final headers = await _headers();
    final response = await _guarded(
      () => http.put(uri, headers: headers, body: jsonEncode(body)),
    );
    return _decode(response, 'PUT $path', headers);
  }

  /// Convertit tout échec réseau bas niveau (pas de DNS/internet, timeout,
  /// TLS...) en [ApiException] — sans ça, ces erreurs remontent comme des
  /// exceptions non gérées (SocketException...) que les écrans n'attrapent
  /// pas puisqu'ils ne catchent que ApiException.
  Future<http.Response> _guarded(
    Future<http.Response> Function() request,
  ) async {
    try {
      if (!await hasConnectivity()) {
        throw ApiException(
          "Pas de connexion internet. Vérifiez votre réseau et réessayez.",
          0,
        );
      }
      return await request().timeout(const Duration(seconds: 20));
    } on ApiException {
      rethrow;
    } on TimeoutException {
      throw ApiException(
        "Connexion au serveur trop lente. L'action sera synchronisée dès que possible.",
        0,
      );
    } on SocketException {
      if (await hasConnectivity()) {
        throw ApiException(
          "Le serveur est temporairement inaccessible. Vérifiez votre connexion et réessayez.",
          0,
        );
      }
      throw ApiException(
        "Pas de connexion internet. Vérifiez votre réseau et réessayez.",
        0,
      );
    } on HttpException {
      throw ApiException(
        "Le serveur n'a pas répondu correctement. Réessayez.",
        0,
      );
    } on http.ClientException {
      if (await hasConnectivity()) {
        throw ApiException(
          "Le serveur est temporairement inaccessible. Vérifiez votre connexion et réessayez.",
          0,
        );
      }
      throw ApiException(
        "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.",
        0,
      );
    }
  }

  dynamic _decode(
    http.Response response,
    String request,
    Map<String, String> headers,
  ) {
    final body = response.body.isEmpty ? null : jsonDecode(response.body);

    if (response.statusCode == 401) {
      final tokenSent = headers.containsKey('Authorization');
      debugPrint(
        '[api] 401 sur $request (token ${tokenSent ? 'envoyé' : 'absent'})',
      );
      clearSession();
      onSessionExpired?.call();
      throw ApiException(
        tokenSent
            ? 'Session expirée, merci de vous reconnecter.'
            : 'Session introuvable sur ce téléphone, merci de vous reconnecter.',
        response.statusCode,
      );
    }

    final motif = body is Map ? body['erreur'] : null;

    if (motif == 'etablissement_inconnu' ||
        motif == 'etablissement_absent' ||
        response.statusCode == 402) {
      final message = response.statusCode == 402
          ? "L'abonnement de votre établissement est suspendu. Contactez votre administration."
          : "Établissement introuvable : choisissez à nouveau votre établissement.";

      debugPrint('[api] établissement refusé sur $request ($motif)');
      unawaited(oublierEtablissement());
      onEtablissementInvalide?.call(message);

      throw ApiException(message, response.statusCode);
    }

    if (response.statusCode >= 400) {
      final message = body is Map && body['message'] != null
          ? body['message'] as String
          : 'Une erreur est survenue.';
      throw ApiException(
        message,
        response.statusCode,
        errors: body is Map ? body['errors'] : null,
      );
    }

    return body;
  }
}

class ApiException implements Exception {
  final String message;
  final int statusCode;
  final dynamic errors;

  ApiException(this.message, this.statusCode, {this.errors});

  @override
  String toString() => message;
}
