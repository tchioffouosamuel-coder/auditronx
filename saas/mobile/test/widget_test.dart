import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:auditron_x_app/main.dart';

void main() {
  // flutter_secure_storage n'a pas d'implémentation native dans les tests
  // widget (pas d'appareil réel) : sans mock, son appel de MethodChannel ne
  // se résout jamais et Session.bootstrap() reste bloqué indéfiniment.
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  setUp(() {
    TestWidgetsFlutterBinding.ensureInitialized();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      channel,
      (call) async {
        if (call.method == 'read') return null;
        if (call.method == 'readAll') return <String, String>{};
        return null;
      },
    );
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(channel, null);
  });

  testWidgets('affiche un indicateur de chargement puis l\'écran d\'identification', (WidgetTester tester) async {
    await tester.pumpWidget(const AuditronXApp());

    // Avant que Session.bootstrap() ne se termine, l'app affiche un loader.
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    // pumpAndSettle() ne se termine jamais tant qu'un CircularProgressIndicator
    // indéterminé anime — on laisse simplement le temps à bootstrap() de finir.
    await tester.pump(const Duration(milliseconds: 100));

    // Aucun token stocké en test → l'app doit proposer l'identification (tel + mot de passe).
    expect(find.text('Auditron X'), findsWidgets);
    expect(find.text('Se connecter'), findsOneWidget);
    expect(find.text("J'ai déjà reçu mon code d'activation"), findsOneWidget);
  });
}
