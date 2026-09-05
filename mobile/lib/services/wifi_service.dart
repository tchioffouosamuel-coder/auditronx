import 'package:flutter/services.dart';
import 'package:network_info_plus/network_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';
import 'api_client.dart';

class BorneWifi {
  final String ssid;
  final String? password;

  BorneWifi({required this.ssid, required this.password});

  factory BorneWifi.fromJson(Map<String, dynamic> json) =>
      BorneWifi(ssid: json['ssid'] as String, password: json['password'] as String?);
}

/// Connexion à la borne WiFi de la salle au moment du scan (§4.1).
///
/// Sur Android 10+, la connexion passe par `WifiNetworkSpecifier` côté natif
/// (voir MainActivity.kt) : elle n'est jamais ajoutée à la liste WiFi
/// enregistrée du téléphone et se libère (déconnexion + oubli implicite) dès
/// [release] appelé après le scan — ça évite de saturer les quelques
/// emplacements de connexion de l'AP softAP de l'ESP32 avec des téléphones
/// restés connectés en arrière-plan. Sur Android <10 / iOS, cette connexion
/// automatique n'est pas disponible : on retombe sur l'ancien comportement
/// (le BSSID du réseau auquel l'utilisateur s'est déjà connecté manuellement).
class WifiService {
  static const _channel = MethodChannel('auditron/wifi');

  final _networkInfo = NetworkInfo();

  Future<bool> ensureLocationPermission() async {
    final status = await Permission.locationWhenInUse.request();
    return status.isGranted;
  }

  Future<List<BorneWifi>> fetchKnownBornes() async {
    try {
      final data = await ApiClient.instance.get('/wifi-access-points');
      return (data as List)
          .map((e) => BorneWifi.fromJson(e as Map<String, dynamic>))
          .where((b) => b.ssid.isNotEmpty)
          .toList();
    } on ApiException {
      return [];
    }
  }

  /// Tente de rejoindre automatiquement l'une des bornes connues et renvoie
  /// le BSSID une fois connecté. Renvoie null si aucune n'a pu être rejointe
  /// (hors de portée, ou fonctionnalité indisponible sur cet appareil) — dans
  /// ce cas l'appelant doit se rabattre sur [currentBssid].
  Future<String?> connectToKnownBorne(List<BorneWifi> bornes) async {
    for (final borne in bornes) {
      try {
        final bssid = await _channel.invokeMethod<String>('connect', {
          'ssid': borne.ssid,
          'password': borne.password,
        });
        if (bssid != null) return bssid;
      } on PlatformException catch (e) {
        if (e.code == 'UNSUPPORTED_SDK') return null; // inutile d'essayer les autres
        // CONNECT_FAILED / INVALID_ARGS : cette borne n'est pas à portée, on essaie la suivante.
      }
    }
    return null;
  }

  /// Libère la connexion établie par [connectToKnownBorne] — à appeler
  /// systématiquement après le scan (succès ou échec) pour rendre
  /// l'emplacement à l'AP de la borne.
  Future<void> release() async {
    try {
      await _channel.invokeMethod('release');
    } on PlatformException {
      // best-effort
    }
  }

  /// Retourne le BSSID du WiFi déjà connecté, ou null si indisponible
  /// (permission refusée, WiFi désactivé, ou limitation connue sur iOS — §4.1).
  /// Utilisé en repli quand [connectToKnownBorne] échoue ou n'est pas supporté.
  Future<String?> currentBssid() async {
    final granted = await ensureLocationPermission();
    if (!granted) return null;

    return _networkInfo.getWifiBSSID();
  }
}
