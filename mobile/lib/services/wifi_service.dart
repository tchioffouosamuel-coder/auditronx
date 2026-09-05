import 'dart:convert';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
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

class BorneScanResult {
  final String? localId;
  final bool photoCaptured;

  BorneScanResult({this.localId, required this.photoCaptured});
}

/// Adresse par défaut du point d'accès WiFi de l'ESP32 (voir
/// esp32_borne/src/main.cpp — WiFi.softAP() sans IP custom).
const _borneIp = '192.168.4.1';

/// Pointage via la borne WiFi ESP32 (§4.1, §hardware) : le téléphone ne parle
/// JAMAIS à l'API distante pour un scan — il POST en HTTP local à la borne,
/// qui met le paquet en file sur sa carte SD et le pousse elle-même vers
/// l'API à son rythme. Le téléphone n'a donc besoin d'internet qu'une seule
/// fois, à l'activation initiale de l'app — jamais pendant un scan.
///
/// Sur Android 10+, la connexion à la borne passe par `WifiNetworkSpecifier`
/// côté natif (MainActivity.kt), qui fait aussi le POST HTTP directement sur
/// le réseau éphémère (pas la route par défaut du téléphone, donc un simple
/// appel Dart ne l'emprunterait pas) — connexion jamais enregistrée dans la
/// liste WiFi du téléphone, libérée juste après la réponse de la borne.
///
/// Sur Android <10 / si aucune borne connue n'est joignable, on retombe sur
/// un POST HTTP classique en supposant que l'utilisateur est déjà connecté
/// manuellement au WiFi de la borne.
class WifiService {
  static const _channel = MethodChannel('auditron/wifi');

  final _networkInfo = NetworkInfo();

  Future<bool> ensureLocationPermission() async {
    final status = await Permission.locationWhenInUse.request();
    return status.isGranted;
  }

  /// Depuis Android 10, aucune app tierce ne peut activer le WiFi par code
  /// (`WifiManager.setWifiEnabled` est un no-op) — seul l'utilisateur le peut.
  /// Renvoie `true` sur les plateformes où l'info n'est pas disponible
  /// (iOS...), pour ne pas bloquer inutilement le flux de scan.
  Future<bool> isWifiEnabled() async {
    try {
      return await _channel.invokeMethod<bool>('isWifiEnabled') ?? true;
    } on PlatformException {
      return true;
    }
  }

  /// Ouvre le panneau rapide système pour activer le WiFi en un tap, sans
  /// quitter l'app.
  Future<void> openWifiPanel() async {
    try {
      await _channel.invokeMethod('openWifiPanel');
    } on PlatformException {
      // best-effort
    }
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

  /// Transmet un pointage à la borne (§hardware) : tente chaque borne connue
  /// via la connexion WiFi éphémère native, puis retombe sur une connexion
  /// manuelle déjà établie si indisponible/hors de portée. Lève une
  /// [ApiException] si la borne a explicitement refusé le paquet (400/503) ou
  /// si aucune borne n'a pu être jointe.
  Future<BorneScanResult> scanViaBorne({
    required String type,
    required String teacherToken,
    required String qrCode,
    int? enseignantId,
    String? motif,
  }) async {
    final bornes = await fetchKnownBornes();

    for (final borne in bornes) {
      try {
        final raw = await _channel.invokeMethod('scanViaBorne', {
          'ssid': borne.ssid,
          'password': borne.password,
          'type': type,
          'teacherToken': teacherToken,
          'qrCode': qrCode,
          if (enseignantId != null) 'enseignantId': enseignantId,
          if (motif != null) 'motif': motif,
        });
        return _parseBorneResponse(
          (raw as Map)['statusCode'] as int,
          (raw['body'] as String?) ?? '',
        );
      } on PlatformException catch (e) {
        if (e.code == 'UNSUPPORTED_SDK') break; // inutile d'essayer les autres bornes
        // CONNECT_FAILED/HTTP_FAILED : cette borne n'est pas joignable, on essaie la suivante.
      }
    }

    return _scanViaBorneFallback(
      type: type,
      teacherToken: teacherToken,
      qrCode: qrCode,
      enseignantId: enseignantId,
      motif: motif,
    );
  }

  /// Repli Android <10 (ou aucune borne WifiNetworkSpecifier joignable) : le
  /// téléphone doit déjà être connecté manuellement au WiFi de la borne, qui
  /// est alors sa route réseau par défaut — un POST HTTP classique suffit.
  Future<BorneScanResult> _scanViaBorneFallback({
    required String type,
    required String teacherToken,
    required String qrCode,
    int? enseignantId,
    String? motif,
  }) async {
    final bssid = await currentBssid();
    if (bssid == null) {
      throw ApiException(
        "Impossible de rejoindre la borne. Vérifiez que le WiFi est activé et que vous êtes à portée.",
        0,
      );
    }

    final payload = {
      'qr_code': qrCode,
      'bssid': bssid,
      if (enseignantId != null) 'enseignant_id': enseignantId,
      if (motif != null) 'motif': motif,
    };

    http.Response response;
    try {
      response = await http.post(
        Uri.parse('http://$_borneIp/scan'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'type': type, 'teacher_token': teacherToken, 'payload': payload}),
      );
    } catch (_) {
      throw ApiException("Impossible de joindre la borne. Vérifiez la connexion WiFi.", 0);
    }

    return _parseBorneResponse(response.statusCode, response.body);
  }

  BorneScanResult _parseBorneResponse(int statusCode, String body) {
    final decoded = body.isNotEmpty ? jsonDecode(body) as Map<String, dynamic> : <String, dynamic>{};

    if (statusCode == 202 && decoded['queued'] == true) {
      return BorneScanResult(
        localId: decoded['local_id'] as String?,
        photoCaptured: decoded['photo_captured'] == true,
      );
    }

    throw ApiException((decoded['error'] as String?) ?? 'La borne a refusé le scan.', statusCode);
  }

  /// Retourne le BSSID du WiFi déjà connecté, ou null si indisponible
  /// (permission refusée, WiFi désactivé, ou limitation connue sur iOS — §4.1).
  Future<String?> currentBssid() async {
    final granted = await ensureLocationPermission();
    if (!granted) return null;

    return _networkInfo.getWifiBSSID();
  }
}
