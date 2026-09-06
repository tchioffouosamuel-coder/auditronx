import 'package:flutter/foundation.dart';
import 'admin_api_client.dart';

/// Session de l'espace admin (§admin-mobile) — mode secondaire de l'app,
/// indépendant de [Session] (enseignant/kiosque). Contrairement au flux
/// enseignant (OTP, jamais de re-login), l'admin s'authentifie comme sur le
/// backoffice web : email + mot de passe (`User`), token Sanctum classique.
class AdminSession extends ChangeNotifier {
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
      try {
        _user = await AdminApiClient.instance.get('/me') as Map<String, dynamic>;
      } catch (_) {
        // Token invalide/expiré : AdminApiClient a déjà purgé le stockage sur
        // un 401 ; on retombe simplement sur l'écran de connexion admin.
        _loggedIn = await AdminApiClient.instance.isLoggedIn;
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
    _loggedIn = true;
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await AdminApiClient.instance.post('/logout', {});
    } catch (_) {
      // Best-effort : même si la révocation serveur échoue (hors-ligne...),
      // on déconnecte localement.
    }
    await AdminApiClient.instance.clearToken();
    _loggedIn = false;
    _user = null;
    notifyListeners();
  }
}
