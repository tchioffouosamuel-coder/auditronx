import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_client.dart';
import '../services/session.dart';
import '../theme.dart';
import 'admin/admin_login_screen.dart';
import 'choix_etablissement.dart';

/// Activation en deux temps : choix de l'établissement, puis identification
/// par téléphone et mot de passe.
///
/// L'application est la même pour tous les abonnés : rien dans le binaire ne
/// dit à quel établissement ce téléphone appartient. Le choix est demandé une
/// seule fois, mémorisé dans le stockage sécurisé, et envoyé ensuite dans
/// l'en-tête `X-Tenant` de chaque requête.
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

  /// Établissement déjà mémorisé sur ce téléphone, et son branding en cache.
  String? _etablissement;
  Map<String, dynamic>? _branding;
  bool _chargementEtablissement = true;

  @override
  void initState() {
    super.initState();
    _loadSavedTel();
    _chargerEtablissement();
  }

  Future<void> _loadSavedTel() async {
    final tel = await context.read<Session>().lastTel;
    if (mounted && tel != null) _telController.text = tel;
  }

  Future<void> _chargerEtablissement() async {
    final code = await ApiClient.instance.etablissement;
    final branding = await ApiClient.instance.brandingEnCache;

    if (!mounted) return;

    setState(() {
      _etablissement = code;
      _branding = branding;
      _chargementEtablissement = false;
    });
  }

  /// Dissocie ce téléphone de l'établissement courant : la session en cours
  /// ne vaut plus rien ailleurs, elle est donc effacée avec le code.
  Future<void> _changerEtablissement() async {
    await ApiClient.instance.oublierEtablissement();

    if (!mounted) return;

    setState(() {
      _etablissement = null;
      _branding = null;
      _error = null;
    });
  }

  @override
  void dispose() {
    _telController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_telController.text.trim().isEmpty ||
        _passwordController.text.isEmpty) {
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await context.read<Session>().requestActivation(
        _telController.text.trim(),
        _passwordController.text,
      );
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (e) {
      // Toute autre erreur (stockage du téléphone, réponse inattendue...) :
      // sans message, le bouton semblait simplement ne rien faire.
      debugPrint('Connexion impossible : $e');
      if (mounted) {
        setState(
          () => _error =
              'Connexion impossible sur ce téléphone. Réessayez ; si le problème persiste, réinstallez l\'application.',
        );
      }
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
              // Logo de l'établissement s'il est connu, logo Auditron sinon :
              // une seule app, mais l'enseignant doit reconnaître son lycée.
              _branding?['logo_url'] != null
                  ? Image.network(
                      _branding!['logo_url'] as String,
                      height: 88,
                      errorBuilder: (_, _, _) =>
                          Image.asset('assets/logo.png', height: 88),
                    )
                  : Image.asset('assets/logo.png', height: 88),
              const SizedBox(height: 20),
              Text(
                (_branding?['nom'] as String?) ?? 'Auditron X',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                "L'assiduité intelligente au service de l'éducation.",
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AuditronColors.gold500,
                  fontSize: 13,
                  fontStyle: FontStyle.italic,
                ),
              ),
              const SizedBox(height: 32),
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: _chargementEtablissement
                    ? const Padding(
                        padding: EdgeInsets.symmetric(vertical: 24),
                        child: Center(
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      )
                    // Étape 1 : l'établissement. Étape 2 : les identifiants.
                    : _etablissement == null
                    ? ChoixEtablissement(
                        onChoisi: (branding) => setState(() {
                          _branding = branding;
                          _etablissement = branding?['code'] as String?;
                        }),
                      )
                    : Column(
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
                          icon: Icon(
                            _obscurePassword
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                          ),
                          tooltip: _obscurePassword
                              ? 'Afficher le mot de passe'
                              : 'Masquer le mot de passe',
                          onPressed: () => setState(
                            () => _obscurePassword = !_obscurePassword,
                          ),
                        ),
                      ),
                      onSubmitted: (_) => _submit(),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        _error!,
                        style: const TextStyle(color: Colors.red),
                        textAlign: TextAlign.center,
                      ),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _submitting ? null : _submit,
                      child: _submitting
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Se connecter'),
                    ),
                    const SizedBox(height: 12),
                    TextButton(
                      onPressed: _submitting
                          ? null
                          : () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const AdminLoginScreen(),
                              ),
                            ),
                      child: const Text(
                        'Se connecter en tant qu\'administrateur',
                      ),
                    ),
                    TextButton(
                      onPressed: _submitting ? null : _changerEtablissement,
                      child: Text(
                        'Changer d\'établissement ($_etablissement)',
                        style: const TextStyle(fontSize: 12),
                      ),
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
