import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:auditron_x_app/screens/activation_screen.dart';
import 'package:auditron_x_app/services/api_client.dart';
import 'package:auditron_x_app/services/session.dart';
import 'package:auditron_x_app/theme.dart';
import 'package:auditron_x_app/widgets/auth/auth_pattern_background.dart';
import 'support/auditron_test_fonts.dart';

class _Session extends Session {
  String? telephone;
  String? password;
  @override
  Future<String?> get lastTel async => null;
  @override
  Future<void> requestActivation(String tel, String secret) async {
    telephone = tel;
    password = secret;
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const storageChannel = MethodChannel(
    'plugins.it_nomads.com/flutter_secure_storage',
  );
  const networkChannel = MethodChannel(
    'dev.fluttercommunity.plus/connectivity',
  );
  final storage = <String, String>{};
  final boundaryKey = GlobalKey();
  late _Session session;

  setUpAll(() async {
    await const SvgAssetLoader(AuthPatternBackground.assetPath).loadBytes(null);
    await loadAuditronTestFonts();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });
  setUp(() async {
    session = _Session();
    final messenger =
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(storageChannel, (call) async {
      final args = Map<String, dynamic>.from(call.arguments as Map);
      final key = args['key'] as String?;
      switch (call.method) {
        case 'read':
          return storage[key];
        case 'write':
          storage[key!] = args['value'] as String;
          return null;
        case 'delete':
          storage.remove(key);
          return null;
        case 'readAll':
          return storage;
      }
      return null;
    });
    messenger.setMockMethodCallHandler(networkChannel, (_) async => ['wifi']);
    await ApiClient.instance.oublierEtablissement();
    storage.clear();
  });
  tearDown(() async {
    await ApiClient.instance.oublierEtablissement();
    final messenger =
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(storageChannel, null);
    messenger.setMockMethodCallHandler(networkChannel, null);
    session.dispose();
  });

  Future<void> pump(
    WidgetTester tester, {
    double scale = 1,
    double width = 390,
  }) async {
    tester.view.physicalSize = Size(width, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      ChangeNotifierProvider<Session>.value(
        value: session,
        child: RepaintBoundary(
          key: boundaryKey,
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: buildAuditronTheme(),
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: const ActivationScreen(),
          ),
        ),
      ),
    );
    await tester.runAsync(
      () => precacheImage(
        const AssetImage('assets/logo.png'),
        boundaryKey.currentContext!,
      ),
    );
    await tester.pumpAndSettle();
  }

  Future<void> capture(WidgetTester tester, String name) async {
    if (!const bool.fromEnvironment('AUDITRON_UI_PREVIEWS')) return;
    await tester.runAsync(() async {
      final boundary =
          boundaryKey.currentContext!.findRenderObject()
              as RenderRepaintBoundary;
      final image = await boundary.toImage();
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      final directory = Directory('build/ui-previews');
      await directory.create(recursive: true);
      await File(
        '${directory.path}/$name.png',
      ).writeAsBytes(bytes!.buffer.asUint8List());
      image.dispose();
    });
  }

  Future<void> choose(WidgetTester tester) async {
    await tester.enterText(find.byType(TextField), 'ltm');
    await tester.ensureVisible(find.text('Continuer'));
    await tester.tap(find.text('Continuer'));
    await tester.pumpAndSettle();
  }

  testWidgets('etablissement puis telephone puis mot de passe', (tester) async {
    await http.runWithClient(
      () async {
        await pump(tester);
        expect(find.text('Votre établissement'), findsOneWidget);
        expect(find.text('Mot de passe'), findsNothing);
        await capture(tester, 'connexion-etablissement');
        await choose(tester);
        expect(find.text('Votre téléphone'), findsOneWidget);
        expect(find.text('Lycée technique · LTM'), findsOneWidget);
        expect(storage['auditron_etablissement'], 'LTM');
        await capture(tester, 'connexion-enseignant-saas');
        await tester.enterText(
          find.byKey(const ValueKey('identifier')),
          '699123456',
        );
        await tester.tap(find.text('Continuer'));
        await tester.pumpAndSettle();
        await tester.enterText(
          find.byKey(const ValueKey('password')),
          'secret',
        );
        await tester.tap(find.text('Se connecter'));
        await tester.pumpAndSettle();
        expect(session.telephone, '699123456');
        expect(session.password, 'secret');
        await tester.binding.handlePopRoute();
        await tester.pumpAndSettle();
        expect(find.text('Votre téléphone'), findsOneWidget);
        await tester.binding.handlePopRoute();
        await tester.pumpAndSettle();
        expect(find.text('Votre établissement'), findsOneWidget);
        expect(storage['auditron_etablissement'], isNull);
        expect(tester.takeException(), isNull);
      },
      () => MockClient((request) async {
        expect(request.headers['X-Tenant'], 'LTM');
        expect(request.url.path, endsWith('/etablissement'));
        return http.Response(
          jsonEncode({
            'data': {'nom': 'Lycée technique', 'code': 'LTM'},
          }),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        );
      }),
    );
  });

  testWidgets('code vide et inconnu restent sur le premier ecran', (
    tester,
  ) async {
    var requests = 0;
    await http.runWithClient(
      () async {
        await pump(tester);
        await tester.tap(find.text('Continuer'));
        await tester.pumpAndSettle();
        expect(
          find.text('Saisissez le code de votre établissement.'),
          findsOneWidget,
        );
        expect(requests, 0);
        await choose(tester);
        expect(find.text('Code inconnu.'), findsOneWidget);
        expect(find.text('Votre établissement'), findsOneWidget);
        expect(storage['auditron_etablissement'], isNull);
      },
      () => MockClient((request) async {
        requests++;
        return http.Response(jsonEncode({'message': 'Code inconnu.'}), 404);
      }),
    );
  });

  testWidgets('reponse sans branding ne memorise pas un code non verifie', (
    tester,
  ) async {
    await http.runWithClient(() async {
      await pump(tester);
      await choose(tester);
      expect(find.text('Code d’établissement invalide.'), findsOneWidget);
      expect(storage['auditron_etablissement'], isNull);
    }, () => MockClient((request) async => http.Response('{}', 200)));
  });

  testWidgets('verification en cours bloque les autres connexions', (
    tester,
  ) async {
    final response = Completer<http.Response>();
    await http.runWithClient(() async {
      await pump(tester);
      await tester.enterText(find.byType(TextField), 'ltm');
      await tester.tap(find.text('Continuer'));
      await tester.pump();
      final central = tester.widget<TextButton>(
        find.widgetWithText(TextButton, 'Connexion équipe Auditron'),
      );
      expect(central.onPressed, isNull);
      response.complete(http.Response('{"data":{"code":"LTM"}}', 200));
      await tester.pumpAndSettle();
      expect(find.text('Votre téléphone'), findsOneWidget);
      expect(tester.takeException(), isNull);
    }, () => MockClient((request) => response.future));
  });

  testWidgets('portail central disponible avant le choix etablissement', (
    tester,
  ) async {
    await pump(tester, width: 320, scale: 2);
    await tester.ensureVisible(find.text('Connexion équipe Auditron'));
    await tester.tap(find.text('Connexion équipe Auditron'));
    await tester.pumpAndSettle();
    expect(find.text('Votre adresse email'), findsOneWidget);
    expect(find.text('Équipe Auditron'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
