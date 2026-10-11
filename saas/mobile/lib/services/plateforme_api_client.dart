import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import 'api_client.dart';
import 'secure_storage_safe.dart';

/// Session du personnel Auditron (éditeur), contre les routes `/api/central/*`.
///
/// Troisième session de l'application, à côté de celle de l'enseignant
/// ([ApiClient]) et de celle de la direction d'un établissement
/// ([AdminApiClient]). Elle est bien distincte : un compte éditeur est un
/// `PlatformUser`, une autre table et un autre garde côté API (`platform`), et
/// il n'est pas authentifiable sur les routes métier d'un client.
///
/// Les routes centrales ne portent **pas** d'en-tête `X-Tenant` : elles
/// travaillent sur la base centrale et ne désignent aucun établissement.
///
/// Clés de stockage propres, pour que les trois sessions coexistent sur le
/// même téléphone sans se marcher dessus.
class PlateformeApiClient {
  PlateformeApiClient._();
  static final PlateformeApiClient instance = PlateformeApiClient._();

  final _storage = const FlutterSecureStorage();
  static const _tokenKey = 'auditron_plateforme_token';
  static const _nomKey = 'auditron_plateforme_nom';
  static const _roleKey = 'auditron_plateforme_role';

  Future<String?> get token => _storage.readOrNull(_tokenKey);
  Future<String?> get nom => _storage.readOrNull(_nomKey);
  Future<String?> get role => _storage.readOrNull(_roleKey);

  Future<bool> get isLoggedIn async => (await token) != null;

  /// `POST /api/central/login` — renvoie le nom du compte connecté.
  Future<String> connexion(String email, String motDePasse) async {
    final reponse = await http.post(
      Uri.parse('${ApiClient.baseUrl}/central/login'),
      headers: const {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'email': email, 'password': motDePasse}),
    );

    final corps = reponse.body.isEmpty ? null : jsonDecode(reponse.body);

    if (reponse.statusCode >= 400 || corps is! Map) {
      final message = corps is Map && corps['message'] != null
          ? corps['message'] as String
          : 'Identifiants invalides.';
      throw ApiException(message, reponse.statusCode);
    }

    final utilisateur = corps['user'] as Map<String, dynamic>? ?? const {};

    await _storage.write(key: _tokenKey, value: corps['token'] as String);
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

  Future<void> deconnexion() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _nomKey);
    await _storage.delete(key: _roleKey);
  }

  Future<dynamic> get(String path, {Map<String, String>? query}) async {
    final t = await token;
    final uri = Uri.parse(
      '${ApiClient.baseUrl}/central$path',
    ).replace(queryParameters: query);

    final reponse = await http.get(
      uri,
      headers: {
        'Accept': 'application/json',
        if (t != null) 'Authorization': 'Bearer $t',
      },
    );

    if (reponse.statusCode == 401) {
      await deconnexion();
      throw ApiException('Session expirée, reconnectez-vous.', 401);
    }

    final corps = reponse.body.isEmpty ? null : jsonDecode(reponse.body);

    if (reponse.statusCode >= 400) {
      throw ApiException(
        corps is Map && corps['message'] != null
            ? corps['message'] as String
            : 'Une erreur est survenue.',
        reponse.statusCode,
      );
    }

    return corps;
  }
}
