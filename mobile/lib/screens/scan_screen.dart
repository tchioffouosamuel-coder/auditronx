import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../services/ble_service.dart';
import '../theme.dart';

/// Écran de scan générique (§4.1, §4.3, §hardware) : lecture du QR papier fixe
/// au point de contrôle, puis transmission à la borne ESP32 en BLE local
/// (jamais à l'API distante directement — la borne met le paquet en file sur
/// sa carte SD et le pousse elle-même vers l'API à son rythme). Le téléphone
/// n'a donc besoin d'internet qu'une seule fois, à l'activation de l'app —
/// jamais pendant un scan.
class ScanScreen extends StatefulWidget {
  final String title;
  final String type; // 'scan' (pointage personnel) ou 'admin_proxy'
  final int? enseignantId; // requis pour 'admin_proxy'
  final String? motif; // requis pour 'admin_proxy'

  const ScanScreen({
    super.key,
    required this.title,
    this.type = 'scan',
    this.enseignantId,
    this.motif,
  });

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  final _controller = MobileScannerController();
  final _ble = BleService();
  bool _processing = false;

  Future<void> _handleCode(String code) async {
    if (_processing) return;
    setState(() => _processing = true);
    // Coupe la détection pendant le traitement : tant que le QR reste dans le
    // champ de la caméra, onDetect se redéclencherait immédiatement après
    // chaque tentative échouée et relancerait la connexion BLE en boucle très
    // rapide (symptôme observé avec le WiFi : caméra qui clignote, rien ne se passe).
    await _controller.stop();

    bool success = false;
    try {
      final teacherToken = await ApiClient.instance.token;
      if (teacherToken == null) {
        _showMessage('Session expirée, merci de vous réactiver.', error: true);
        return;
      }

      if (!await _ble.isBluetoothEnabled()) {
        _showBluetoothDisabledMessage();
        return;
      }

      final result = await _ble.scanViaBorne(
        type: widget.type,
        teacherToken: teacherToken,
        qrCode: code,
        enseignantId: widget.enseignantId,
        motif: widget.motif,
      );

      _showMessage(
        result.photoCaptured
            ? 'Pointage transmis à la borne avec photo — synchronisation en cours.'
            : 'Pointage transmis à la borne — synchronisation en cours.',
      );
      success = true;
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      _showMessage(e.message, error: true);
    } finally {
      if (!success) {
        // Laisse le temps à l'utilisateur d'écarter le QR du champ de la
        // caméra avant de rouvrir la détection, sinon la même tentative
        // échouée repartirait aussitôt en boucle.
        await Future.delayed(const Duration(seconds: 2));
        if (mounted) await _controller.start();
      }
      if (mounted) setState(() => _processing = false);
    }
  }

  void _showMessage(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? Colors.red : Colors.green,
      ),
    );
  }

  /// Contrairement au WiFi, Android autorise une app à demander l'activation
  /// du Bluetooth directement — un seul tap suffit, pas besoin d'un panneau
  /// système séparé (voir BleService.requestEnableBluetooth).
  void _showBluetoothDisabledMessage() {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text(
          'Le Bluetooth est désactivé. Activez-le pour vous connecter à la borne.',
        ),
        backgroundColor: Colors.red,
        action: SnackBarAction(
          label: 'Activer',
          onPressed: _ble.requestEnableBluetooth,
        ),
        duration: const Duration(seconds: 6),
      ),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: Stack(
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: (capture) {
              final code = capture.barcodes.firstOrNull?.rawValue;
              if (code != null) _handleCode(code);
            },
          ),
          if (_processing) const _QrProcessingOverlay(),
        ],
      ),
    );
  }
}

class _QrProcessingOverlay extends StatefulWidget {
  const _QrProcessingOverlay();

  @override
  State<_QrProcessingOverlay> createState() => _QrProcessingOverlayState();
}

class _QrProcessingOverlayState extends State<_QrProcessingOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _animation = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1700),
  )..repeat();

  @override
  void dispose() {
    _animation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: Colors.black54,
      child: Center(
        child: Container(
          width: 238,
          padding: const EdgeInsets.fromLTRB(20, 22, 20, 18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: const [
              BoxShadow(
                color: Colors.black38,
                blurRadius: 24,
                offset: Offset(0, 10),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedBuilder(
                animation: _animation,
                builder: (context, child) => CustomPaint(
                  size: const Size.square(126),
                  painter: _QrLoaderPainter(_animation.value),
                  child: child,
                ),
                child: const Center(
                  child: Icon(
                    Icons.qr_code_2,
                    size: 54,
                    color: AuditronColors.brand800,
                  ),
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Transmission en cours',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AuditronColors.ink900,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Connexion à la borne...',
                textAlign: TextAlign.center,
                style: TextStyle(color: AuditronColors.ink500, fontSize: 13),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QrLoaderPainter extends CustomPainter {
  const _QrLoaderPainter(this.progress);

  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    const cornerLength = 25.0;
    const strokeWidth = 4.0;
    final cornerPaint = Paint()
      ..color = AuditronColors.brand600
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    final linePaint = Paint()
      ..color = AuditronColors.gold500
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round;
    final rect = Offset.zero & size;
    final path = Path()
      ..moveTo(rect.left + cornerLength, rect.top)
      ..lineTo(rect.left, rect.top)
      ..lineTo(rect.left, rect.top + cornerLength)
      ..moveTo(rect.right - cornerLength, rect.top)
      ..lineTo(rect.right, rect.top)
      ..lineTo(rect.right, rect.top + cornerLength)
      ..moveTo(rect.left, rect.bottom - cornerLength)
      ..lineTo(rect.left, rect.bottom)
      ..lineTo(rect.left + cornerLength, rect.bottom)
      ..moveTo(rect.right, rect.bottom - cornerLength)
      ..lineTo(rect.right, rect.bottom)
      ..lineTo(rect.right - cornerLength, rect.bottom);
    canvas.drawPath(path, cornerPaint);

    final scanY = 10 + (size.height - 20) * progress;
    canvas.drawLine(
      Offset(12, scanY),
      Offset(size.width - 12, scanY),
      linePaint,
    );
  }

  @override
  bool shouldRepaint(_QrLoaderPainter oldDelegate) =>
      oldDelegate.progress != progress;
}

extension on List<Barcode> {
  Barcode? get firstOrNull => isEmpty ? null : first;
}
