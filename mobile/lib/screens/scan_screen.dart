import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';
import '../services/api_client.dart';
import '../services/ble_service.dart';
import '../services/presence_repository.dart';
import '../services/selfie_capture_service.dart';
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
  // Source du token transmis à la borne (`teacher_token`, §admin-mobile) :
  // celui de l'enseignant par défaut, mais l'admin backoffice (AdminApiClient)
  // scanne aussi par procuration avec son propre token.
  final Future<String?> Function()? tokenProvider;

  const ScanScreen({
    super.key,
    required this.title,
    this.type = 'scan',
    this.enseignantId,
    this.motif,
    this.tokenProvider,
  });

  @override
  State<ScanScreen> createState() => _ScanScreenState();
}

class _ScanScreenState extends State<ScanScreen> {
  MobileScannerController _controller = MobileScannerController();
  final _ble = BleService();
  final _presenceRepository = PresenceRepository();
  final _selfieCapture = SelfieCaptureService();
  bool _processing = false;
  bool _controllerDisposed = false;

  Future<void> _handleCode(String code) async {
    if (_processing) return;
    setState(() => _processing = true);
    // Coupe la détection pendant le traitement : tant que le QR reste dans le
    // champ de la caméra, onDetect se redéclencherait immédiatement après
    // chaque tentative échouée et relancerait la connexion BLE en boucle très
    // rapide (symptôme observé avec le WiFi : caméra qui clignote, rien ne se passe).
    await _controller.stop();
    await _controller.dispose();
    _controllerDisposed = true;

    bool success = false;
    try {
      // Caméra arrière (scanner) déjà arrêtée ci-dessus : la caméra avant
      // peut maintenant s'ouvrir sans conflit (une seule caméra active à la
      // fois sur la quasi-totalité des téléphones). Best-effort — un échec ne
      // doit jamais bloquer le pointage, voir SelfieCaptureService.
      final selfieJpeg = await _selfieCapture.captureLowResSelfie();
      final teacherToken =
          await (widget.tokenProvider ?? () => ApiClient.instance.token)();
      if (teacherToken == null) {
        _showMessage('Session expirée, merci de vous réactiver.', error: true);
        return;
      }

      final isPersonalScan = widget.type == 'scan';
      final wasDeparture =
          isPersonalScan && await _presenceRepository.hasOpenArrivalToday();
      final departureTimeRemaining = isPersonalScan
          ? await _presenceRepository.departureTimeRemainingToday()
          : null;
      if (departureTimeRemaining != null) {
        _showMessage(
          'Départ impossible : il reste ${_formatRemainingDuration(departureTimeRemaining)} avant de pouvoir pointer votre sortie.',
          error: true,
        );
        return;
      }

      if (!await _ensureLocationPermission()) {
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
        selfieJpeg: selfieJpeg,
      );

      if (isPersonalScan) {
        await _presenceRepository.markSuccessfulScan(
          wasDeparture: wasDeparture,
        );
      }

      await _showScanSuccessSheet(result.photoCaptured);
      success = true;
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (_isBorneError(e.message)) {
        await _showBorneUnavailableSheet(e.message);
      } else {
        _showMessage(e.message, error: true);
      }
    } finally {
      if (!success) {
        // Laisse le temps à l'utilisateur d'écarter le QR du champ de la
        // caméra avant de rouvrir la détection, sinon la même tentative
        // échouée repartirait aussitôt en boucle.
        await Future.delayed(const Duration(seconds: 2));
        if (mounted) {
          setState(() {
            _controller = MobileScannerController();
            _controllerDisposed = false;
          });
          await _controller.start();
        }
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

  Future<bool> _ensureLocationPermission() async {
    if (!Platform.isAndroid) return true;

    final serviceStatus = await Permission.locationWhenInUse.serviceStatus;
    if (serviceStatus == ServiceStatus.enabled) return true;

    final status = await Permission.locationWhenInUse.request();
    if (status.isGranted || status.isLimited) return true;

    final updatedServiceStatus =
        await Permission.locationWhenInUse.serviceStatus;
    if (updatedServiceStatus == ServiceStatus.enabled) return true;

    if (mounted) {
      await _showLocationRequiredSheet();
    }
    return false;
  }

  Future<void> _showLocationRequiredSheet() {
    return showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      backgroundColor: AuditronColors.ink50,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Icon(Icons.location_on, color: AuditronColors.brand700, size: 48),
              const SizedBox(height: 14),
              const Text(
                'Localisation nécessaire',
                style: TextStyle(
                  color: AuditronColors.ink900,
                  fontSize: 23,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Activez l’autorisation de localisation pour permettre la recherche de la borne à proximité.',
                style: TextStyle(
                  color: AuditronColors.ink700,
                  fontSize: 15,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 22),
              FilledButton.icon(
                onPressed: () async {
                  await openAppSettings();
                  if (context.mounted) Navigator.pop(context);
                },
                icon: const Icon(Icons.settings_outlined),
                label: const Text('Activer la localisation'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showScanSuccessSheet(bool photoCaptured) {
    return showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      backgroundColor: AuditronColors.ink50,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                width: 68,
                height: 68,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  color: AuditronColors.brand100,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_rounded,
                  color: AuditronColors.brand700,
                  size: 42,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Scan réussi',
                style: TextStyle(
                  color: AuditronColors.ink900,
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                photoCaptured
                    ? 'Votre pointage a été transmis avec la photo.'
                    : 'Votre pointage a été transmis à la borne.',
                style: const TextStyle(
                  color: AuditronColors.ink700,
                  fontSize: 15,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 22),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.done),
                label: const Text('Terminer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  bool _isBorneError(String message) {
    final normalized = message.toLowerCase();
    return normalized.contains('borne') ||
        normalized.contains('bluetooth') ||
        normalized.contains('communication');
  }

  Future<void> _showBorneUnavailableSheet(String message) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: AuditronColors.ink50,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                width: 64,
                height: 64,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.wifi_tethering_error_rounded,
                  color: Colors.red.shade700,
                  size: 34,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Borne injoignable',
                style: TextStyle(
                  color: AuditronColors.ink900,
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                style: const TextStyle(
                  color: AuditronColors.ink700,
                  fontSize: 15,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 18),
              const _BorneCheckRow(
                icon: Icons.bluetooth,
                text: 'Vérifiez que le Bluetooth est activé.',
              ),
              const _BorneCheckRow(
                icon: Icons.near_me_outlined,
                text: 'Rapprochez-vous de la borne.',
              ),
              const _BorneCheckRow(
                icon: Icons.power_settings_new,
                text: 'Vérifiez que la borne est allumée.',
              ),
              const SizedBox(height: 22),
              FilledButton.icon(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.refresh),
                label: const Text('Réessayer'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatRemainingDuration(Duration duration) {
    final totalMinutes = (duration.inSeconds / 60).ceil();
    if (totalMinutes < 1) return 'moins d’une minute';
    if (totalMinutes == 1) return '1 minute';
    if (totalMinutes < 60) return '$totalMinutes minutes';

    final hours = totalMinutes ~/ 60;
    final minutes = totalMinutes % 60;
    if (minutes == 0) return hours == 1 ? '1 heure' : '$hours heures';
    return hours == 1
        ? '1 heure et $minutes minute${minutes > 1 ? 's' : ''}'
        : '$hours heures et $minutes minute${minutes > 1 ? 's' : ''}';
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
    if (!_controllerDisposed) _controller.dispose();
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

// Overlay statique, sans animation (§4.1) : la version précédente tournait un
// AnimationController + CustomPaint en continu pendant tout l'échange BLE,
// ce qui donnait une impression de lenteur sans rien apporter au traitement
// réel — un simple indicateur suffit à signaler que le scan est en cours.
class _QrProcessingOverlay extends StatelessWidget {
  const _QrProcessingOverlay();

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
          child: const Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 32,
                height: 32,
                child: CircularProgressIndicator(
                  strokeWidth: 3,
                  color: AuditronColors.brand600,
                ),
              ),
              SizedBox(height: 18),
              Text(
                'Transmission en cours',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AuditronColors.ink900,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
              SizedBox(height: 6),
              Text(
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

extension on List<Barcode> {
  Barcode? get firstOrNull => isEmpty ? null : first;
}

class _BorneCheckRow extends StatelessWidget {
  final IconData icon;
  final String text;

  const _BorneCheckRow({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Icon(icon, color: AuditronColors.brand700, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: AuditronColors.ink700),
            ),
          ),
        ],
      ),
    );
  }
}
