import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:auditron_x_app/screens/admin/admin_home_screen.dart';
import 'package:auditron_x_app/screens/home_screen.dart';
import 'package:auditron_x_app/screens/scan_screen.dart';
import 'package:auditron_x_app/screens/teacher_day_screen.dart';
import 'package:auditron_x_app/services/admin_session.dart';
import 'package:auditron_x_app/services/offline/sync_engine.dart';
import 'package:auditron_x_app/services/presence_repository.dart';
import 'package:auditron_x_app/services/session.dart';
import 'package:auditron_x_app/theme.dart';
import 'package:auditron_x_app/widgets/auditron_app_bar.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';

class _TeacherSession extends Session {
  final bool admin;
  _TeacherSession({this.admin = false});

  @override
  String get nom => 'Enseignant A.';

  @override
  Map<String, dynamic> get me => {'id': 12, 'nom': nom, 'est_admin': admin};
}

class _DirectionSession extends AdminSession {
  @override
  Map<String, dynamic> get user => {
    'name': 'Direction',
    'accreditation': {'groupe': '*', 'exclut_administration': false},
  };
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const storageChannel = MethodChannel(
    'plugins.it_nomads.com/flutter_secure_storage',
  );
  const connectivityChannel = MethodChannel(
    'dev.fluttercommunity.plus/connectivity',
  );
  final storage = <String, String>{};
  final boundaryKey = GlobalKey();
  String getToday() => DateTime.now().toIso8601String().substring(0, 10);

  setUpAll(() async {
    await initializeDateFormatting('fr');
    final fonts = FontLoader('Lato')
      ..addFont(rootBundle.load('assets/fonts/Lato-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/Lato-Bold.ttf'));
    await fonts.load();
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await icons.load();
  });

