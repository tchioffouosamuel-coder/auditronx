import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/plateforme_session.dart';
import '../../widgets/auth/credential_login_screen.dart';

class PlateformeLoginScreen extends StatelessWidget {
  const PlateformeLoginScreen({super.key});
  @override
  Widget build(BuildContext context) => CredentialLoginScreen(
    scope: 'Équipe Auditron',
    contextLabel: 'Votre compte du portail central Auditron.',
    login: (email, password) =>
        context.read<PlateformeSession>().login(email, password),
    onSuccess: () => Navigator.of(context).popUntil((route) => route.isFirst),
  );
}
