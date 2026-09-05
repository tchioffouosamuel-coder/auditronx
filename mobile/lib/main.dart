import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'screens/activation_screen.dart';
import 'screens/home_screen.dart';
import 'services/push_notifications.dart';
import 'services/session.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  runApp(const AuditronXApp());
}

class AuditronXApp extends StatelessWidget {
  const AuditronXApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => Session()..bootstrap(),
      child: MaterialApp(
        title: 'Auditron X',
        debugShowCheckedModeBanner: false,
        theme: buildAuditronTheme(),
        home: const _RootGate(),
      ),
    );
  }
}

/// Aiguille vers l'activation OTP ou l'écran principal selon l'état du device.
class _RootGate extends StatelessWidget {
  const _RootGate();

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    if (session.loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return session.activated ? const HomeScreen() : const ActivationScreen();
  }
}