  setUp(() {
    final today = getToday();
    storage.clear();
    storage['auditron_presence_history_cache'] = jsonEncode([
      {
        'id': 1,
        'date': today,
        'heure_arrivee': '${today}T07:25:00',
        'heure_depart': null,
        'source': 'app_mobile',
        'minutes_retard': 0,
      },
    ]);
    storage['auditron_cache_teacher_courses_today'] = jsonEncode({
      'date': today,
      'present': true,
      'cours': [
        {
          'emploi_du_temps_id': 1,
          'discipline': 'Mathématiques',
          'classe': '3e A',
          'heure_debut': '08:00:00',
          'heure_fin': '09:00:00',
          'lecons': [],
        },
        {
          'emploi_du_temps_id': 2,
          'discipline': 'Mathématiques',
          'classe': '4e B',
          'heure_debut': '10:00:00',
          'heure_fin': '11:00:00',
          'lecons': [],
        },
      ],
    });
    storage['auditron_cache_teacher_assiduite_mensuelle'] = jsonEncode({
      'mois': today,
      'taux_assiduite': 96,
      'jours_attendus': 25,
      'jours_presents': 24,
      'jours_scannes': 24,
      'emploi_du_temps_charge': true,
    });
    storage['auditron_cache_admin_dashboard_$today'] = jsonEncode({
      'date': today,
      'effectif': 72,
      'presents': 68,
      'absents': 4,
      'retardataires': 3,
      'classement_par_section': [
        {'section': 'Enseignement général', 'taux_assiduite': 96},
        {'section': 'Enseignement technique', 'taux_assiduite': 92},
        {'section': 'Administration', 'taux_assiduite': 98},
      ],
      'scannes': [],
      'absents_liste': [],
      'retardataires_liste': [],
    });
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storageChannel, (call) async {
          final args = call.arguments as Map?;
          final key = args?['key'] as String?;
          switch (call.method) {
            case 'read':
              return storage[key];
            case 'write':
              storage[key!] = args!['value'] as String;
              return null;
            case 'delete':
              storage.remove(key);
              return null;
            case 'readAll':
              return storage;
          }
          return null;
        });
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(connectivityChannel, (_) async => ['none']);
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storageChannel, null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(connectivityChannel, null);
  });

  Future<void> pumpScreen(
    WidgetTester tester,
    Widget screen, {
    double width = 390,
    double height = 844,
    double scale = 1,
    bool teacherAdmin = false,
  }) async {
    tester.view.physicalSize = Size(width, height);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<Session>(
            create: (_) => _TeacherSession(admin: teacherAdmin),
          ),
          ChangeNotifierProvider<AdminSession>(
            create: (_) => _DirectionSession(),
          ),
          ChangeNotifierProvider.value(value: SyncEngine.instance),
        ],
        child: RepaintBoundary(
          key: boundaryKey,
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: buildAuditronTheme(),
            locale: const Locale('fr'),
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            supportedLocales: const [Locale('fr')],
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: screen,
          ),
        ),
      ),
    );
    await tester.runAsync(() async {
      await precacheImage(
        const AssetImage('assets/logo.png'),
        boundaryKey.currentContext!,
      );
    });
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 100));
    }
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

  Widget day({
    required Future<void> Function() scan,
    VoidCallback? courses,
    VoidCallback? history,
  }) => Builder(
    builder: (context) => Scaffold(
      appBar: buildAuditronAppBar(
        context,
        title: 'Ma journée',
        subtitle: 'Enseignant A.',
      ),
      body: TeacherDayScreen(
        onScan: scan,
        onHistory: history ?? () {},
        onCourses: courses ?? () {},
        onSchedule: () {},
      ),
    ),
  );

  testWidgets(
    'reprend la journée de la vitrine avec la présence et les cours réels',
    (tester) async {
      await pumpScreen(tester, const HomeScreen());
      expect(find.text('Auditron-X'), findsOneWidget);
      expect(find.text('Ma journée'), findsOneWidget);
      expect(find.text('Arrivée enregistrée'), findsOneWidget);
      expect(find.text('07:25'), findsOneWidget);
      expect(find.text('08:00 - 09:00'), findsOneWidget);
      expect(find.text('Mathématiques'), findsNWidgets(2));
      final button = tester.widget<FilledButton>(
        find.widgetWithText(FilledButton, 'Scanner pour pointer'),
      );
      expect(
        button.style!.backgroundColor!.resolve({}),
        AuditronColors.orange500,
      );
      expect(tester.takeException(), isNull);
      await capture(tester, 'teacher-day-390');
    },
  );

  testWidgets('le scan actualise la présence au retour sans réseau', (
    tester,
  ) async {
    storage['auditron_presence_history_cache'] = '[]';
    var scans = 0;
    await pumpScreen(
      tester,
      day(
        scan: () async {
          scans++;
          await PresenceRepository().markSuccessfulScan(wasDeparture: false);
        },
      ),
    );
    expect(find.text('Pas encore de pointage'), findsOneWidget);
    await tester.tap(find.text('Scanner pour pointer'));
    await tester.pumpAndSettle();
    expect(scans, 1);
    expect(find.text('Arrivée enregistrée'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('distingue le départ enregistré de l’arrivée', (tester) async {
    final today = getToday();
    storage['auditron_presence_history_cache'] = jsonEncode([
      {
        'id': 1,
        'date': today,
        'heure_arrivee': '${today}T07:25:00',
        'heure_depart': '${today}T15:30:00',
        'source': 'app_mobile',
        'minutes_retard': 0,
      },
    ]);
    await pumpScreen(tester, const HomeScreen());
    expect(find.text('Départ enregistré'), findsOneWidget);
    expect(find.text('15:30'), findsOneWidget);
    expect(find.text('Arrivée à 07:25'), findsOneWidget);
  });

  testWidgets('ne présente pas les cours d’hier comme ceux du jour', (
    tester,
  ) async {
    storage['auditron_cache_teacher_courses_today'] = jsonEncode({
      'date': '2000-01-01',
      'cours': [
        {'discipline': 'Cours périmé'},
      ],
    });
    await pumpScreen(tester, const HomeScreen());
    expect(find.text('Cours du jour indisponibles.'), findsOneWidget);
    expect(find.text('Cours périmé'), findsNothing);
    expect(find.text('Scanner pour pointer'), findsOneWidget);
  });

  testWidgets('le scan reste accessible sans aucune donnée en cache', (
    tester,
  ) async {
    storage.clear();
    await pumpScreen(tester, const HomeScreen());
    expect(find.text('Présence indisponible'), findsOneWidget);
    expect(find.text('Cours du jour indisponibles.'), findsOneWidget);
    expect(find.text('Scanner pour pointer'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'les cours et l’historique restent accessibles depuis la journée',
    (tester) async {
      await pumpScreen(tester, const HomeScreen());
      await tester.tap(find.text('Mathématiques').first);
      await tester.pumpAndSettle();
      expect(find.text('Mes cours du jour'), findsOneWidget);
      expect(find.text('Cahier de texte'), findsNWidgets(2));
      await tester.tap(find.byIcon(Icons.home_outlined));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Historique de mes présences'));
      await tester.pumpAndSettle();
      expect(find.text('Mes présences'), findsOneWidget);
      expect(find.text('Arrivée 07:25'), findsOneWidget);
      expect(find.text('À l’heure'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await capture(tester, 'history-390');
    },
  );

  testWidgets(
    'le tableau de bord conserve ses valeurs et ses barres par section',
    (tester) async {
      await pumpScreen(tester, const AdminHomeScreen());
      expect(find.text('Espace direction'), findsOneWidget);
      for (final value in ['72', '68', '4', '3']) {
        expect(find.text(value), findsOneWidget);
      }
      expect(find.text('Assiduité par section'), findsOneWidget);
      expect(find.text('Enseignement technique'), findsOneWidget);
      expect(find.text('92 %'), findsOneWidget);
      expect(find.byType(LinearProgressIndicator), findsNWidgets(3));
      expect(tester.takeException(), isNull);
      await capture(tester, 'direction-390');
    },
  );

  testWidgets('les raccourcis direction ouvrent les bonnes rubriques', (
    tester,
  ) async {
    await pumpScreen(tester, const AdminHomeScreen(), height: 1000);
    await tester.ensureVisible(find.text('Signalements et justificatifs'));
    await tester.tap(find.text('Signalements et justificatifs'));
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.text('Signalements'),
      ),
      findsOneWidget,
    );
    await tester.tap(find.byIcon(Icons.dashboard_outlined));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Bilans d’assiduité et de retards'));
    await tester.tap(find.text('Bilans d’assiduité et de retards'));
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.text('Assiduité'),
      ),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('la procuration reste réservée aux enseignants administrateurs', (
    tester,
  ) async {
    await pumpScreen(tester, const HomeScreen());
    expect(
      tester
          .widgetList<NavigationDestination>(find.byType(NavigationDestination))
          .any((destination) => destination.label == 'Procuration'),
      isFalse,
    );
    await pumpScreen(tester, const HomeScreen(), teacherAdmin: true);
    expect(
      tester
          .widgetList<NavigationDestination>(find.byType(NavigationDestination))
          .any((destination) => destination.label == 'Procuration'),
      isTrue,
    );
  });

  testWidgets('la journée sans cours reste disponible', (tester) async {
    storage['auditron_cache_teacher_courses_today'] = jsonEncode({
      'date': getToday(),
      'present': true,
      'cours': [],
    });
    await pumpScreen(tester, const HomeScreen());
    expect(find.text('Aucun cours prévu aujourd’hui.'), findsOneWidget);
    expect(find.text('Scanner pour pointer'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('les retards restent lisibles dans l’historique en grand texte', (
    tester,
  ) async {
    final today = getToday();
    storage['auditron_presence_history_cache'] = jsonEncode([
      {
        'id': 1,
        'date': today,
        'heure_arrivee': '${today}T07:45:00',
        'heure_depart': '${today}T15:30:00',
        'source': 'app_mobile',
        'minutes_retard': 15,
      },
    ]);
    await pumpScreen(
      tester,
      const HomeScreen(),
      width: 320,
      scale: 2,
      teacherAdmin: true,
    );
    await tester.tap(
      find.descendant(
        of: find.byType(NavigationBar),
        matching: find.byIcon(Icons.history),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Retard : 15 min'), findsOneWidget);
    expect(find.text('Départ 15:30'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await capture(tester, 'history-320-2.0');
  });

  testWidgets('les deux espaces restent défilants en paysage', (tester) async {
    await pumpScreen(tester, const HomeScreen(), width: 844, height: 390);
    expect(tester.takeException(), isNull);
    await tester.drag(find.byType(ListView).first, const Offset(0, -300));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await pumpScreen(tester, const AdminHomeScreen(), width: 844, height: 390);
    await tester.drag(find.byType(ListView).first, const Offset(0, -300));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });

  for (final size in [const Size(390, 844), const Size(844, 390)]) {
    testWidgets('le bandeau Bluetooth ne recouvre pas la caméra à $size', (
      tester,
    ) async {
      const cameraKey = ValueKey('test-camera');
      await pumpScreen(
        tester,
        Builder(
          builder: (context) => Scaffold(
            appBar: buildAuditronAppBar(
              context,
              title: 'Pointer ma présence',
              subtitle: 'Point de contrôle · Borne locale',
            ),
            body: const ScanCameraView(
              camera: ColoredBox(key: cameraKey, color: Color(0xFF334155)),
            ),
          ),
        ),
        width: size.width,
        height: size.height,
      );
      final cameraRect = tester.getRect(find.byKey(cameraKey));
      final statusRect = tester.getRect(
        find.text('Transmission locale par Bluetooth'),
      );
      expect(cameraRect.bottom, lessThanOrEqualTo(statusRect.top));
      expect(tester.takeException(), isNull);
      await capture(tester, 'scan-${size.width.toInt()}');
    });
  }

  testWidgets(
    'la transmission affiche un état distinct sans relancer le scan',
    (tester) async {
      await pumpScreen(
        tester,
        const Scaffold(
          body: ScanCameraView(
            camera: ColoredBox(color: Colors.black),
            processing: true,
          ),
        ),
      );
      expect(find.text('Transmission en cours'), findsOneWidget);
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  for (final width in [320.0, 600.0]) {
    for (final scale in [1.0, 1.6, 2.0]) {
      testWidgets(
        'enseignant et direction sans débordement à ${width}px, texte x$scale',
        (tester) async {
          await pumpScreen(
            tester,
            const HomeScreen(),
            width: width,
            scale: scale,
            teacherAdmin: true,
          );
          expect(find.text('Scanner pour pointer'), findsOneWidget);
          expect(tester.takeException(), isNull);
          await capture(tester, 'teacher-${width.toInt()}-$scale');
          await pumpScreen(
            tester,
            const AdminHomeScreen(),
            width: width,
            scale: scale,
          );
          expect(find.text('72'), findsOneWidget);
          expect(tester.takeException(), isNull);
          await capture(tester, 'direction-${width.toInt()}-$scale');
        },
      );
    }
  }
}
