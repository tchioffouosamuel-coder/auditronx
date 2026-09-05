import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../services/wifi_service.dart';

/// Écran de scan générique (§4.1, §4.3, §hardware) : lecture du QR papier fixe
/// au point de contrôle, puis transmission à la borne WiFi ESP32 en HTTP
/// local (jamais à l'API distante directement — la borne met le paquet en
/// file sur sa carte SD et le pousse elle-même vers l'API à son rythme). Le
/// téléphone n'a donc besoin d'internet qu'une seule fois, à l'activation de
/// l'app — jamais pendant un scan.
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
  final _wifi = WifiService();
  bool _processing = false;

  Future<void> _handleCode(String code) async {
    if (_processing) return;
    setState(() => _processing = true);
    // Coupe la détection pendant le traitement : tant que le QR reste dans le
    // champ de la caméra, onDetect se redéclencherait immédiatement après
    // chaque tentative échouée et relancerait la connexion WiFi en boucle
    // très rapide (symptôme observé : caméra qui clignote, rien ne se passe).
    await _controller.stop();

    bool success = false;
    try {
      final teacherToken = await ApiClient.instance.token;
      if (teacherToken == null) {
        _showMessage('Session expirée, merci de vous réactiver.', error: true);
        return;
      }

      if (!await _wifi.isWifiEnabled()) {
        _showWifiDisabledMessage();
        return;
      }

      final result = await _wifi.scanViaBorne(
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
      SnackBar(content: Text(message), backgroundColor: error ? Colors.red : Colors.green),
    );
  }

  /// Android bloque toute activation du WiFi par code depuis une app tierce
  /// (§ WifiService.isWifiEnabled) — seul un tap de l'utilisateur peut le
  /// faire, via le panneau rapide système.
  void _showWifiDisabledMessage() {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Le WiFi est désactivé. Activez-le pour vous connecter à la borne.'),
        backgroundColor: Colors.red,
        action: SnackBarAction(label: 'Activer', onPressed: _wifi.openWifiPanel),
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
          if (_processing)
            const ColoredBox(
              color: Colors.black45,
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }
}

extension on List<Barcode> {
  Barcode? get firstOrNull => isEmpty ? null : first;
}
