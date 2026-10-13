import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:auditron_x_app/services/api_client.dart';
import 'package:auditron_x_app/theme.dart';
import 'package:auditron_x_app/widgets/auth/credential_login_screen.dart';
import 'package:auditron_x_app/widgets/auth/auth_pattern_background.dart';
import 'support/auditron_test_fonts.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  final boundaryKey = GlobalKey();

  setUpAll(() async {
    await const SvgAssetLoader(AuthPatternBackground.assetPath).loadBytes(null);
    await loadAuditronTestFonts();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
  });

  Future<void> pumpLogin(
    WidgetTester tester, {
    bool phone = true,
    double width = 390,
    double height = 844,
    double scale = 1,
    double keyboard = 0,
    Future<String?>? saved,
    Future<void> Function(String, String)? login,
    VoidCallback? success,
  }) async {
    tester.view.physicalSize = Size(width, height);
    tester.view.devicePixelRatio = 1;
    tester.view.viewInsets = FakeViewPadding(bottom: keyboard);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetViewInsets);
    await tester.pumpWidget(
      RepaintBoundary(
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
          home: CredentialLoginScreen(
            scope: phone ? 'Espace enseignant' : 'Espace administration',
            phone: phone,
            savedIdentifier: saved,
            login: login ?? (_, _) async {},
            onSuccess: success,
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

  Future<void> tap(WidgetTester tester, String text) async {
    await tester.ensureVisible(find.text(text));
    await tester.tap(find.text(text));
    await tester.pumpAndSettle();
  }

  Future<void> passwordStep(
    WidgetTester tester, {
    String identifier = '699123456',
  }) async {
    await tester.enterText(
      find.byKey(const ValueKey('identifier')),
      identifier,
    );
    await tap(tester, 'Continuer');
    expect(find.byType(TextFormField), findsOneWidget);
    expect(find.text('Votre mot de passe'), findsOneWidget);
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

  testWidgets('polices de la vitrine et un seul champ par etape', (
    tester,
  ) async {
    await pumpLogin(tester);
    expect(
      Theme.of(
        tester.element(find.byType(TextFormField)),
      ).textTheme.bodyMedium!.fontFamily,
      AuditronFonts.body,
    );
    expect(find.byType(TextFormField), findsOneWidget);
    expect(find.text('Mot de passe'), findsNothing);
    await capture(tester, 'connexion-telephone');
    await passwordStep(tester);
    await capture(tester, 'connexion-mot-de-passe');
  });

  testWidgets('valide le telephone et le mot de passe avant connexion', (
    tester,
  ) async {
    var calls = 0;
    await pumpLogin(
      tester,
      login: (_, _) async {
        calls++;
      },
    );
    await tap(tester, 'Continuer');
    expect(find.text('Saisissez votre numéro.'), findsOneWidget);
    await passwordStep(tester);
    await tap(tester, 'Se connecter');
    expect(find.text('Saisissez votre mot de passe.'), findsOneWidget);
    expect(calls, 0);
  });

  testWidgets('retour conserve le telephone et efface le mot de passe', (
    tester,
  ) async {
    await pumpLogin(tester);
    await passwordStep(tester);
    await tester.enterText(find.byKey(const ValueKey('password')), 'secret');
    await tester.tap(find.byTooltip('Afficher le mot de passe'));
    await tester.pumpAndSettle();
    expect(
      tester.widget<EditableText>(find.byType(EditableText)).obscureText,
      isFalse,
    );
    await tester.tap(find.byTooltip('Retour'));
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<TextFormField>(find.byKey(const ValueKey('identifier')))
          .controller!
          .text,
      '699123456',
    );
    await tap(tester, 'Continuer');
    final password = tester.widget<TextFormField>(
      find.byKey(const ValueKey('password')),
    );
    expect(password.controller!.text, isEmpty);
    expect(
      tester.widget<EditableText>(find.byType(EditableText)).obscureText,
      isTrue,
    );
  });

  testWidgets('retour systeme revient au numero', (tester) async {
    await pumpLogin(tester);
    await passwordStep(tester);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('Votre téléphone'), findsOneWidget);
  });

  testWidgets('email puis mot de passe pour administration', (tester) async {
    await pumpLogin(tester, phone: false);
    await capture(tester, 'connexion-administration');
    await tester.enterText(
      find.byKey(const ValueKey('identifier')),
      'invalide',
    );
    await tap(tester, 'Continuer');
    expect(find.text('Vérifiez votre adresse email.'), findsOneWidget);
    await passwordStep(tester, identifier: 'direction@example.test');
  });

  testWidgets('connexion transmet les identifiants sans double soumission', (
    tester,
  ) async {
    final completion = Completer<void>();
    var calls = 0;
    var success = false;
    await pumpLogin(
      tester,
      login: (identifier, password) {
        calls++;
        expect(identifier, '699123456');
        expect(password, 'secret');
        return completion.future;
      },
      success: () => success = true,
    );
    await passwordStep(tester);
    await tester.enterText(find.byKey(const ValueKey('password')), 'secret');
    await tester.tap(find.text('Se connecter'));
    await tester.pump();
    tester
        .widget<EditableText>(find.byType(EditableText))
        .onSubmitted
        ?.call('secret');
    await tester.pump();
    expect(calls, 1);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNull,
    );
    completion.complete();
    await tester.pumpAndSettle();
    expect(success, isTrue);
    expect(tester.takeException(), isNull);
  });

  testWidgets('erreur API lisible et nouvelle tentative possible', (
    tester,
  ) async {
    await pumpLogin(
      tester,
      login: (_, _) async {
        throw ApiException('Identifiants incorrects.', 401);
      },
    );
    await passwordStep(tester);
    await tester.enterText(find.byKey(const ValueKey('password')), 'secret');
    await tap(tester, 'Se connecter');
    expect(find.text('Identifiants incorrects.'), findsOneWidget);
    expect(
      tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
      isNotNull,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('fin de requete apres fermeture sans setState apres dispose', (
    tester,
  ) async {
    final completion = Completer<void>();
    await pumpLogin(tester, login: (_, _) => completion.future);
    await passwordStep(tester);
    await tester.enterText(find.byKey(const ValueKey('password')), 'secret');
    await tester.tap(find.text('Se connecter'));
    await tester.pump();
    await tester.pumpWidget(const SizedBox.shrink());
    completion.completeError(ApiException('Erreur tardive', 401));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  testWidgets('identifiant memorise tardif ne remplace pas la saisie', (
    tester,
  ) async {
    final saved = Completer<String?>();
    await pumpLogin(tester, saved: saved.future);
    await tester.enterText(
      find.byKey(const ValueKey('identifier')),
      '699123456',
    );
    saved.complete('600000000');
    await tester.pumpAndSettle();
    expect(
      tester
          .widget<TextFormField>(find.byKey(const ValueKey('identifier')))
          .controller!
          .text,
      '699123456',
    );
  });

  for (final viewport in [
    const Size(320, 640),
    const Size(600, 360),
    const Size(1280, 800),
  ]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets('connexion sans debordement $viewport texte $scale', (
        tester,
      ) async {
        await pumpLogin(
          tester,
          width: viewport.width,
          height: viewport.height,
          scale: scale,
        );
        expect(tester.takeException(), isNull);
        await passwordStep(tester);
        expect(tester.takeException(), isNull);
        await capture(tester, 'connexion-${viewport.width.toInt()}-$scale');
      });
    }
  }

  testWidgets('champ et bouton accessibles avec le clavier ouvert', (
    tester,
  ) async {
    await pumpLogin(tester, width: 320, height: 640, keyboard: 300, scale: 2);
    await passwordStep(tester);
    await tester.enterText(find.byKey(const ValueKey('password')), 'secret');
    await tap(tester, 'Se connecter');
    expect(tester.takeException(), isNull);
    await capture(tester, 'connexion-clavier-320');
  });
}
