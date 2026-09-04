import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../services/wifi_service.dart';

/// Écran de scan générique (§4.1, §4.3) : lecture du QR papier fixe au point
/// de contrôle + lecture du BSSID de la borne WiFi connectée, puis envoi à
/// l'endpoint fourni par [onScanned]. L'horodatage est toujours généré côté
/// serveur — cet écran ne fait que transmettre le contexte du scan.
class ScanScreen extends StatefulWidget {
  final String title;
  final Future<Map<String, dynamic>> Function(String qrCode, String bssid) onScanned;

  const ScanScreen({super.key, required this.title, required this.onScanned});

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

    try {
      final bssid = await _wifi.currentBssid();
      if (bssid == null) {
        _showMessage("Impossible de lire la borne WiFi. Vérifiez la connexion et l'autorisation de localisation.", error: true);
        return;
      }

      final presence = await widget.onScanned(code, bssid);
      final sens = presence['heure_depart'] != null ? 'Départ' : 'Arrivée';
      _showMessage('$sens enregistrée avec succès.');
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      _showMessage(e.message, error: true);
    } finally {
      if (mounted) setState(() => _processing = false);
    }
  }

  void _showMessage(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: error ? Colors.red : Colors.green),
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
