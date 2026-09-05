import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_client.dart';
import '../services/session.dart';
import '../theme.dart';
import 'otp_entry_screen.dart';

/// Identification (§4.1 revu) : téléphone + mot de passe. Un enseignant admin
/// est activé immédiatement ; sinon l'écran de saisie de l'OTP (remis en
/// personne par l'administration) prend le relais.
class ActivationScreen extends StatefulWidget {
  const ActivationScreen({super.key});

  @override
  State<ActivationScreen> createState() => _ActivationScreenState();
}

class _ActivationScreenState extends State<ActivationScreen> {
  final _telController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _submitting = false;
  bool _obscurePassword = true;
  String? _error;

  Future<void> _submit() async {
    if (_telController.text.trim().isEmpty || _passwordController.text.isEmpty) return;

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final activated = await context.read<Session>().requestActivation(
            _telController.text.trim(),
            _passwordController.text,
          );

      if (!activated && mounted) {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => const OtpEntryScreen()));
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AuditronColors.brand900,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 40),
              Image.asset('assets/logo.png', height: 88),
              const SizedBox(height: 20),
              const Text(
                'Auditron X',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 4),
              const Text(
                "L'assiduité intelligente au service de l'éducation.",
                textAlign: TextAlign.center,
                style: TextStyle(color: AuditronColors.gold500, fontSize: 13, fontStyle: FontStyle.italic),
              ),
              const SizedBox(height: 32),
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20)),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Connectez-vous avec votre numéro de téléphone et votre mot de passe.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AuditronColors.ink700),
                    ),
                    const SizedBox(height: 20),
                    TextField(
                      controller: _telController,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(labelText: 'Téléphone'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      decoration: InputDecoration(
                        labelText: 'Mot de passe',
                        suffixIcon: IconButton(
                          icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          tooltip: _obscurePassword ? 'Afficher le mot de passe' : 'Masquer le mot de passe',
                          onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                        ),
                      ),
                      onSubmitted: (_) => _submit(),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(_error!, style: const TextStyle(color: Colors.red), textAlign: TextAlign.center),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Text('Se connecter'),
                    ),
                    const SizedBox(height: 12),
                    TextButton(
                      onPressed: _submitting
                          ? null
                          : () => Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => const OtpEntryScreen()),
                              ),
                      child: const Text("J'ai déjà reçu mon code d'activation"),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
