import 'dart:async';
import 'dart:convert';
import 'dart:io' show Platform;
import 'dart:math' show min;
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show debugPrint, kIsWeb;
import 'package:flutter_blue_plus/flutter_blue_plus.dart';
import 'api_client.dart';

class BorneScanResult {
  final String? localId;

  BorneScanResult({this.localId});
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

  // Protocole photo (§4.1, anti-procuration) : `scanChar` reçoit désormais
  // plusieurs écritures préfixées d'un octet de tag — DOIT correspondre
  // exactement à esp32dev_borne/include/config.h (BLE_TAG_*). Un JPEG brut
  // dépasse largement le MTU négocié : on le découpe en chunks tagués avant
  // d'envoyer la requête JSON finale, que la borne réassemble.
  static const int _tagPhotoChunk = 0x01;
  static const int _tagScanFinal = 0x02;
  // JSON final envoyé en plusieurs écritures (comme la photo) quand il ne
  // tient pas dans une seule : constaté sur un téléphone bas de gamme (chipset
  // MediaTek/Unisoc, Itel) qui ne négocie qu'un MTU de 255o — bien en dessous
  // des 512o supposés par défaut, cf. _negotiateChunkPayloadSize().
  static const int _tagJsonChunk = 0x03;
  // Plafond haut : borne la taille de chaque chunk même si l'appareil négocie
  // un MTU très généreux, pour garder des écritures GATT courtes.
  static const int _maxChunkPayloadSize = 480;

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

      // Négocie le MTU réel AVANT tout write de taille non triviale : le
      // constater trop tard fait échouer l'écriture platform-side avec
      // "data longer than allowed" (vu en prod sur un Itel bas de gamme,
      // chipset MediaTek/Unisoc, MTU négocié à 255o — bien en dessous des
      // 512o qu'on supposait par défaut). Appelée avant discoverServices()
      // sur le conseil de flutter_blue_plus (son propre predelay évite une
      // course avec une éventuelle mise à jour MTU automatique du périphérique).
      final chunkPayloadSize = await _negotiateChunkPayloadSize(device);

      // Petite pause avant la première opération GATT : sur certains
      // téléphones (Samsung notamment), enchaîner discoverServices() puis un
      // write immédiatement déclenche une erreur GATT générique (133) alors
      // que la connexion est en réalité valide.
      await Future.delayed(const Duration(milliseconds: 300));

      await resultChar.setNotifyValue(true);
      // Timeout généreux : la borne attend la dernière écriture (tag final)
      // avant de répondre, et les chunks JSON peuvent prendre du temps sur un
      // lien BLE lent.
      final responseFuture = resultChar.onValueReceived.first.timeout(const Duration(seconds: 15));

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
      final body = utf8.encode(jsonEncode({
        'type': type,
        'teacher_token': teacherToken,
        'payload': payload,
        'captured_at': DateTime.now().toUtc().toIso8601String(),
      }));
      // Tag 0x02 sur le dernier morceau : signale à la borne que c'est la fin
      // de la requête. Le JSON peut désormais dépasser un seul chunk (tag 0x03
      // pour les morceaux intermédiaires) :
      // constaté nécessaire sur le même téléphone bas de gamme que ci-dessus,
      // dont le MTU négocié (255o) est parfois trop court pour un JSON avec un
      // long token/qr_code. Voir esp32dev_borne/src/main.cpp (BLE_TAG_*).
      await _sendFramedBytes(scanChar, body, _tagJsonChunk, _tagScanFinal, chunkPayloadSize);

      final responseBytes = await responseFuture;
      debugPrint('[timing] scanViaBorne BLE total=${total.elapsedMilliseconds}ms (chunk=${chunkPayloadSize}o)');
      return _parseBorneResponse(utf8.decode(responseBytes));
    } finally {
      unawaited(device.disconnect());
    }
  }

  /// Détermine combien d'octets de données caser par écriture GATT, en plus
  /// de l'octet de tag : `requestMtu()` n'existe que sur Android (throw sur
  /// les autres plateformes, cf. doc flutter_blue_plus) — sur iOS on se fie
  /// à `mtuNow`, déjà mis à jour par la néociation automatique de l'OS.
  /// Best-effort : toute erreur retombe sur `mtuNow` (ou son défaut ATT de
  /// 23o si vraiment rien n'est connu) plutôt que de bloquer le scan.
  Future<int> _negotiateChunkPayloadSize(BluetoothDevice device) async {
    int mtu = device.mtuNow;
    try {
      if (!kIsWeb && Platform.isAndroid) {
        mtu = await device.requestMtu(517);
      }
    } catch (e) {
      debugPrint('[ble] requestMtu indisponible, on garde le MTU déjà négocié ($mtu o): $e');
    }
    // mtu - 3 (en-tête ATT) - 1 (notre octet de tag), avec un plancher bas
    // pour rester fonctionnel même sur un MTU minimal (23o par défaut).
    return (mtu - 4).clamp(16, _maxChunkPayloadSize);
  }

  /// Découpe [bytes] en écritures GATT successives d'au plus [chunkPayloadSize]
  /// octets de données chacune, préfixées d'un octet de tag — [continuationTag]
  /// pour tous les morceaux sauf le dernier, [finalTag] pour le dernier.
  Future<void> _sendFramedBytes(
    BluetoothCharacteristic scanChar,
    List<int> bytes,
    int continuationTag,
    int finalTag,
    int chunkPayloadSize,
  ) async {
    if (bytes.isEmpty) {
      await scanChar.write(Uint8List.fromList([finalTag]), withoutResponse: false);
      return;
    }
    for (var offset = 0; offset < bytes.length; offset += chunkPayloadSize) {
      final end = min(offset + chunkPayloadSize, bytes.length);
      final isLast = end == bytes.length;
      final chunk = Uint8List(1 + (end - offset))
        ..[0] = isLast ? finalTag : continuationTag
        ..setRange(1, 1 + (end - offset), bytes.sublist(offset, end));
      await scanChar.write(chunk, withoutResponse: false);
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

  // Scan sans filtre natif (`withServices`) : sur certains chipsets bas de
  // gamme (MediaTek/Unisoc, ex. Transsion Tecno/Infinix/Itel — reproduit sur
  // un Tecno KJ5), le `ScanFilter` natif Android par UUID de service 128-bit
  // ne matche jamais, même quand la borne annonce bien cet UUID dans le
  // paquet d'advertising primaire (confirmé via nRF Connect : la borne est
  // visible en scan non filtré). On filtre donc manuellement les résultats
  // côté Dart plutôt que de déléguer au filtre natif, qui est cassé sur ces
  // téléphones.
  Future<BluetoothDevice?> _findBorne() async {
    final completer = Completer<BluetoothDevice?>();
    final sub = FlutterBluePlus.scanResults.listen((results) {
      if (completer.isCompleted) return;
      for (final result in results) {
        if (result.advertisementData.serviceUuids.contains(serviceUuid)) {
          completer.complete(result.device);
          break;
        }
      }
    });

    await FlutterBluePlus.startScan(timeout: const Duration(seconds: 8));
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
      );
    }

    throw ApiException((decoded['error'] as String?) ?? 'La borne a refusé le scan.', 0);
  }
}
