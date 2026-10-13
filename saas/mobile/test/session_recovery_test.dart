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
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';
import 'package:auditron_x_app/main.dart';
import 'package:auditron_x_app/screens/session_recovery_screen.dart';
import 'package:auditron_x_app/screens/home_screen.dart';
import 'package:auditron_x_app/services/api_client.dart';
import 'package:auditron_x_app/services/session.dart';
import 'package:auditron_x_app/theme.dart';
import 'package:auditron_x_app/widgets/auth/auth_pattern_background.dart';
import 'support/auditron_test_fonts.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const storageChannel = MethodChannel(
    'plugins.it_nomads.com/flutter_secure_storage',
  );
  const networkChannel = MethodChannel(
    'dev.fluttercommunity.plus/connectivity',
  );
  final storage = <String, String>{};
  late Session session;
  var online = true;
  Future<void> Function(String?)? beforeDelete;

  setUpAll(() async {
    await initializeDateFormatting('fr');
    await loadAuditronTestFonts();
    await (FontLoader(
      'MaterialIcons',
    )..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'))).load();
    await const SvgAssetLoader(AuthPatternBackground.assetPath).loadBytes(null);
  });
  setUp(() async {
    session = Session();
    online = true;
    beforeDelete = null;
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
        case 'delete':
          await beforeDelete?.call(key);
          storage.remove(key);
        case 'readAll':
          return storage;
      }
      return null;
    });
    messenger.setMockMethodCallHandler(
      networkChannel,
      (_) async => [online ? 'wifi' : 'none'],
    );
    ApiClient.instance.onSessionExpired = null;
    await ApiClient.instance.oublierEtablissement();
    storage.clear();
  });
  tearDown(() async {
    beforeDelete = null;
    session.dispose();
    ApiClient.instance.onSessionExpired = null;
    await ApiClient.instance.oublierEtablissement();
    final messenger =
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
    messenger.setMockMethodCallHandler(storageChannel, null);
    messenger.setMockMethodCallHandler(networkChannel, null);
  });

  Future<void> seed({bool token = true, bool binding = true}) async {
    await ApiClient.instance.definirEtablissement('LTM');
    storage['auditron_device_uuid_local'] = 'same-phone';
    storage['auditron_last_tel'] = '699123456';
    storage['auditron_me_cache'] = jsonEncode({
      'id': 7,
      'nom': 'Enseignant Test',
      'tel': '699123456',
    });
    if (binding) {
      storage['auditron_teacher_binding'] = jsonEncode({
        'tel': '699123456',
        'etablissement': 'LTM',
      });
    }
    if (token) {
      await ApiClient.instance.saveSession(
        token: 'old-token',
        deviceUuid: 'same-phone',
      );
    }
  }

  http.Response profile() => http.Response(storage['auditron_me_cache']!, 200);
  Future<void> pumpUntil(WidgetTester tester, bool Function() ready) async {
    for (var i = 0; i < 30 && !ready(); i++) {
      await tester.pump(const Duration(milliseconds: 20));
      await tester.runAsync(() => Future<void>.delayed(Duration.zero));
    }
    expect(ready(), isTrue);
  }

  final rejects = MockClient(
    (_) async => http.Response('{"message":"Refus"}', 401),
  );

  test('real 401 preserves teacher identity and requires recovery', () async {
    await seed();
    await http.runWithClient(session.bootstrap, () => rejects);
    expect(session.activated, isFalse);
    expect(session.needsRecovery, isTrue);
    expect(session.boundTelephone, '699123456');
    expect(session.nom, 'Enseignant Test');
    expect(await ApiClient.instance.token, isNull);
    expect(storage['auditron_device_uuid_local'], 'same-phone');
    expect(storage['auditron_me_cache'], isNotNull);
    expect(storage['auditron_teacher_binding'], isNotNull);
  });

  test(
    'offline startup keeps activation and never requires recovery',
    () async {
      await seed();
      online = false;
      await session.bootstrap();
      expect(session.activated, isTrue);
      expect(session.needsRecovery, isFalse);
      expect(await ApiClient.instance.token, 'old-token');
    },
  );

  test('missing token after restart recovers same bound account', () async {
    await seed(token: false);
    await session.bootstrap();
    expect(session.needsRecovery, isTrue);
    expect(session.boundTelephone, '699123456');
    expect(session.boundEtablissement, 'LTM');
  });

  test('existing installations migrate teacher binding', () async {
    await seed(token: false, binding: false);
    storage.remove('auditron_me_cache');
    await session.bootstrap();
    expect(session.needsRecovery, isTrue);
    expect(
      jsonDecode(storage['auditron_teacher_binding']!)['tel'],
      '699123456',
    );
  });

  test('failed first activation does not bind an unverified account', () async {
    await ApiClient.instance.definirEtablissement('LTM');
    await session.bootstrap();
    await http.runWithClient(() async {
      await expectLater(
        session.requestActivation('699000000', 'wrong'),
        throwsA(isA<ApiException>()),
      );
    }, () => rejects);
    expect(storage['auditron_last_tel'], isNull);
    expect(storage['auditron_teacher_binding'], isNull);
    expect(session.needsRecovery, isFalse);
  });

  test('late 401 from old token cannot erase new session', () async {
    await seed();
    var expired = 0;
    ApiClient.instance.onSessionExpired = () => expired++;
    final sent = Completer<void>();
    final response = Completer<http.Response>();
    await http.runWithClient(
      () async {
        final pending = ApiClient.instance.get('/me');
        final checked = expectLater(pending, throwsA(isA<ApiException>()));
        await sent.future;
        await ApiClient.instance.saveSession(
          token: 'new-token',
          deviceUuid: 'same-phone',
        );
        response.complete(http.Response('{}', 401));
        await checked;
      },
      () => MockClient((request) {
        expect(request.headers['Authorization'], 'Bearer old-token');
        sent.complete();
        return response.future;
      }),
    );
    expect(expired, 0);
    expect(await ApiClient.instance.token, 'new-token');
    expect(storage['auditron_token'], 'new-token');
  });

  test('unauthenticated 401 cannot erase a concurrent login', () async {
    var expired = 0;
    ApiClient.instance.onSessionExpired = () => expired++;
    final sent = Completer<void>();
    final response = Completer<http.Response>();
    await http.runWithClient(
      () async {
        final checked = expectLater(
          ApiClient.instance.get('/me'),
          throwsA(isA<ApiException>()),
        );
        await sent.future;
        await ApiClient.instance.saveSession(
          token: 'new-token',
          deviceUuid: 'same-phone',
        );
        response.complete(http.Response('{}', 401));
        await checked;
      },
      () => MockClient((request) {
        expect(request.headers['Authorization'], isNull);
        sent.complete();
        return response.future;
      }),
    );
    expect(expired, 0);
    expect(storage['auditron_token'], 'new-token');
  });

  test(
    'login during token deletion wins in memory and secure storage',
    () async {
      await seed();
      var expired = 0;
      ApiClient.instance.onSessionExpired = () => expired++;
      final deleting = Completer<void>();
      final resume = Completer<void>();
      beforeDelete = (key) async {
        if (key == 'auditron_token') {
          deleting.complete();
          await resume.future;
        }
      };
      await http.runWithClient(() async {
        final checked = expectLater(
          ApiClient.instance.get('/me'),
          throwsA(isA<ApiException>()),
        );
        await deleting.future;
        final saving = ApiClient.instance.saveSession(
          token: 'new-token',
          deviceUuid: 'same-phone',
        );
        resume.complete();
        await checked;
        await saving;
      }, () => rejects);
      expect(expired, 0);
      expect(await ApiClient.instance.token, 'new-token');
      expect(storage['auditron_token'], 'new-token');
    },
  );

  test('recovery uses original phone, establishment and device UUID', () async {
    await seed(token: false);
    await session.bootstrap();
    await ApiClient.instance.oublierEtablissement();
    await http.runWithClient(
      () => session.restoreSession('secret'),
      () => MockClient((request) async {
        expect(request.headers['X-Tenant'], 'LTM');
        if (request.url.path.endsWith('/devices/request-activation')) {
          expect(jsonDecode(request.body), {
            'tel': '699123456',
            'password': 'secret',
            'device_uuid': 'same-phone',
            'device_type': 'mobile',
          });
          return http.Response('{"token":"new-token"}', 200);
        }
        return profile();
      }),
    );
    expect(session.activated, isTrue);
    expect(session.needsRecovery, isFalse);
    expect(storage['auditron_device_uuid_local'], 'same-phone');
    expect(await ApiClient.instance.token, 'new-token');
    expect(storage.values, isNot(contains('secret')));
  });

  test('bound phone cannot activate a colleague account', () async {
    await seed(token: false);
    await session.bootstrap();
    await expectLater(
      session.requestActivation('699000000', 'secret'),
      throwsA(isA<ApiException>()),
    );
    expect(session.boundTelephone, '699123456');
  });

  test('missing device identity is not replaced during recovery', () async {
    await seed(token: false);
    await session.bootstrap();
    storage.remove('auditron_device_uuid_local');
    await expectLater(
      session.restoreSession('secret'),
      throwsA(isA<ApiException>()),
    );
    expect(storage['auditron_device_uuid_local'], isNull);
    expect(session.needsRecovery, isTrue);
  });

  test(
    'wrong recovery password preserves binding and remains retryable',
    () async {
      await seed(token: false);
      await session.bootstrap();
      await http.runWithClient(() async {
        await expectLater(
          session.restoreSession('wrong'),
          throwsA(isA<ApiException>()),
        );
      }, () => rejects);
      expect(session.needsRecovery, isTrue);
      expect(storage['auditron_last_tel'], '699123456');
      expect(storage['auditron_device_uuid_local'], 'same-phone');
    },
  );

  testWidgets('recovery only offers password and guards duplicate submits', (
    tester,
  ) async {
    await tester.runAsync(() async {
      await seed(token: false);
      await session.bootstrap();
    });
    final response = Completer<http.Response>();
    var activations = 0;
    await http.runWithClient(
      () async {
        await tester.pumpWidget(
          ChangeNotifierProvider<Session>.value(
            value: session,
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: buildAuditronTheme(),
              home: const SessionRecoveryScreen(),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.byType(TextFormField), findsOneWidget);
        expect(find.text('Se déconnecter'), findsNothing);
        expect(find.byTooltip('Retour'), findsNothing);
        await tester.tap(find.text('Rétablir'));
        await tester.pumpAndSettle();
        expect(find.text('Saisissez votre mot de passe.'), findsOneWidget);
        expect(activations, 0);
        await tester.enterText(find.byType(TextFormField), 'secret');
        await tester.tap(find.text('Rétablir'));
        await pumpUntil(tester, () => activations == 1);
        await tester.pump();
        await tester.tap(find.byType(FilledButton));
        await tester.pump();
        expect(activations, 1);
        await tester.runAsync(() async {
          response.complete(
            http.Response('{"message":"Mot de passe incorrect."}', 401),
          );
          await Future<void>.delayed(Duration.zero);
        });
        await tester.pumpAndSettle();
        expect(find.text('Mot de passe incorrect.'), findsOneWidget);
        expect(
          tester.widget<FilledButton>(find.byType(FilledButton)).onPressed,
          isNotNull,
        );
        expect(tester.takeException(), isNull);
      },
      () => MockClient((request) {
        activations++;
        return response.future;
      }),
    );
  });

  testWidgets('recovery fits small screens, large text and keyboard', (
    tester,
  ) async {
    await tester.runAsync(() async {
      await seed(token: false);
      await session.bootstrap();
    });
    final boundaryKey = GlobalKey();
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetDevicePixelRatio);
    addTearDown(tester.view.resetPhysicalSize);
    for (final width in [320.0, 390.0, 600.0]) {
      tester.view.physicalSize = Size(width, 844);
      await tester.pumpWidget(
        ChangeNotifierProvider<Session>.value(
          value: session,
          child: RepaintBoundary(
            key: boundaryKey,
            child: MaterialApp(
              debugShowCheckedModeBanner: false,
              theme: buildAuditronTheme(),
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(context).copyWith(
                  textScaler: const TextScaler.linear(2),
                  viewInsets: const EdgeInsets.only(bottom: 300),
                ),
                child: child!,
              ),
              home: const SessionRecoveryScreen(),
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
      await tester.ensureVisible(find.text('Rétablir'));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      if (const bool.fromEnvironment('AUDITRON_UI_PREVIEWS') && width == 320) {
        await tester.runAsync(() async {
          final boundary =
              boundaryKey.currentContext!.findRenderObject()
                  as RenderRepaintBoundary;
          final bitmap = await boundary.toImage();
          final bytes = await bitmap.toByteData(format: ui.ImageByteFormat.png);
          await Directory('build/ui-previews').create(recursive: true);
          await File(
            'build/ui-previews/session-recovery-320.png',
          ).writeAsBytes(bytes!.buffer.asUint8List());
          bitmap.dispose();
        });
      }
    }
  });

  testWidgets('root opens recovery and closes routes above the teacher home', (
    tester,
  ) async {
    await tester.runAsync(seed);
    await http.runWithClient(
      () async {
        await tester.pumpWidget(const AuditronXApp());
        final current = tester
            .element(find.byType(Scaffold).first)
            .read<Session>();
        await pumpUntil(tester, () => !current.loading);
        await tester.pumpAndSettle();
        final context = tester.element(find.byType(Scaffold).first);
        final activeSession = context.read<Session>();
        unawaited(
          Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => const Scaffold(body: Text('Scan en cours')),
            ),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Scan en cours'), findsOneWidget);
        await tester.runAsync(
          () => expectLater(
            ApiClient.instance.get('/expire'),
            throwsA(isA<ApiException>()),
          ),
        );
        await tester.pumpAndSettle();
        expect(find.text('Scan en cours'), findsNothing);
        expect(find.byType(SessionRecoveryScreen), findsOneWidget);
        expect(find.text('Rétablir ma session'), findsOneWidget);
        await tester.enterText(find.byType(TextFormField), 'secret');
        await tester.tap(find.text('Rétablir'));
        await pumpUntil(
          tester,
          () => find.byType(HomeScreen).evaluate().isNotEmpty,
        );
        await tester.pumpAndSettle();
        expect(find.byType(SessionRecoveryScreen), findsNothing);
        expect(find.byType(HomeScreen), findsOneWidget);
        expect(activeSession.activated, isTrue);
        expect(activeSession.boundTelephone, '699123456');
        expect(tester.takeException(), isNull);
        await tester.pumpWidget(const SizedBox());
      },
      () => MockClient((request) async {
        if (request.url.path.endsWith('/me')) return profile();
        if (request.url.path.endsWith('/expire')) {
          return http.Response('{}', 401);
        }
        if (request.url.path.endsWith('/devices/request-activation')) {
          final body = jsonDecode(request.body) as Map;
          expect(body['tel'], '699123456');
          expect(body['device_uuid'], 'same-phone');
          return http.Response('{"token":"new-token"}', 201);
        }
        return http.Response('{"data":[]}', 200);
      }),
    );
  });
}
