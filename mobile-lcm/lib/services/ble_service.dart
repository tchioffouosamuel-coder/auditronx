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
    // `adapterStateNow` reste à `unknown` tant qu'aucun *changement* d'état
    // n'a été observé depuis le démarrage de l'app (ex. Bluetooth déjà activé
    // avant l'ouverture) — on utilise donc le stream `adapterState`, qui va
    // chercher l'état réel via une requête native si besoin.
    final state = await FlutterBluePlus.adapterState.first;
    return state == BluetoothAdapterState.on;
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

    // Android renvoie parfois une erreur GATT générique (code 133) sur une
    // opération par ailleurs valide — bug connu du stack BLE Android (plus
    // fréquent sur certains Samsung), sans solution fiable côté app hormis
    // réessayer. cf. https://github.com/boskokg/flutter_blue_plus (FAQ
    // "ANDROID_SPECIFIC_ERROR").
    const maxAttempts = 3;
    for (var attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        final result = await _attemptScan(
          device,
          total: total,
          type: type,
          teacherToken: teacherToken,
          qrCode: qrCode,
          enseignantId: enseignantId,
          motif: motif,
        );
        // Fire-and-forget : ne doit pas retarder le retour du résultat à
        // l'écran (turnOff() peut mettre plusieurs secondes à répondre).
        unawaited(_disableBluetoothAfterSend());
        return result;
      } on FlutterBluePlusException catch (e) {
        if (attempt == maxAttempts) {
          throw ApiException(
            "La borne n'a pas répondu correctement (erreur Bluetooth). Réessayez.",
            0,
          );
        }
        debugPrint('[ble] tentative $attempt échouée (${e.description}), nouvel essai...');
        await device.disconnect();
        await Future.delayed(const Duration(milliseconds: 400));
      }
    }
    // Inatteignable : la boucle retourne ou lève avant sa dernière itération.
    throw ApiException("Échec de communication avec la borne.", 0);
  }

  Future<BorneScanResult> _attemptScan(
    BluetoothDevice device, {
    required Stopwatch total,
    required String type,
    required String teacherToken,
    required String qrCode,
    int? enseignantId,
    String? motif,
  }) async {
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
      //
      // Petite pause avant la première opération GATT : sur certains
      // téléphones (Samsung notamment), enchaîner discoverServices() puis un
      // write immédiatement déclenche une erreur GATT générique (133) alors
      // que la connexion est en réalité valide.
      await Future.delayed(const Duration(milliseconds: 300));

      await resultChar.setNotifyValue(true);
      final responseFuture = resultChar.onValueReceived.first.timeout(const Duration(seconds: 10));

      final payload = {
        'qr_code': qrCode,
        if (enseignantId != null) 'enseignant_id': enseignantId,
        if (motif != null) 'motif': motif,
      };
      // La borne a son propre NTP (voir processScan() côté firmware), mais ça
      // suppose un modem déjà connecté — sinon elle refuse le scan le temps de
      // se synchroniser. En attendant un DS3231 (RTC matérielle, jamais
      // dépendante du réseau), on fournit l'heure du téléphone : le firmware
      // la préfère déjà à son NTP quand elle est présente dans le paquet.
      final body = jsonEncode({
        'type': type,
        'teacher_token': teacherToken,
        'payload': payload,
        'captured_at': DateTime.now().toUtc().toIso8601String(),
      });
      await scanChar.write(utf8.encode(body), withoutResponse: false);

      final responseBytes = await responseFuture;
      debugPrint('[timing] scanViaBorne BLE total=${total.elapsedMilliseconds}ms');
      return _parseBorneResponse(utf8.decode(responseBytes));
    } finally {
      unawaited(device.disconnect());
    }
  }

  /// Une fois le pointage transmis, on coupe le Bluetooth du téléphone : la
  /// borne n'est plus utile tant qu'il n'y a pas de nouveau scan. Best-effort
  /// et silencieux — `turnOff()` est dépréciée et n'a plus aucun effet sur
  /// Android 13+ (Google a retiré la possibilité pour une app tierce de
  /// couper le Bluetooth système) : sur ces versions, l'appel ne fait rien,
  /// sans lever d'erreur.
  Future<void> _disableBluetoothAfterSend() async {
    try {
      // ignore: deprecated_member_use
      await FlutterBluePlus.turnOff();
    } catch (_) {
      // best-effort, cf. commentaire ci-dessus.
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
