import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import 'api_client.dart';
import 'secure_storage_safe.dart';

/// Session et API du personnel Auditron, distinctes des comptes établissement.
class PlateformeApiClient {
  PlateformeApiClient._();
  static final PlateformeApiClient instance = PlateformeApiClient._();

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auditron_plateforme_token';
  static const _idKey = 'auditron_plateforme_id';
  static const _nomKey = 'auditron_plateforme_nom';
  static const _roleKey = 'auditron_plateforme_role';
  void Function()? onSessionExpired;

  Future<String?> get token => _storage.readOrNull(_tokenKey);
  Future<String?> get userId => _storage.readOrNull(_idKey);
  Future<String?> get nom => _storage.readOrNull(_nomKey);
  Future<String?> get role => _storage.readOrNull(_roleKey);
  Future<bool> get isLoggedIn async => (await token) != null;

  Future<Map<String, String>> _headers({bool json = true}) async {
    final currentToken = await token;
    return {
      'Accept': 'application/json',
      if (json) 'Content-Type': 'application/json',
      if (currentToken != null) 'Authorization': 'Bearer $currentToken',
    };
  }

  Object? _decode(String body) {
    if (body.isEmpty) return null;
    try {
      return jsonDecode(body);
    } on FormatException {
      return null;
    }
  }

  Never _throwResponse(http.Response response) {
    final body = _decode(response.body);
    final message = body is Map && body['message'] is String
        ? body['message'] as String
        : 'Une erreur est survenue (${response.statusCode}).';
    throw ApiException(message, response.statusCode);
  }

  Uri _uri(String path, [Map<String, String>? query]) => Uri.parse(
    '${ApiClient.baseUrl}/central$path',
  ).replace(queryParameters: query);

  Future<String> connexion(String email, String motDePasse) async {
    final response = await http.post(
      _uri('/login'),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'email': email, 'password': motDePasse}),
    );
    final body = _decode(response.body);
    if (response.statusCode >= 400 || body is! Map) {
      _throwResponse(response);
    }

    final utilisateur = body['user'] as Map<String, dynamic>? ?? const {};
    final accessToken = body['token'];
    if (accessToken is! String || accessToken.isEmpty) {
      throw ApiException('Réponse de connexion invalide.', response.statusCode);
    }

    await _storage.write(key: _tokenKey, value: accessToken);
    await _storage.write(
      key: _idKey,
      value: utilisateur['id']?.toString() ?? '',
    );
    await _storage.write(
      key: _nomKey,
      value: utilisateur['name'] as String? ?? email,
    );
    await _storage.write(
      key: _roleKey,
      value: utilisateur['role'] as String? ?? '',
    );
    return utilisateur['name'] as String? ?? email;
  }

  Future<void> deconnexion({bool revoke = false}) async {
    if (revoke) {
      final currentToken = await token;
      if (currentToken != null) {
        await http.post(_uri('/logout'), headers: await _headers());
      }
    }
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _idKey);
    await _storage.delete(key: _nomKey);
    await _storage.delete(key: _roleKey);
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) async {
    final response = await http.get(
      _uri(path, query),
      headers: await _headers(),
    );
    return _handleJson(response);
  }

  Future<dynamic> post(String path, [Map<String, dynamic>? data]) async {
    final response = await http.post(
      _uri(path),
      headers: await _headers(),
      body: jsonEncode(data ?? const {}),
    );
    return _handleJson(response);
  }

  Future<dynamic> put(String path, Map<String, dynamic> data) async {
    final response = await http.put(
      _uri(path),
      headers: await _headers(),
      body: jsonEncode(data),
    );
    return _handleJson(response);
  }

  Future<dynamic> delete(String path, {Map<String, dynamic>? data}) async {
    final response = await http.delete(
      _uri(path),
      headers: await _headers(),
      body: data == null ? null : jsonEncode(data),
    );
    return _handleJson(response);
  }

  Future<Uint8List> facturePdf(String factureId) async {
    final response = await http.get(
      _uri('/factures/$factureId/pdf'),
      headers: await _headers(json: false),
    );
    if (response.statusCode == 401) {
      await deconnexion();
      onSessionExpired?.call();
      throw ApiException('Session expirée, reconnectez-vous.', 401);
    }
    if (response.statusCode >= 400) _throwResponse(response);
    return response.bodyBytes;
  }

  Future<dynamic> _handleJson(http.Response response) async {
    if (response.statusCode == 401) {
      await deconnexion();
      onSessionExpired?.call();
      throw ApiException('Session expirée, reconnectez-vous.', 401);
    }
    if (response.statusCode >= 400) _throwResponse(response);

    final body = _decode(response.body);
    if (response.body.isNotEmpty && body == null) {
      throw ApiException('Réponse serveur invalide.', response.statusCode);
    }
    return body;
  }
}
