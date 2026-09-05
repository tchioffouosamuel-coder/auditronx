import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_client.dart';
import 'push_notifications.dart';

/// État d'activation de l'app (§4.1). Un `device_uuid` est généré une seule
/// fois et persisté ; il identifie ce téléphone auprès de l'API à l'activation.
///
/// Une fois l'enseignant activé (token Sanctum stocké de façon sécurisée), il
/// ne doit plus jamais repasser par le login/OTP — y compris au tout premier
/// lancement sans internet (le scan passe par la borne locale, pas par l'API,
/// §hardware) : la présence du token suffit à rester connecté, `/me` n'est
/// qu'un rafraîchissement best-effort qui ne doit jamais démonter la session.
class Session extends ChangeNotifier {
  final _storage = const FlutterSecureStorage();
  static const _deviceUuidKey = 'auditron_device_uuid_local';
  static const _meCacheKey = 'auditron_me_cache';

  bool _loading = true;
  bool _activated = false;
  Map<String, dynamic>? _me;

  bool get loading => _loading;
  bool get activated => _activated;
  Map<String, dynamic>? get me => _me;
  String get nom => _me?['nom'] as String? ?? '';

  Future<void> bootstrap() async {
    _activated = await ApiClient.instance.isActivated;

    if (_activated) {
      _me = await _loadCachedMe();
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

  Future<void> _cacheMe(Map<String, dynamic> me) => _storage.write(key: _meCacheKey, value: jsonEncode(me));

  Future<Map<String, dynamic>?> _loadCachedMe() async {
    final raw = await _storage.read(key: _meCacheKey);
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<String> deviceUuid() async {
    var uuid = await _storage.read(key: _deviceUuidKey);
    if (uuid == null) {
      uuid = const Uuid().v4();
      await _storage.write(key: _deviceUuidKey, value: uuid);
    }
    return uuid;
  }

  /// Étape 1 (§4.1 revu) : identification par téléphone + mot de passe. Un
  /// enseignant admin est activé immédiatement ; sinon une demande est créée
  /// pour l'administration, qui remettra un OTP en personne.
  Future<bool> requestActivation(String tel, String password) async {
    final uuid = await deviceUuid();
    final response = await ApiClient.instance.post('/devices/request-activation', {
      'tel': tel,
      'password': password,
      'device_uuid': uuid,
      'device_type': 'mobile',
    });

    final activated = response['activated'] as bool;
    if (activated) {
      await ApiClient.instance.saveSession(token: response['token'] as String, deviceUuid: uuid);
      _activated = true;
      _me = await ApiClient.instance.get('/me') as Map<String, dynamic>;
      await _cacheMe(_me!);
      unawaited(PushNotifications.instance.registerDevice());
      notifyListeners();
    }

    return activated;
  }

  /// Étape 2 : finalise l'activation avec le code OTP remis en personne par
  /// l'administration.
  Future<void> activate(String otpCode) async {
    final uuid = await deviceUuid();
    final response = await ApiClient.instance.post('/devices/activate', {
      'code': otpCode,
      'device_uuid': uuid,
      'device_type': 'mobile',
    });

    await ApiClient.instance.saveSession(token: response['token'] as String, deviceUuid: uuid);
    _activated = true;
    _me = await ApiClient.instance.get('/me') as Map<String, dynamic>;
    await _cacheMe(_me!);
    unawaited(PushNotifications.instance.registerDevice());
    notifyListeners();
  }

  Future<void> logout() async {
    await ApiClient.instance.clearSession();
    await _storage.delete(key: _meCacheKey);
    _activated = false;
    _me = null;
    notifyListeners();
  }
}
