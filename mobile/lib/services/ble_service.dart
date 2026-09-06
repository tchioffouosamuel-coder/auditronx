import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart' show debugPrint;
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import 'api_client.dart';

class BorneScanResult {
  final String? localId;
  final bool photoCaptured;

  BorneScanResult({this.localId, required this.photoCaptured});
}

/// Pointage via la borne ESP32 en BLE (§4.1, §hardware) : remplace la
/// connexion WiFi locale (trop lente à négocier — poignée de main WiFi +
/// parfois boîte de dialogue système). Le BLE se connecte typiquement en
/// moins d'une seconde, sans dialogue de confirmation réseau. Le téléphone
/// n'a besoin d'internet qu'une seule fois, à l'activation — jamais pour un
/// scan : ici il ne parle qu'en local à la borne.
///
/// UUIDs propres à Auditron X (doivent correspondre exactement à ceux
/// déclarés côté firmware, voir esp32_borne/include/config.h) :
/// - service : expose deux caractéristiques,
/// - "scan"  (écriture) : le téléphone y écrit la requête JSON,
/// - "result" (lecture/notification) : la borne y publie sa réponse JSON.
class BleService {
  static final Guid serviceUuid = Guid('b3a1a100-2c33-4e6f-9a1e-5f6a2e6c2b01');
  static final Guid _scanCharUuid = Guid('b3a1a101-2c33-4e6f-9a1e-5f6a2e6c2b01');
  static final Guid _resultCharUuid = Guid('b3a1a102-2c33-4e6f-9a1e-5f6a2e6c2b01');

  Future<bool> isBluetoothEnabled() async {
    if (!await FlutterBluePlus.isSupported) return false;
    return FlutterBluePlus.adapterStateNow == BluetoothAdapterState.on;
  }

  /// Contrairement au WiFi (bloqué pour les apps tierces depuis Android 10),
  /// Android autorise une app à demander l'activation du Bluetooth
  /// directement (boîte de dialogue système à valider par l'utilisateur).
  Future<void> requestEnableBluetooth() async {
    try {
      await FlutterBluePlus.turnOn();
    } catch (_) {
      // best-effort — l'appelant revérifiera isBluetoothEnabled().
    }
  }

  /// Transmet un pointage à la borne : cherche la borne à portée (filtrée par
  /// UUID de service), s'y connecte, envoie la requête, attend la réponse.
  /// Lève une [ApiException] si la borne est hors de portée ou a refusé le
  /// paquet.
  Future<BorneScanResult> scanViaBorne({
    required String type,
    required String teacherToken,
    required String qrCode,
    int? enseignantId,
    String? motif,
  }) async {
    final total = Stopwatch()..start();
    final device = await _findBorne();
    debugPrint('[timing] recherche BLE borne: ${total.elapsedMilliseconds}ms');
    if (device == null) {
      throw ApiException(
        "Impossible de trouver la borne à proximité. Vérifiez le Bluetooth et la distance.",
        0,
      );
    }

    try {
      await device.connect(timeout: const Duration(seconds: 8));
      final services = await device.discoverServices();
      final service = services.firstWhere(
        (s) => s.uuid == serviceUuid,
        orElse: () => throw ApiException("Borne incompatible (service BLE introuvable).", 0),
      );
      final scanChar = service.characteristics.firstWhere((c) => c.uuid == _scanCharUuid);
      final resultChar = service.characteristics.firstWhere((c) => c.uuid == _resultCharUuid);

      // `connect()` négocie déjà un MTU de 512 par défaut — pas besoin d'un
      // requestMtu() séparé, le payload JSON (quelques centaines d'octets) y tient.
      await resultChar.setNotifyValue(true);
      final responseFuture = resultChar.onValueReceived.first.timeout(const Duration(seconds: 10));

      final payload = {
        'qr_code': qrCode,
        if (enseignantId != null) 'enseignant_id': enseignantId,
        if (motif != null) 'motif': motif,
      };
      final body = jsonEncode({'type': type, 'teacher_token': teacherToken, 'payload': payload});
      await scanChar.write(utf8.encode(body), withoutResponse: false);

      final responseBytes = await responseFuture;
      debugPrint('[timing] scanViaBorne BLE total=${total.elapsedMilliseconds}ms');
      return _parseBorneResponse(utf8.decode(responseBytes));
    } finally {
      unawaited(device.disconnect());
    }
  }

  Future<BluetoothDevice?> _findBorne() async {
    final completer = Completer<BluetoothDevice?>();
    final sub = FlutterBluePlus.scanResults.listen((results) {
      if (results.isNotEmpty && !completer.isCompleted) {
        completer.complete(results.first.device);
      }
    });

    await FlutterBluePlus.startScan(withServices: [serviceUuid], timeout: const Duration(seconds: 8));
    final device = await completer.future.timeout(const Duration(seconds: 8), onTimeout: () => null);
    await FlutterBluePlus.stopScan();
    await sub.cancel();
    return device;
  }

  BorneScanResult _parseBorneResponse(String body) {
    final decoded = body.isNotEmpty ? jsonDecode(body) as Map<String, dynamic> : <String, dynamic>{};

    if (decoded['queued'] == true) {
      return BorneScanResult(
        localId: decoded['local_id'] as String?,
        photoCaptured: decoded['photo_captured'] == true,
      );
    }

    throw ApiException((decoded['error'] as String?) ?? 'La borne a refusé le scan.', 0);
  }
}
