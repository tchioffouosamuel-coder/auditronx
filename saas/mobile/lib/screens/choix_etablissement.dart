import 'dart:async';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../services/etablissement_service.dart';
import '../widgets/auth/auth_scaffold.dart';

/// Première étape de l'activation : choisir son établissement.
///
/// L'application est unique pour tous les abonnés ; ce choix, fait une seule
/// fois, détermine la base de données interrogée pour toute la vie de
/// l'installation. L'établissement est choisi avec son code, saisi ou lu
/// depuis le QR d'enrôlement affiché par l'administration. Aucun catalogue
/// des clients n'est affiché sur cet écran.
class ChoixEtablissement extends StatefulWidget {
  const ChoixEtablissement({
    super.key,
    required this.onChoisi,
    this.onBusyChanged,
  });

  /// Appelé avec le branding de l'établissement retenu, une fois vérifié
  /// auprès de l'API.
  final FutureOr<void> Function(Map<String, dynamic>? branding) onChoisi;
  final ValueChanged<bool>? onBusyChanged;

  @override
  State<ChoixEtablissement> createState() => _ChoixEtablissementState();
}

class _ChoixEtablissementState extends State<ChoixEtablissement> {
  final _codeController = TextEditingController();

  bool _validation = false;
  String? _erreur;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _retenir(String code) async {
    if (_validation) return;
    if (code.trim().isEmpty) {
      setState(() => _erreur = 'Saisissez le code de votre établissement.');
      return;
    }
    setState(() {
      _validation = true;
      _erreur = null;
    });
    widget.onBusyChanged?.call(true);

    try {
      final branding = await EtablissementService.instance.verifier(code);
      if (!mounted) return;

      if (branding == null) {
        setState(() => _erreur = 'Code d’établissement invalide.');
        return;
      }

      await widget.onChoisi(branding);
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e.message);
    } catch (_) {
      if (mounted) {
        setState(() => _erreur = 'Vérification impossible. Réessayez.');
      }
    } finally {
      if (mounted) {
        setState(() => _validation = false);
        widget.onBusyChanged?.call(false);
      }
    }
  }

  Future<void> _scanner() async {
    final code = await Navigator.of(
      context,
    ).push<String>(MaterialPageRoute(builder: (_) => const _ScanEnrolement()));

    if (mounted && code != null && code.isNotEmpty) await _retenir(code);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _codeController,
          enabled: !_validation,
          autocorrect: false,
          textInputAction: TextInputAction.next,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Code de l’établissement',
            floatingLabelBehavior: FloatingLabelBehavior.always,
            hintText: 'Ex. LTM2026',
            prefixIcon: Icon(Icons.apartment_outlined),
          ),
          onSubmitted: (v) => _retenir(v),
        ),
        if (_erreur != null) AuthError(_erreur!),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: _validation ? null : () => _retenir(_codeController.text),
          child: _validation
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Text('Continuer'),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _validation ? null : _scanner,
          icon: const Icon(Icons.qr_code_scanner),
          label: const Text('Scanner un QR', textAlign: TextAlign.center),
        ),
      ],
    );
  }
}

/// Scan du QR d'enrôlement : il encode le seul code de l'établissement.
class _ScanEnrolement extends StatefulWidget {
  const _ScanEnrolement();

  @override
  State<_ScanEnrolement> createState() => _ScanEnrolementState();
}

class _ScanEnrolementState extends State<_ScanEnrolement> {
  bool _rendu = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('QR de l’établissement')),
      body: MobileScanner(
        onDetect: (capture) {
          if (_rendu) return;

          final valeur = capture.barcodes
              .map((b) => b.rawValue)
              .firstWhere((v) => v != null && v.isNotEmpty, orElse: () => null);

          if (valeur == null) return;

          _rendu = true;
          // Le QR peut contenir une URL d'enrôlement : on n'en garde que le
          // code, pour accepter les deux formes sans casser l'autre.
          Navigator.of(context).pop(_codeDepuis(valeur));
        },
      ),
    );
  }

  String _codeDepuis(String valeur) {
    final brut = valeur.contains('=')
        ? valeur.split('=').last
        : valeur.split('/').last;

    return ApiClient.normaliserCodeEtablissement(brut);
  }
}
