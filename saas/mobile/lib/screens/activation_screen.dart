import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_client.dart';
import '../services/session.dart';
import '../widgets/auth/auth_scaffold.dart';
import '../widgets/auth/credential_login_screen.dart';
import 'admin/admin_login_screen.dart';
import 'choix_etablissement.dart';
import 'plateforme/plateforme_login_screen.dart';

class ActivationScreen extends StatefulWidget {
  const ActivationScreen({super.key});
  @override
  State<ActivationScreen> createState() => _ActivationScreenState();
}

class _ActivationScreenState extends State<ActivationScreen> {
  String? _code;
  Map<String, dynamic>? _branding;
  bool _loading = true;
  bool _choosing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final code = await ApiClient.instance.etablissement;
      final branding = await ApiClient.instance.brandingEnCache;
      if (mounted) {
        setState(() {
          _code = code;
          _branding = branding;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'Impossible de lire votre établissement.');
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _change() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await ApiClient.instance.oublierEtablissement();
      if (mounted) {
        setState(() {
          _code = null;
          _branding = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Impossible de changer d’établissement. Réessayez.',
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Widget _footer(bool busy) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (_error != null) AuthError(_error!),
      if (_code != null)
        TextButton.icon(
          onPressed: busy
              ? null
              : () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const AdminLoginScreen()),
                ),
          icon: const Icon(Icons.admin_panel_settings_outlined, size: 18),
          label: const Text(
            'Espace administration',
            textAlign: TextAlign.center,
          ),
        ),
      TextButton.icon(
        onPressed: busy
            ? null
            : () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const PlateformeLoginScreen(),
                ),
              ),
        icon: const Icon(Icons.business_outlined, size: 18),
        label: const Text(
          'Connexion équipe Auditron',
          textAlign: TextAlign.center,
        ),
      ),
    ],
  );

  @override
  Widget build(BuildContext context) {
    if (_loading || _code == null) {
      return AuthScaffold(
        title: 'Votre établissement',
        subtitle:
            'Le code communiqué par votre établissement, ou son QR d’enrôlement.',
        icon: Icons.apartment_outlined,
        step: 0,
        steps: 3,
        footer: _footer(_loading || _choosing),
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : ChoixEtablissement(
                onBusyChanged: (busy) => setState(() => _choosing = busy),
                onChoisi: (branding) async {
                  final code = await ApiClient.instance.etablissement;
                  if (mounted) {
                    setState(() {
                      _code = code;
                      _branding = branding;
                      _error = null;
                      _choosing = false;
                    });
                  }
                },
              ),
      );
    }
    return CredentialLoginScreen(
      key: ValueKey(_code),
      scope: 'Espace enseignant',
      phone: true,
      steps: 3,
      stepOffset: 1,
      contextLabel: '${_branding?['nom'] ?? 'Établissement'} · $_code',
      contextLogoUrl: _branding?['logo_url'] as String?,
      savedIdentifier: context.read<Session>().lastTel,
      login: (telephone, password) =>
          context.read<Session>().requestActivation(telephone, password),
      onBack: _change,
      footerBuilder: _footer,
    );
  }
}
