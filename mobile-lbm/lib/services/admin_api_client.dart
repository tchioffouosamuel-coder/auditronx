import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_client.dart';

/// Client API dédié à l'espace admin (§admin-mobile), distinct de [ApiClient]
/// (enseignant/kiosque) : authentification par email/mot de passe contre le
/// modèle `User` du backoffice (`POST /api/login`), token stocké sous des clés
/// séparées pour que les deux sessions (enseignant et admin) puissent
/// coexister sans se marcher dessus sur le même téléphone.
class AdminApiClient {
  AdminApiClient._();
  static final AdminApiClient instance = AdminApiClient._();

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auditron_admin_token';
  static const _lastEmailKey = 'auditron_admin_last_email';

  Future<String?> get token => _storage.read(key: _tokenKey);

  Future<String?> get lastEmail => _storage.read(key: _lastEmailKey);

  Future<void> saveLastEmail(String email) =>
      _storage.write(key: _lastEmailKey, value: email);

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<bool> get isLoggedIn async => (await token) != null;

  Future<Map<String, String>> _headers() async {
    final t = await token;
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (t != null) 'Authorization': 'Bearer $t',
    };
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) async {
    final uri = Uri.parse(
      '${ApiClient.baseUrl}$path',
    ).replace(queryParameters: query);
    final response = await _guarded(
      () async => http.get(uri, headers: await _headers()),
    );
    return _decode(response);
  }

  /// Récupère toutes les pages Laravel d'une ressource paginée.
  Future<dynamic> getAllPages(String path, {Map<String, String>? query}) async {
    final uri = Uri.parse(path);
    final pageQuery = {...uri.queryParameters, ...?query};
    final first = await get(uri.path, query: pageQuery);
    if (first is! Map || first['data'] is! List) return first;

    final items = List<dynamic>.from(first['data'] as List);
    var page = (first['current_page'] as num?)?.toInt() ?? 1;
    final lastPage = (first['last_page'] as num?)?.toInt() ?? page;
    while (page < lastPage) {
      page++;
      final next = await get(uri.path, query: {...pageQuery, 'page': '$page'});
      if (next is! Map || next['data'] is! List) break;
      items.addAll(next['data'] as List);
    }

    return items;
  }

  Future<dynamic> post(String path, Map<String, dynamic> body) async {
    final uri = Uri.parse('${ApiClient.baseUrl}$path');
    final response = await _guarded(
      () async =>
          http.post(uri, headers: await _headers(), body: jsonEncode(body)),
    );
    return _decode(response);
  }

  Future<dynamic> put(String path, Map<String, dynamic> body) async {
    final uri = Uri.parse('${ApiClient.baseUrl}$path');
    final response = await _guarded(
      () async =>
          http.put(uri, headers: await _headers(), body: jsonEncode(body)),
    );
    return _decode(response);
  }

  Future<dynamic> patch(String path, Map<String, dynamic> body) async {
    final uri = Uri.parse('${ApiClient.baseUrl}$path');
    final response = await _guarded(
      () async =>
          http.patch(uri, headers: await _headers(), body: jsonEncode(body)),
    );
    return _decode(response);
  }

  Future<dynamic> delete(String path) async {
    final uri = Uri.parse('${ApiClient.baseUrl}$path');
    final response = await _guarded(
      () async => http.delete(uri, headers: await _headers()),
    );
    return _decode(response);
  }

  /// Envoi multipart (photo, import XLSX). [method] : 'POST' (par défaut) ou 'PUT'.
  Future<dynamic> sendMultipart(
    String path, {
    required String fileField,
    required List<int> fileBytes,
    required String filename,
    Map<String, String>? fields,
    String method = 'POST',
  }) async {
    final uri = Uri.parse('${ApiClient.baseUrl}$path');
    final response = await _guarded(() async {
      final request = http.MultipartRequest(method, uri);
      final t = await token;
      request.headers['Accept'] = 'application/json';
      if (t != null) request.headers['Authorization'] = 'Bearer $t';
      request.fields.addAll(fields ?? {});
      request.files.add(
        http.MultipartFile.fromBytes(fileField, fileBytes, filename: filename),
      );
      final streamed = await request.send();
      return http.Response.fromStream(streamed);
    });
    return _decode(response);
  }

  /// Récupère une réponse binaire (export XLSX, PDF bilan) sous forme d'octets bruts.
  Future<List<int>> getBytes(String path, {Map<String, String>? query}) async {
    final uri = Uri.parse(
      '${ApiClient.baseUrl}$path',
    ).replace(queryParameters: query);
    final response = await _guarded(
      () async => http.get(uri, headers: await _headers()),
    );
    if (response.statusCode == 401) {
      clearToken();
      throw ApiException(
        'Session expirée, merci de vous reconnecter.',
        response.statusCode,
      );
    }
    if (response.statusCode >= 400) {
      throw ApiException('Une erreur est survenue.', response.statusCode);
    }
    return response.bodyBytes;
  }

  Future<http.Response> _guarded(
    Future<http.Response> Function() request,
  ) async {
    try {
      return await request();
    } on SocketException {
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
      throw ApiException(
        "Impossible de contacter le serveur. Vérifiez votre connexion et réessayez.",
        0,
      );
    }
  }

  dynamic _decode(http.Response response) {
    final body = response.body.isEmpty ? null : jsonDecode(response.body);

    if (response.statusCode == 401) {
      clearToken();
      throw ApiException(
        'Session expirée, merci de vous reconnecter.',
        response.statusCode,
      );
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
