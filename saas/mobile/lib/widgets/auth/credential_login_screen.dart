import 'package:flutter/material.dart';
import '../../services/api_client.dart';
import 'auth_scaffold.dart';

class CredentialLoginScreen extends StatefulWidget {
  const CredentialLoginScreen({
    super.key,
    required this.scope,
    required this.login,
    this.phone = false,
    this.contextLabel,
    this.contextLogoUrl,
    this.savedIdentifier,
    this.onSuccess,
    this.onBack,
    this.footerBuilder,
    this.stepOffset = 0,
    this.steps = 2,
  });
  final String scope;
  final bool phone;
  final String? contextLabel;
  final String? contextLogoUrl;
  final Future<String?>? savedIdentifier;
  final Future<void> Function(String identifier, String password) login;
  final VoidCallback? onSuccess, onBack;
  final Widget Function(bool busy)? footerBuilder;
  final int stepOffset, steps;

  @override
  State<CredentialLoginScreen> createState() => _CredentialLoginScreenState();
}

class _CredentialLoginScreenState extends State<CredentialLoginScreen> {
  final _identifier = TextEditingController();
  final _password = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _passwordStep = false, _busy = false, _obscure = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadIdentifier();
  }

  Future<void> _loadIdentifier() async {
    try {
      final value = await widget.savedIdentifier;
      if (mounted && _identifier.text.isEmpty && value != null) {
        _identifier.text = value;
      }
    } catch (error) {
      debugPrint('Identifiant mémorisé indisponible : $error');
    }
  }

  void _back() {
    if (_busy) return;
    FocusScope.of(context).unfocus();
    if (_passwordStep) {
      setState(() {
        _passwordStep = false;
        _password.clear();
        _obscure = true;
        _error = null;
      });
    } else if (widget.onBack != null) {
      widget.onBack!();
    } else {
      Navigator.of(context).maybePop();
    }
  }

  Future<void> _continue() async {
    if (_busy || !(_form.currentState?.validate() ?? false)) return;
    FocusScope.of(context).unfocus();
    if (!_passwordStep) {
      setState(() {
        _passwordStep = true;
        _error = null;
      });
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.login(_identifier.text.trim(), _password.text);
      if (mounted) widget.onSuccess?.call();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      debugPrint('Connexion impossible : $error');
      if (mounted) {
        setState(
          () => _error =
              'Connexion impossible. Vérifiez votre connexion et réessayez.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _identifier.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final canGoBack =
        _passwordStep ||
        widget.onBack != null ||
        Navigator.of(context).canPop();
    return PopScope(
      canPop: !_busy && !_passwordStep && widget.onBack == null,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop && !_busy && (_passwordStep || widget.onBack != null)) {
          _back();
        }
      },
      child: AuthScaffold(
        scope: widget.scope,
        step: widget.stepOffset + (_passwordStep ? 1 : 0),
        steps: widget.steps,
        icon: _passwordStep
            ? Icons.lock_outline_rounded
            : widget.phone
            ? Icons.phone_iphone_rounded
            : Icons.alternate_email_rounded,
        title: _passwordStep
            ? 'Votre mot de passe'
            : widget.phone
            ? 'Votre téléphone'
            : 'Votre adresse email',
        subtitle: _passwordStep
            ? _identifier.text.trim()
            : widget.contextLabel ??
                  (widget.phone
                      ? 'Le numéro associé à votre compte enseignant.'
                      : 'L’adresse associée à votre compte.'),
        onBack: canGoBack && !_busy ? _back : null,
        footer: widget.footerBuilder?.call(_busy),
        contextLogoUrl: _passwordStep ? null : widget.contextLogoUrl,
        child: AutofillGroup(
          child: Form(
            key: _form,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 180),
                  child: _passwordStep
                      ? TextFormField(
                          key: const ValueKey('password'),
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
                          onFieldSubmitted: (_) => _continue(),
                        )
                      : TextFormField(
                          key: const ValueKey('identifier'),
                          controller: _identifier,
                          keyboardType: widget.phone
                              ? TextInputType.phone
                              : TextInputType.emailAddress,
                          autocorrect: false,
                          autofillHints: [
                            widget.phone
                                ? AutofillHints.telephoneNumber
                                : AutofillHints.username,
                          ],
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(
                            labelText: widget.phone ? 'Téléphone' : 'Email',
                            floatingLabelBehavior: FloatingLabelBehavior.always,
                            errorMaxLines: 3,
                            prefixIcon: Icon(
                              widget.phone
                                  ? Icons.phone_outlined
                                  : Icons.mail_outline_rounded,
                            ),
                          ),
                          validator: (value) {
                            final text = value?.trim() ?? '';
                            if (text.isEmpty) {
                              return widget.phone
                                  ? 'Saisissez votre numéro.'
                                  : 'Saisissez votre adresse email.';
                            }
                            if (widget.phone
                                ? !RegExp(r'\d').hasMatch(text)
                                : !RegExp(
                                    r'^[^\s@]+@[^\s@]+\.[^\s@]+$',
                                  ).hasMatch(text)) {
                              return widget.phone
                                  ? 'Vérifiez votre numéro.'
                                  : 'Vérifiez votre adresse email.';
                            }
                            return null;
                          },
                          onFieldSubmitted: (_) => _continue(),
                        ),
                ),
                if (_error != null) AuthError(_error!),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _busy ? null : _continue,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 3),
                    child: _busy
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Flexible(
                                child: Text(
                                  _passwordStep ? 'Se connecter' : 'Continuer',
                                  textAlign: TextAlign.center,
                                ),
                              ),
                              const SizedBox(width: 10),
                              const Icon(Icons.arrow_forward_rounded, size: 20),
                            ],
                          ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
