import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'screens/activation_screen.dart';
import 'screens/admin/admin_home_screen.dart';
import 'screens/home_screen.dart';
import 'services/admin_session.dart';
import 'services/offline/sync_engine.dart';
import 'services/push_notifications.dart';
import 'services/session.dart';
import 'theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

  // Mode offline (§offline-sync) : démarré une seule fois pour toute l'app,
  // rejoue automatiquement la file d'actions en attente au retour du réseau.
  unawaited(SyncEngine.instance.start());

  runApp(const AuditronXApp());
}

class AuditronXApp extends StatelessWidget {
  const AuditronXApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => Session()..bootstrap()),
        // Espace admin (§admin-mobile) : session indépendante de l'enseignant
        // — un même téléphone peut avoir les deux actives à la fois.
        ChangeNotifierProvider(create: (_) => AdminSession()..bootstrap()),
        ChangeNotifierProvider.value(value: SyncEngine.instance),
      ],
      child: MaterialApp(
        title: 'Auditron X',
        debugShowCheckedModeBanner: false,
        theme: buildAuditronTheme(),
        home: const _RootGate(),
      ),
    );
  }
}

/// Aiguille vers l'espace admin, l'activation OTP ou l'écran principal enseignant
/// selon l'état des deux sessions. L'enseignant reste le cas par défaut de
/// l'app ; l'admin y accède via un lien depuis [ActivationScreen].
class _RootGate extends StatelessWidget {
  const _RootGate();

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final adminSession = context.watch<AdminSession>();

    if (session.loading || adminSession.loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (session.activated) return const HomeScreen();
    if (adminSession.loggedIn) return const AdminHomeScreen();

    return const ActivationScreen();
  }
}
