import 'dart:convert';

import 'package:auditron_x_app/screens/plateforme/plateforme_audit_screen.dart';
import 'package:auditron_x_app/screens/plateforme/plateforme_home_screen.dart';
import 'package:auditron_x_app/screens/plateforme/plateforme_plans_screen.dart';
import 'package:auditron_x_app/screens/plateforme/plateforme_presences_screen.dart';
import 'package:auditron_x_app/screens/plateforme/plateforme_utilisateurs_screen.dart';
import 'package:auditron_x_app/services/plateforme_session.dart';
import 'package:auditron_x_app/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';
import 'support/auditron_test_fonts.dart';

class _Session extends PlateformeSession {
  _Session(this.superAdmin);
  final bool superAdmin;

  @override
  bool get estSuperAdmin => superAdmin;

  @override
  String get nom => 'Equipe de demonstration';
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const storage = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  setUpAll(() async {
    await initializeDateFormatting('fr');
    await loadAuditronTestFonts();
  });
  setUp(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storage, (_) async => null);
  });
  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storage, null);
  });

  for (final superAdmin in [false, true]) {
    testWidgets('navigation sans facturation, superAdmin=$superAdmin', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(390, 844);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final requests = <String>[];
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            ChangeNotifierProvider<PlateformeSession>(
              create: (_) => _Session(superAdmin),
              child: MaterialApp(
                theme: buildAuditronTheme(),
                locale: const Locale('fr'),
                supportedLocales: const [Locale('fr')],
                localizationsDelegates: const [
                  GlobalMaterialLocalizations.delegate,
                  GlobalWidgetsLocalizations.delegate,
                  GlobalCupertinoLocalizations.delegate,
                ],
                home: const PlateformeHomeScreen(),
              ),
            ),
          );
          await tester.pumpAndSettle();
          final destinations = tester.widgetList<NavigationDestination>(
            find.byType(NavigationDestination),
          );
          expect(destinations.map((item) => item.label), [
            'Accueil',
            'Parc',
            'Présences',
            'Plus',
          ]);
          expect(find.text('Factures'), findsNothing);
          expect(find.text('Facturation'), findsNothing);

          await tester.tap(find.byIcon(Icons.fact_check_outlined).last);
          await tester.pumpAndSettle();
          expect(find.byType(PlateformePresencesScreen), findsOneWidget);
          expect(
            find.descendant(
              of: find.byType(AppBar),
              matching: find.text('Présences du parc'),
            ),
            findsOneWidget,
          );

          await tester.tap(find.text('Plus'));
          await tester.pumpAndSettle();
          expect(
            find.text('Équipe Auditron'),
            superAdmin ? findsOneWidget : findsNothing,
          );
          expect(
            find.text('Journal d’audit Auditron'),
            superAdmin ? findsOneWidget : findsNothing,
          );
          await tester.tap(find.text('Offres et abonnements'));
          await tester.pumpAndSettle();
          expect(
            find.descendant(
              of: find.byType(AppBar),
              matching: find.text('Offres et abonnements'),
            ),
            findsOneWidget,
          );
          final stack = tester.widget<IndexedStack>(
            find.byType(IndexedStack).first,
          );
          expect(stack.children[stack.index!], isA<PlateformePlansScreen>());

          if (superAdmin) {
            for (final (title, type) in [
              ('Équipe Auditron', PlateformeUtilisateursScreen),
              ('Journal d’audit Auditron', PlateformeAuditScreen),
            ]) {
              await tester.tap(find.text('Plus'));
              await tester.pumpAndSettle();
              await tester.tap(find.text(title));
              await tester.pumpAndSettle();
              final stack = tester.widget<IndexedStack>(
                find.byType(IndexedStack).first,
              );
              expect(stack.children[stack.index!].runtimeType, type);
              expect(
                find.descendant(
                  of: find.byType(AppBar),
                  matching: find.text(title),
                ),
                findsOneWidget,
              );
            }
          }
          expect(requests.any((path) => path.contains('/factures')), isFalse);
          expect(tester.takeException(), isNull);
        },
        () => MockClient((request) async {
          requests.add(request.url.path);
          return http.Response(jsonEncode({'data': []}), 200);
        }),
      );
    });
  }
}
