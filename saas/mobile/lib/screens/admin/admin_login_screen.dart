import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/admin_api_client.dart';
import '../../services/admin_session.dart';
import '../../widgets/auth/credential_login_screen.dart';

class AdminLoginScreen extends StatelessWidget {
  const AdminLoginScreen({super.key});
  @override
  Widget build(BuildContext context) => CredentialLoginScreen(
    scope: 'Espace administration',
    savedIdentifier: AdminApiClient.instance.lastEmail,
    login: (email, password) async {
      await context.read<AdminSession>().login(email, password);
      await AdminApiClient.instance.saveLastEmail(email);
    },
    onSuccess: () => Navigator.of(context).popUntil((route) => route.isFirst),
  );
}
