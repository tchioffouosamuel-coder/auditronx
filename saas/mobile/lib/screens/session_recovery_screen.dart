import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_client.dart';
import '../services/session.dart';
import '../widgets/auth/auth_scaffold.dart';

class SessionRecoveryScreen extends StatefulWidget {
  const SessionRecoveryScreen({super.key});

  @override
  State<SessionRecoveryScreen> createState() => _SessionRecoveryScreenState();
}

class _SessionRecoveryScreenState extends State<SessionRecoveryScreen> {
  final _password = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _busy = false, _obscure = true;
  String? _error;

  Future<void> _restore() async {
    if (_busy || !(_form.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<Session>().restoreSession(_password.text);
      if (mounted) _password.clear();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Connexion impossible. Réessayez.');
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    return PopScope(
      canPop: false,
      child: AuthScaffold(
        title: 'Rétablir ma session',
        subtitle: [
          if (session.nom.isNotEmpty) session.nom,
          if (session.boundTelephone != null) session.boundTelephone!,
          if (session.boundEtablissement != null) session.boundEtablissement!,
        ].join('\n'),
        icon: Icons.lock_reset_rounded,
        step: 0,
        steps: 1,
        child: Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              TextFormField(
                key: const ValueKey('recovery-password'),
                controller: _password,
                enabled: !_busy,
                obscureText: _obscure,
                autocorrect: false,
                enableSuggestions: false,
                autofillHints: const [AutofillHints.password],
                textInputAction: TextInputAction.done,
                decoration: InputDecoration(
                  labelText: 'Mot de passe',
                  floatingLabelBehavior: FloatingLabelBehavior.always,
                  errorMaxLines: 3,
                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                  suffixIcon: IconButton(
                    tooltip: _obscure
                        ? 'Afficher le mot de passe'
                        : 'Masquer le mot de passe',
                    onPressed: _busy
                        ? null
                        : () => setState(() => _obscure = !_obscure),
                    icon: Icon(
                      _obscure
                          ? Icons.visibility_outlined
                          : Icons.visibility_off_outlined,
                    ),
                  ),
                ),
                validator: (value) => value == null || value.isEmpty
                    ? 'Saisissez votre mot de passe.'
                    : null,
                onFieldSubmitted: (_) => _restore(),
              ),
              if (_error != null) AuthError(_error!),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _busy ? null : _restore,
                child: _busy
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Flexible(
                            child: Text(
                              'Rétablir',
                              textAlign: TextAlign.center,
                            ),
                          ),
                          SizedBox(width: 10),
                          Icon(Icons.refresh_rounded, size: 20),
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
