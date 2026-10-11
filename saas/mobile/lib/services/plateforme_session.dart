import 'package:flutter/foundation.dart';

import 'api_client.dart';
import 'plateforme_api_client.dart';

class PlateformeSession extends ChangeNotifier {
  PlateformeSession() {
    PlateformeApiClient.instance.onSessionExpired = _expire;
  }

  bool _loading = true;
  bool _loggedIn = false;
  String _nom = '';
  String _role = '';
  String _userId = '';

  bool get loading => _loading;
  bool get loggedIn => _loggedIn;
  String get nom => _nom;
  String get role => _role;
  String get userId => _userId;
  bool get estSuperAdmin => _role == 'super_admin';

  void _expire() {
    _loggedIn = false;
    _nom = '';
    _role = '';
    _userId = '';
    notifyListeners();
  }

  Future<void> bootstrap() async {
    _loggedIn = await PlateformeApiClient.instance.isLoggedIn;
    _nom = await PlateformeApiClient.instance.nom ?? '';
    _role = await PlateformeApiClient.instance.role ?? '';
    _userId = await PlateformeApiClient.instance.userId ?? '';

    if (_loggedIn) {
      try {
        final user =
            await PlateformeApiClient.instance.get('/me')
                as Map<String, dynamic>;
        _nom = user['name'] as String? ?? _nom;
        _role = user['role'] as String? ?? _role;
        _userId = user['id']?.toString() ?? _userId;
      } on ApiException catch (error) {
        if (error.statusCode == 401) {
          _loggedIn = false;
          _nom = '';
          _role = '';
          _userId = '';
        }
      } catch (error) {
        debugPrint('Actualisation de la session Auditron impossible : $error');
      }
    }

    _loading = false;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    _nom = await PlateformeApiClient.instance.connexion(email, password);
    _role = await PlateformeApiClient.instance.role ?? '';
    _userId = await PlateformeApiClient.instance.userId ?? '';
    _loggedIn = true;
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await PlateformeApiClient.instance.deconnexion(revoke: true);
    } catch (error) {
      debugPrint('Révocation de la session Auditron impossible : $error');
      await PlateformeApiClient.instance.deconnexion();
    }
    _loggedIn = false;
    _nom = '';
    _role = '';
    _userId = '';
    notifyListeners();
  }
}
