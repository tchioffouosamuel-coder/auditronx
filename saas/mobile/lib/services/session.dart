import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'secure_storage_safe.dart';
import 'api_client.dart';
import 'presence_repository.dart';
import 'push_notifications.dart';

/// État d'activation de l'app (§4.1). Un `device_uuid` est généré une seule
/// fois et persisté ; il identifie ce téléphone auprès de l'API à l'activation.
///
/// Une fois l'enseignant activé (token Sanctum stocké de façon sécurisée), il
/// ne repasse pas par le login tant que le token reste valide — même au premier
/// lancement sans internet (le scan passe par la borne locale, pas par l'API,
/// §hardware) : la présence du token suffit à rester connecté, `/me` n'est
/// qu'un rafraîchissement best-effort. Un refus du token ouvre une récupération
/// limitée au même enseignant, sans changer l'identité du téléphone.
class Session extends ChangeNotifier {
  final _storage = const FlutterSecureStorage();
  static const _deviceUuidKey = 'auditron_device_uuid_local';
  static const _meCacheKey = 'auditron_me_cache';
  static const _lastTelKey = 'auditron_last_tel';
  static const _bindingKey = 'auditron_teacher_binding';

  bool _loading = true;
  bool _activated = false;
  bool _needsRecovery = false;
  String? _boundTel;
  String? _boundTenant;
  Map<String, dynamic>? _me;

  bool get loading => _loading;
  bool get activated => _activated;
  bool get needsRecovery => _needsRecovery;
  String? get boundTelephone => _boundTel;
  String? get boundEtablissement => _boundTenant;
  Map<String, dynamic>? get me => _me;
  String get nom => _me?['nom'] as String? ?? '';

  Future<String?> get lastTel => _storage.readOrNull(_lastTelKey);

  /// L'API a refusé la session (token révoqué, supprimé ou perdu) : retour à
  /// l'écran de connexion, quel que soit l'écran d'où venait la requête.
  void _onSessionExpired() {
    requireRecovery();
  }

  void requireRecovery() {
    if (_needsRecovery) return;
    _activated = false;
    _needsRecovery = true;
    notifyListeners();
  }

  Future<void> bootstrap() async {
    ApiClient.instance.onSessionExpired = _onSessionExpired;
    _activated = await ApiClient.instance.isActivated;
    _me = await _loadCachedMe();
    await _loadBinding();
    _needsRecovery = !_activated && _boundTel != null;

    if (_activated) {
      try {
        _me = await ApiClient.instance.get('/me') as Map<String, dynamic>;
        await _cacheMe(_me!);
        unawaited(PushNotifications.instance.registerDevice());
      } catch (_) {
        // Pas d'internet (ou API indisponible) au démarrage : on reste
        // connecté avec les infos mises en cache localement — l'app n'en a
        // besoin qu'à l'activation, jamais pour scanner.
      }
    }

    _loading = false;
    notifyListeners();
  }

  Future<void> _loadBinding() async {
    final raw = await _storage.readOrNull(_bindingKey);
    if (raw != null) {
      try {
        final binding = jsonDecode(raw) as Map<String, dynamic>;
        _boundTel = binding['tel'] as String?;
        _boundTenant = binding['etablissement'] as String?;
      } catch (_) {
        // Older installs recover their binding from the existing device record.
      }
    }
    if (_boundTel == null &&
        await _storage.readOrNull(_deviceUuidKey) != null) {
      _boundTel = _me?['tel'] as String? ?? await lastTel;
      _boundTenant = await ApiClient.instance.etablissement;
      if (_boundTel != null) await _saveBinding();
    }
  }

  Future<void> _saveBinding() => _storage.write(
    key: _bindingKey,
    value: jsonEncode({'tel': _boundTel, 'etablissement': _boundTenant}),
  );

