import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_client.dart';
import '../services/session.dart';

/// Étape 2 de l'activation (§4.1 revu, §otp-approval) : l'enseignant saisit le
/// code OTP reçu par notification push une fois l'admin validée sa demande
/// (créée par l'écran d'identification) — plus besoin de le remettre en
/// personne.
class OtpEntryScreen extends StatefulWidget {
  const OtpEntryScreen({super.key});

  @override
  State<OtpEntryScreen> createState() => _OtpEntryScreenState();
}

class _OtpEntryScreenState extends State<OtpEntryScreen> {
  final _codeController = TextEditingController();
  bool _submitting = false;
  String? _error;
  StreamSubscription<RemoteMessage>? _foregroundSub;
  StreamSubscription<RemoteMessage>? _openedSub;

  @override
  void initState() {
    super.initState();
    // App au premier plan : l'OS n'affiche pas les notifications FCM, le code
    // poussé à la validation admin (type `otp_delivery`) serait perdu sans
    // cet écouteur — on pré-remplit directement le champ.
    _foregroundSub = FirebaseMessaging.onMessage.listen(_onPush);
    // Notification système touchée alors que l'app était en arrière-plan.
    _openedSub = FirebaseMessaging.onMessageOpenedApp.listen(_onPush);
  }

  @override
  void dispose() {
    _foregroundSub?.cancel();
    _openedSub?.cancel();
    _codeController.dispose();
    super.dispose();
  }

  void _onPush(RemoteMessage message) {
    final code = message.data['code'];
    if (message.data['type'] != 'otp_delivery' || code is! String || !mounted) return;

    _codeController.text = code;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text("Demande validée : code d'activation reçu.")),
    );
    if (!_submitting) _submit();
  }

  Future<void> _submit() async {
    if (_codeController.text.trim().isEmpty) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await context.read<Session>().activate(_codeController.text.trim());
      // Session bascule sur HomeScreen via le RootGate ; on referme cet écran.
      if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("Code d'activation")),
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              "Un administrateur doit valider votre demande. Saisissez ici le code à 6 chiffres reçu par notification une fois la demande approuvée.",
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _codeController,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 26, letterSpacing: 10, fontWeight: FontWeight.w700),
              decoration: const InputDecoration(hintText: '000000', counterText: ''),
              maxLength: 6,
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red), textAlign: TextAlign.center),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text("Activer l'application"),
            ),
          ],
        ),
      ),
    );
  }
}
