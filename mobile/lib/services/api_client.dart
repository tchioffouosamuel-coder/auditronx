import 'dart:async';
import 'dart:convert';
import 'dart:io';
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

  static const String baseUrl = 'https://api.auditronx.com/public/api';

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auditron_token';
  static const _deviceUuidKey = 'auditron_device_uuid';

  /// Token gardé en mémoire dès qu'il est connu : les requêtes ne dépendent
  /// plus d'une relecture du stockage sécurisé, qui peut échouer sur certains
  /// téléphones (voir `readOrNull`) — la requête partait alors sans token et
  /// le 401 du serveur s'affichait comme une session expirée.
  String? _cachedToken;

  /// Appelé quand l'API refuse la session (401) : [Session] s'y abonne pour
  /// renvoyer vers l'écran de connexion. Sans ça, l'app restait sur l'écran
  /// principal sans token, à répéter « Session expirée » à chaque action.
  void Function()? onSessionExpired;

  Future<String?> get token async =>
      _cachedToken ??= await _storage.readOrNull(_tokenKey);
  Future<String?> get deviceUuid => _storage.readOrNull(_deviceUuidKey);

  Future<void> saveSession({required String token, required String deviceUuid}) async {
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
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (t != null) 'Authorization': 'Bearer $t',
    };
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
  Future<http.Response> _guarded(Future<http.Response> Function() request) async {
    try {
      return await request().timeout(const Duration(seconds: 20));
    } on TimeoutException {
      throw ApiException("Connexion au serveur trop lente. L'action sera synchronisée dès que possible.", 0);
    } on SocketException {
      throw ApiException("Pas de connexion internet. Vérifiez votre réseau et réessayez.", 0);
    } on HttpException {
      throw ApiException("Le serveur n'a pas répondu correctement. Réessayez.", 0);
    } on http.ClientException {
      throw ApiException("Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.", 0);
    }
  }

  dynamic _decode(http.Response response, String request, Map<String, String> headers) {
    final body = response.body.isEmpty ? null : jsonDecode(response.body);

    if (response.statusCode == 401) {
      final tokenSent = headers.containsKey('Authorization');
      debugPrint('[api] 401 sur $request (token ${tokenSent ? 'envoyé' : 'absent'})');
      clearSession();
      onSessionExpired?.call();
      throw ApiException(
        tokenSent
            ? 'Session expirée, merci de vous reconnecter.'
            : 'Session introuvable sur ce téléphone, merci de vous reconnecter.',
        response.statusCode,
      );
    }

    if (response.statusCode >= 400) {
      final message = body is Map && body['message'] != null
          ? body['message'] as String
          : 'Une erreur est survenue.';
      throw ApiException(message, response.statusCode, errors: body is Map ? body['errors'] : null);
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