  Future<void> restoreSession(String password) async {
    final tel = _boundTel;
    final tenant = _boundTenant;
    final uuid = await _storage.readOrNull(_deviceUuidKey);
    if (tel == null || tenant == null || uuid == null) {
      throw ApiException(
        "L'identité enregistrée sur ce téléphone est indisponible. Contactez votre administration sans réinstaller l'application.",
        0,
      );
    }
    if (await ApiClient.instance.etablissement != tenant) {
      await ApiClient.instance.definirEtablissement(tenant);
    }
    await _activate(tel, password, uuid);
  }

  Future<void> _cacheMe(Map<String, dynamic> me) =>
      _storage.write(key: _meCacheKey, value: jsonEncode(me));

  Future<Map<String, dynamic>?> _loadCachedMe() async {
    final raw = await _storage.readOrNull(_meCacheKey);
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<String> deviceUuid() async {
    var uuid = await _storage.readOrNull(_deviceUuidKey);
    if (uuid == null) {
      uuid = const Uuid().v4();
      await _storage.write(key: _deviceUuidKey, value: uuid);
    }
    return uuid;
  }

  /// Vérifie les identifiants et active immédiatement le téléphone.
  Future<void> requestActivation(String tel, String password) async {
    if (_boundTel != null) {
      throw ApiException(
        'Ce téléphone est déjà associé à un enseignant. Rétablissez sa session.',
        403,
      );
    }
    final uuid = await deviceUuid();
    await _activate(tel, password, uuid);
  }

  Future<void> _activate(String tel, String password, String uuid) async {
    final response = await ApiClient.instance.post(
      '/devices/request-activation',
      {
        'tel': tel,
        'password': password,
        'device_uuid': uuid,
        'device_type': 'mobile',
      },
    );

    if (response is! Map<String, dynamic>) {
      throw ApiException(
        'Réponse invalide du serveur lors de la connexion.',
        502,
      );
    }

    final token = response['token'];
    if (token is! String || token.isEmpty) {
      final message = response['activated'] == false
          ? "Le serveur attend encore une validation. L'API LTM doit être mise à jour pour activer directement les enseignants."
          : "Le serveur n'a pas renvoyé de session. Vérifiez que l'API LTM est à jour.";
      throw ApiException(message, 502);
    }

    _boundTel = tel;
    _boundTenant = await ApiClient.instance.etablissement;
    await _saveBinding();
    await _storage.write(key: _lastTelKey, value: tel);
    await ApiClient.instance.saveSession(token: token, deviceUuid: uuid);
    _activated = true;
    _needsRecovery = false;
    try {
      _me = await ApiClient.instance.get('/me') as Map<String, dynamic>;
      await _cacheMe(_me!);
      unawaited(PushNotifications.instance.registerDevice());
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        // Le serveur refuse le token qu'il vient d'émettre : ce n'est pas une
        // session expirée, et se reconnecter n'y changera rien.
        throw ApiException(
          "Le serveur a refusé la session qu'il vient de créer. Contactez l'administrateur.",
          401,
        );
      }
      // Réseau coupé juste après la connexion : le token est enregistré, le
      // profil sera chargé au prochain démarrage (voir bootstrap()).
    }
    notifyListeners();
  }

  @override
  void dispose() {
    if (ApiClient.instance.onSessionExpired == _onSessionExpired) {
      ApiClient.instance.onSessionExpired = null;
    }
    super.dispose();
  }

  /// Modification du mot de passe (§mon-compte) — exige le mot de passe
  /// actuel, contrairement à la gestion RH par un admin.
  Future<void> updatePassword(String currentPassword, String newPassword) {
    return ApiClient.instance.put('/me/password', {
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  Future<void> logout() async {
    await ApiClient.instance.clearSession();
    await _storage.delete(key: _meCacheKey);
    await PresenceRepository(storage: _storage).clearCache();
    _activated = false;
    _needsRecovery = _boundTel != null;
    _me = null;
    notifyListeners();
  }
}
