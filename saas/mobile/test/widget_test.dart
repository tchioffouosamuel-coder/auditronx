import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:auditron_x_app/main.dart';
import 'package:auditron_x_app/screens/choix_etablissement.dart';

void main() {
  // flutter_secure_storage n'a pas d'implémentation native dans les tests
  // widget (pas d'appareil réel) : sans mock, son appel de MethodChannel ne
  // se résout jamais et Session.bootstrap() reste bloqué indéfiniment.
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  setUp(() {
    TestWidgetsFlutterBinding.ensureInitialized();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
          if (call.method == 'read') {
            final args = call.arguments;
            if (args is Map && args['key'] == 'auditron_etablissement') {
              return 'TEST';
            }
            return null;
          }
          if (call.method == 'readAll') return <String, String>{};
          return null;
        });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  testWidgets(
    'affiche un indicateur de chargement puis l\'écran d\'identification',
    (WidgetTester tester) async {
      await tester.pumpWidget(const AuditronXApp());

      // Avant que Session.bootstrap() ne se termine, l'app affiche un loader.
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      // pumpAndSettle() ne se termine jamais tant qu'un CircularProgressIndicator
      // indéterminé anime — on laisse simplement le temps à bootstrap() de finir.
      // Trois sessions distinctes sont désormais restaurées au démarrage :
      // laisser leurs lectures sécurisées se terminer avant de vérifier l'écran.
      for (var i = 0; i < 10; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }

      // Aucun jeton stocké → login établissement et accès au portail central.
      expect(find.text('Auditron-X'), findsOneWidget);
      expect(find.text('Votre téléphone'), findsOneWidget);
      expect(find.text('Continuer'), findsOneWidget);
      expect(find.text('Mot de passe'), findsNothing);
      expect(find.text('Connexion équipe Auditron'), findsOneWidget);
    },
  );

  testWidgets('demande le code sans exposer le catalogue des établissements', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: ChoixEtablissement(onChoisi: (_) {})),
      ),
    );

    expect(find.text('Code de l’établissement'), findsOneWidget);
    expect(find.text('Continuer'), findsOneWidget);
    expect(find.text('Scanner un QR'), findsOneWidget);
    expect(find.text('Retrouver mon établissement'), findsNothing);
    expect(find.byType(ListTile), findsNothing);
  });
}
