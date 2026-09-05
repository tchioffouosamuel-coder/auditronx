import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Point d'accès unique à l'API Auditron X. Attache le token Bearer stocké de
/// façon sécurisée (device_uuid + token émis à l'activation, §4.1) à chaque
/// requête, et lève une [ApiException] normalisée sur toute erreur HTTP.
class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  static const String baseUrl = 'https://api-ltm.auditronx.com/public/api';

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auditron_token';
  static const _deviceUuidKey = 'auditron_device_uuid';

  Future<String?> get token => _storage.read(key: _tokenKey);
  Future<String?> get deviceUuid => _storage.read(key: _deviceUuidKey);

  Future<void> saveSession({required String token, required String deviceUuid}) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _deviceUuidKey, value: deviceUuid);
  }

  Future<void> clearSession() async {
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
    final response = await _guarded(() async => http.get(uri, headers: await _headers()));
    return _decode(response);
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final uri = Uri.parse('$baseUrl$path');
    final response = await _guarded(
      () async => http.post(uri, headers: await _headers(), body: jsonEncode(body)),
    );
    return _decode(response);
  }

  /// Convertit tout échec réseau bas niveau (pas de DNS/internet, timeout,
  /// TLS...) en [ApiException] — sans ça, ces erreurs remontent comme des
  /// exceptions non gérées (SocketException...) que les écrans n'attrapent
  /// pas puisqu'ils ne catchent que ApiException.
  Future<http.Response> _guarded(Future<http.Response> Function() request) async {
    try {
      return await request();
    } on SocketException {
      throw ApiException("Pas de connexion internet. Vérifiez votre réseau et réessayez.", 0);
    } on HttpException {
      throw ApiException("Le serveur n'a pas répondu correctement. Réessayez.", 0);
    } on http.ClientException {
      throw ApiException("Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.", 0);
    }
  }

  dynamic _decode(http.Response response) {
    final body = response.body.isEmpty ? null : jsonDecode(response.body);

    if (response.statusCode == 401) {
      clearSession();
      throw ApiException('Session expirée, merci de vous réactiver.', response.statusCode);
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
