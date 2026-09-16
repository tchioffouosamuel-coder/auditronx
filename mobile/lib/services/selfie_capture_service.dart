import 'dart:typed_data';

import 'package:camera/camera.dart';
import 'package:flutter/foundation.dart' show debugPrint;
import 'package:image/image.dart' as img;

/// Capture un selfie basse résolution avec la caméra avant au moment du scan
/// (§4.1, anti-procuration) : preuve visuelle que c'est bien la personne
/// physiquement présente qui pointe, pas un téléphone prêté à un collègue.
///
/// Best-effort et jamais bloquant : un échec (pas de caméra avant, permission
/// refusée, capture ratée) renvoie `null` — le pointage continue sans preuve
/// visuelle plutôt que d'empêcher un scan légitime (voir ScanScreen).
///
/// Un seul flux caméra actif à la fois sur la quasi-totalité des téléphones :
/// le scanner QR (caméra arrière) est donc déjà arrêté par l'appelant avant
/// d'invoquer cette capture, séquentiellement.
class SelfieCaptureService {
  static const int _targetWidth = 160;
  static const int _jpegQuality = 20;

  Future<Uint8List?> captureLowResSelfie() async {
    CameraController? controller;
    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) return null;
      final frontCamera = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );

      controller = CameraController(
        frontCamera,
        ResolutionPreset.low,
        enableAudio: false,
      );
      await controller.initialize();
      final file = await controller.takePicture();
      final original = await file.readAsBytes();
      return _compress(original);
    } catch (e) {
      debugPrint('[selfie] capture indisponible, scan envoyé sans preuve visuelle: $e');
      return null;
    } finally {
      await controller?.dispose();
    }
  }

  /// Redimensionne et recompresse fortement : ~160px de large, JPEG qualité
  /// ~20, pour tenir en quelques Ko malgré l'envoi en chunks sur un lien BLE
  /// bien plus lent qu'un upload réseau classique (voir ble_service.dart).
  Uint8List? _compress(Uint8List original) {
    final decoded = img.decodeImage(original);
    if (decoded == null) return null;
    final resized = decoded.width > _targetWidth
        ? img.copyResize(decoded, width: _targetWidth)
        : decoded;
    return Uint8List.fromList(img.encodeJpg(resized, quality: _jpegQuality));
  }
}
