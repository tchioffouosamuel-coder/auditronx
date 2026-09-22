import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'admin_api_client.dart';
import 'api_client.dart';

/// Session de l'espace admin (§admin-mobile) — mode secondaire de l'app,
/// indépendant de [Session] (enseignant/kiosque). Contrairement au flux
/// enseignant (OTP, jamais de re-login), l'admin s'authentifie comme sur le
/// backoffice web : email + mot de passe (`User`), token Sanctum classique.
class AdminSession extends ChangeNotifier {
  final _storage = const FlutterSecureStorage();
  static const _userCacheKey = 'auditron_admin_user_cache';

  bool _loading = true;
  bool _loggedIn = false;
  Map<String, dynamic>? _user;

  bool get loading => _loading;
  bool get loggedIn => _loggedIn;
  Map<String, dynamic>? get user => _user;
  String get nom => _user?['name'] as String? ?? '';

  Future<void> bootstrap() async {
    _loggedIn = await AdminApiClient.instance.isLoggedIn;

    if (_loggedIn) {
      _user = await _loadCachedUser();
      try {
        _user = await AdminApiClient.instance.get('/me') as Map<String, dynamic>;
        await _cacheUser(_user!);
      } on ApiException catch (e) {
        if (e.statusCode == 401) {
          _loggedIn = false;
          _user = null;
          await _storage.delete(key: _userCacheKey);
        }
      }
    }

    _loading = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final response = await AdminApiClient.instance.post('/login', {
      'email': email,
      'password': password,
    });

    await AdminApiClient.instance.saveToken(response['token'] as String);
    _user = response['user'] as Map<String, dynamic>;
    await _cacheUser(_user!);
    _loggedIn = true;
    notifyListeners();
  }

  Future<void> _cacheUser(Map<String, dynamic> user) =>
      _storage.write(key: _userCacheKey, value: jsonEncode(user));

  Future<Map<String, dynamic>?> _loadCachedUser() async {
    final raw = await _storage.read(key: _userCacheKey);
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  /// Modification du mot de passe (§mon-compte) — exige le mot de passe
  /// actuel, contrairement à la gestion RH d'un tiers via AdminPersonnelScreen.
  Future<void> updatePassword(String currentPassword, String newPassword) {
    return AdminApiClient.instance.put('/me/password', {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  Future<void> logout() async {
    try {
      await AdminApiClient.instance.post('/logout', {});
    } catch (_) {
      // Best-effort : même si la révocation serveur échoue (hors-ligne...),
      // on déconnecte localement.
    }
    await AdminApiClient.instance.clearToken();
    await _storage.delete(key: _userCacheKey);
    _loggedIn = false;
    _user = null;
    notifyListeners();
  }
}
