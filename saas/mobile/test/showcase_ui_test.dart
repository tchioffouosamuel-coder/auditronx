import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:auditron_x_app/screens/admin/admin_home_screen.dart';
import 'package:auditron_x_app/screens/admin/admin_journal_hebdomadaire_tab.dart';
import 'package:auditron_x_app/screens/home_screen.dart';
import 'package:auditron_x_app/screens/scan_screen.dart';
import 'package:auditron_x_app/screens/teacher_day_screen.dart';
import 'package:auditron_x_app/services/admin_session.dart';
import 'package:auditron_x_app/services/offline/sync_engine.dart';
import 'package:auditron_x_app/services/presence_repository.dart';
import 'package:auditron_x_app/services/session.dart';
import 'package:auditron_x_app/theme.dart';
import 'package:auditron_x_app/widgets/auditron_app_bar.dart';
import 'package:auditron_x_app/widgets/admin/admin_select_field.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
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
  const pathChannel = MethodChannel('plugins.flutter.io/path_provider');
  const shareChannel = MethodChannel('dev.fluttercommunity.plus/share');
  final storage = <String, String>{};
  final sharedFiles = <String>[];
  var online = false;
  late Directory pdfDirectory;
  final boundaryKey = GlobalKey();
  String getToday() => DateTime.now().toIso8601String().substring(0, 10);

  setUpAll(() async {
    pdfDirectory = await Directory.systemTemp.createTemp('auditron-pdf-tests-');
    await initializeDateFormatting('fr');
    final fonts = FontLoader('Lato')
      ..addFont(rootBundle.load('assets/fonts/Lato-Regular.ttf'))
      ..addFont(rootBundle.load('assets/fonts/Lato-Bold.ttf'));
    await fonts.load();
    final icons = FontLoader('MaterialIcons')
      ..addFont(rootBundle.load('fonts/MaterialIcons-Regular.otf'));
    await icons.load();
  });

  tearDownAll(() async {
    for (final file in await pdfDirectory.list().toList()) {
      if (file is File) await file.delete();
    }
    await pdfDirectory.delete();
  });

  setUp(() {
    final today = getToday();
    online = false;
    sharedFiles.clear();
    storage.clear();
    final now = DateTime.now();
    final firstDay = DateTime(
      now.year,
      now.month,
      1,
    ).toIso8601String().substring(0, 10);
    final lastDay = DateTime(
      now.year,
      now.month + 1,
      0,
    ).toIso8601String().substring(0, 10);
    storage['auditron_cache_admin_retards_${firstDay}_$lastDay'] = jsonEncode([
      {
        'enseignant_id': 12,
        'nom': 'DJANKO MBO UOMBOUO THIERRY',
        'matricule': '1148431X',
        'section': 'Industrielle',
        'jours_retard': 3,
        'minutes_retard_total': 124,
      },
      {
        'enseignant_id': 18,
        'nom': 'BELINGA MARIE',
        'matricule': '1148432X',
        'section': 'Administration',
        'jours_retard': 1,
        'minutes_retard_total': 18,
      },
    ]);
    storage['auditron_cache_admin_retards_parametres'] = jsonEncode({
      'tolerance_minutes': 10,
    });
    storage['auditron_cache_admin_personnel'] = jsonEncode([
      {
        'id': 12,
        'nom': 'DJANKO MBO UOMBOUO THIERRY',
        'matricule': '1148431X',
        'fonction': 'Enseignant de mathématiques',
        'section': 'Industrielle',
        'email': 'enseignant@example.test',
        'tel': '600000000',
        'est_admin': true,
      },
      {
        'id': 18,
        'nom': 'BELINGA MARIE',
        'matricule': '1148432X',
        'fonction': 'Secrétaire',
        'section': 'Administration',
        'est_admin': false,
      },
    ]);
    storage['auditron_cache_admin_personnel_12_assiduite'] = jsonEncode({
      'taux_assiduite': 96,
      'jours_presents': 24,
      'jours_attendus': 25,
    });
    storage['auditron_cache_admin_personnel_12_emplois'] = '[]';
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
        .setMockMethodCallHandler(
          connectivityChannel,
          (_) async => [online ? 'wifi' : 'none'],
        );
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(pathChannel, (_) async => pdfDirectory.path);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(shareChannel, (call) async {
          final args = call.arguments as Map;
          sharedFiles.addAll((args['paths'] as List).cast<String>());
          return 'saved';
        });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(storageChannel, null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(connectivityChannel, null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(pathChannel, null);
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(shareChannel, null);
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

  Future<void> openRetards(
    WidgetTester tester, {
    double width = 390,
    double height = 844,
    double scale = 1,
  }) async {
    await pumpScreen(
      tester,
      const AdminHomeScreen(),
      width: width,
      height: height,
      scale: scale,
    );
    await tester.tap(find.byIcon(Icons.timer_outlined));
    await tester.pumpAndSettle();
  }

  Future<void> openPersonnel(
    WidgetTester tester, {
    double width = 390,
    double scale = 1,
  }) async {
    await pumpScreen(
      tester,
      const AdminHomeScreen(),
      width: width,
      scale: scale,
    );
    await tester.tap(find.byIcon(Icons.badge_outlined));
    await tester.pumpAndSettle();
  }

  if (const bool.fromEnvironment('AUDITRON_STORE_SCREENSHOTS')) {
    testWidgets('export Play Store : sept captures telephone 9:16', (
      tester,
    ) async {
      // Render the real screens with demo data, not a stretched phone preview.
      for (final key in storage.keys.toList()) {
        if (key == 'auditron_cache_admin_personnel' ||
            key.startsWith('auditron_cache_admin_retards_') &&
                key != 'auditron_cache_admin_retards_parametres') {
          final rows = jsonDecode(storage[key]!) as List;
          for (var i = 0; i < rows.length; i++) {
            rows[i]['nom'] = i == 0 ? 'Enseignant A.' : 'Agent B.';
            rows[i]['matricule'] = 'DEMO-00${i + 1}';
          }
          storage[key] = jsonEncode(rows);
        }
      }

      Future<void> export(String name) async {
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        expect(tester.getSize(find.byKey(boundaryKey)), const Size(432, 768));
        await tester.runAsync(() async {
          final boundary =
              boundaryKey.currentContext!.findRenderObject()
                  as RenderRepaintBoundary;
          final image = await boundary.toImage(pixelRatio: 2.5);
          try {
            expect(image.width, 1080);
            expect(image.height, 1920);
            final bytes = await image.toByteData(
              format: ui.ImageByteFormat.png,
            );
            expect(bytes!.lengthInBytes, lessThan(8000000));
            final directory = Directory('store-assets/phone-screenshots');
            await directory.create(recursive: true);
            await File('${directory.path}/$name.png').writeAsBytes(
              bytes.buffer.asUint8List(
                bytes.offsetInBytes,
                bytes.lengthInBytes,
              ),
            );
          } finally {
            image.dispose();
          }
        });
      }

      await pumpScreen(tester, const HomeScreen(), width: 432, height: 768);
      expect(find.text('Arrivée enregistrée'), findsOneWidget);
      await export('01-journee-enseignant');
      await tester.tap(find.text('Mathématiques').first);
      await tester.pumpAndSettle();
      expect(find.text('Mes cours du jour'), findsOneWidget);
      await export('02-cours-du-jour');
      await tester.tap(find.byIcon(Icons.home_outlined));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Historique de mes présences'));
      await tester.pumpAndSettle();
      expect(find.text('Mes présences'), findsOneWidget);
      await export('03-historique-presences');

      await pumpScreen(
        tester,
        const AdminHomeScreen(),
        width: 432,
        height: 768,
      );
      expect(find.text('Espace direction'), findsOneWidget);
      await export('04-tableau-de-bord-direction');
      await tester.tap(find.byIcon(Icons.badge_outlined));
      await tester.pumpAndSettle();
      expect(find.text('DEMO-001'), findsOneWidget);
      await export('05-personnel');
      await tester.tap(find.byIcon(Icons.timer_outlined));
      await tester.pumpAndSettle();
      expect(find.text('Enseignant A.'), findsOneWidget);
      await export('06-retards');
      await tester.tap(find.text('Bilans PDF'));
      await tester.pumpAndSettle();
      expect(find.text('Bilan PDF de la section'), findsOneWidget);
      await export('07-bilans-pdf');
    });
  }

  for (final teacherAdmin in [false, true]) {
    testWidgets(
      'le compte enseignant sans acces Auditron, admin=$teacherAdmin',
      (tester) async {
        await pumpScreen(
          tester,
          const HomeScreen(),
          teacherAdmin: teacherAdmin,
        );
        await tester.tap(find.byTooltip('Mon compte'));
        await tester.pumpAndSettle();
        final entries = tester.widgetList<PopupMenuItem<String>>(
          find.byType(PopupMenuItem<String>),
        );
        expect(entries.map((entry) => entry.value), ['schedule', 'password']);
        expect(find.text('Mon emploi du temps'), findsOneWidget);
        expect(find.text('Modifier le mot de passe'), findsOneWidget);
        expect(find.text('Espace équipe Auditron'), findsNothing);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('le compte direction ne propose pas l espace Auditron', (
    tester,
  ) async {
    await pumpScreen(tester, const AdminHomeScreen());
    await tester.tap(find.byTooltip('Compte direction'));
    await tester.pumpAndSettle();
    final entries = tester.widgetList<PopupMenuItem<String>>(
      find.byType(PopupMenuItem<String>),
    );
    expect(entries.map((entry) => entry.value), ['password', 'logout']);
    expect(find.text('Modifier le mot de passe'), findsOneWidget);
    expect(find.text('Déconnexion'), findsOneWidget);
    expect(find.text('Espace équipe Auditron'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('personnel : noms pleine largeur et actions dans la fiche', (
    tester,
  ) async {
    await openPersonnel(tester);
    final name = find.text('DJANKO MBO UOMBOUO THIERRY');
    final card = find.ancestor(of: name, matching: find.byType(Card));
    expect(
      tester.getSize(name).width,
      closeTo(tester.getSize(card).width - 32, 1),
    );
    expect(tester.widget<Text>(name).maxLines, isNull);
    expect(tester.widget<Text>(name).overflow, isNot(TextOverflow.ellipsis));
    expect(find.descendant(of: card, matching: find.byType(Row)), findsNothing);
    expect(
      find.descendant(of: card, matching: find.byType(IconButton)),
      findsNothing,
    );
    expect(find.text('1148431X'), findsOneWidget);
    expect(find.text('Compte administrateur'), findsOneWidget);
    expect(find.byType(CircleAvatar), findsNothing);
    await capture(tester, 'personnel-390');
    await tester.tap(name);
    await tester.pumpAndSettle();
    for (final action in ['Modifier', 'Gérer la photo', 'Supprimer']) {
      final button = find.byWidgetPredicate(
        (widget) => widget is IconButton && widget.tooltip == action,
      );
      expect(button, findsOneWidget);
      expect(tester.widget(button), isA<IconButton>());
      expect(tester.getSize(button), const Size(48, 48));
      expect(find.text(action), findsNothing);
    }
    expect(
      tester.getTopLeft(find.byTooltip('Modifier')).dy,
      tester.getTopLeft(find.byTooltip('Supprimer')).dy,
    );
    for (final action in ['Emploi du temps', 'Modifier le mot de passe']) {
      expect(find.text(action), findsOneWidget);
    }
    expect(find.text('96%'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await capture(tester, 'personnel-sheet-390');
  });

  testWidgets('personnel : la recherche conserve les champs de la fiche', (
    tester,
  ) async {
    await openPersonnel(tester);
    await tester.enterText(find.byType(TextField), 'Administration');
    await tester.pumpAndSettle();
    expect(find.text('BELINGA MARIE'), findsOneWidget);
    expect(find.text('DJANKO MBO UOMBOUO THIERRY'), findsNothing);
    await tester.enterText(find.byType(TextField), '1148431X');
    await tester.pumpAndSettle();
    expect(find.text('DJANKO MBO UOMBOUO THIERRY'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'personnel : les actions photo, modification et suppression restent accessibles',
    (tester) async {
      await openPersonnel(tester);
      await tester.tap(find.text('DJANKO MBO UOMBOUO THIERRY'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Gérer la photo'));
      await tester.pumpAndSettle();
      expect(find.text('Changer la photo'), findsOneWidget);
      expect(find.text('Supprimer la photo'), findsNothing);
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      await tester.tap(find.text('DJANKO MBO UOMBOUO THIERRY'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Modifier'));
      await tester.pumpAndSettle();
      final nomField = find.widgetWithText(TextFormField, 'Nom *');
      expect(
        tester.widget<TextFormField>(nomField).controller!.text,
        'DJANKO MBO UOMBOUO THIERRY',
      );
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      await tester.tap(find.text('DJANKO MBO UOMBOUO THIERRY'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Supprimer'));
      await tester.pumpAndSettle();
      expect(find.byType(AlertDialog), findsOneWidget);
      await tester.tap(find.text('Annuler'));
      await tester.pumpAndSettle();
      expect(find.text('DJANKO MBO UOMBOUO THIERRY'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'personnel : mot de passe et emploi du temps accessibles depuis la fiche',
    (tester) async {
      await openPersonnel(tester);
      await tester.tap(find.text('DJANKO MBO UOMBOUO THIERRY'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Modifier le mot de passe'));
      await tester.pumpAndSettle();
      expect(
        find.widgetWithText(TextFormField, 'Nouveau mot de passe'),
        findsOneWidget,
      );
      expect(
        find.widgetWithText(TextFormField, 'Confirmer le mot de passe'),
        findsOneWidget,
      );
      await tester.binding.handlePopRoute();
      await tester.pumpAndSettle();
      await tester.tap(find.text('DJANKO MBO UOMBOUO THIERRY'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Emploi du temps'));
      await tester.pumpAndSettle();
      expect(find.text('Aucun cours planifié.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  for (final width in [320.0, 600.0]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets(
        'personnel : tiles et fiche sans débordement à ${width}px, texte x$scale',
        (tester) async {
          await openPersonnel(tester, width: width, scale: scale);
          expect(tester.takeException(), isNull);
          await capture(tester, 'personnel-${width.toInt()}-$scale');
          final name = find.text('DJANKO MBO UOMBOUO THIERRY');
          await tester.ensureVisible(name);
          await tester.tap(name);
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          await tester.ensureVisible(find.byTooltip('Supprimer'));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          await capture(tester, 'personnel-sheet-${width.toInt()}-$scale');
        },
      );
    }
  }

  testWidgets(
    'les bilans sont dans un sheet et les noms prennent toute la largeur',
    (tester) async {
      await openRetards(tester);
      expect(find.text('Bilans PDF'), findsOneWidget);
      expect(find.text('Période du bilan'), findsNothing);
      expect(find.text('Bilan PDF cumulé'), findsNothing);
      final name = find.text('DJANKO MBO UOMBOUO THIERRY');
      final card = find.ancestor(of: name, matching: find.byType(Card));
      expect(
        tester.getSize(name).width,
        closeTo(tester.getSize(card).width - 32, 1),
      );
      expect(
        find.descendant(of: card, matching: find.byType(Row)),
        findsNothing,
      );
      expect(tester.widget<Text>(name).maxLines, isNull);
      expect(find.text('3 jours de retard · 124 min cumulées'), findsOneWidget);
      final navigation = NavigationBarTheme.of(
        tester.element(find.byType(NavigationBar)),
      );
      expect(navigation.indicatorColor, AuditronColors.orange100);
      expect(
        navigation.iconTheme!.resolve({WidgetState.selected})!.color,
        AuditronColors.orange700,
      );
      final theme = Theme.of(tester.element(find.text('Bilans PDF')));
      expect(
        theme.filledButtonTheme.style!.backgroundColor!.resolve({}),
        AuditronColors.orange700,
      );
      await capture(tester, 'retards-390');
      await tester.tap(find.text('Bilans PDF'));
      await tester.pumpAndSettle();
      expect(find.text('Télécharger un bilan'), findsOneWidget);
      expect(find.text('Période du bilan'), findsOneWidget);
      expect(find.text('Tolérance des retards (min)'), findsOneWidget);
      expect(find.text('Bilan PDF cumulé'), findsOneWidget);
      expect(
        tester
            .widget<OutlinedButton>(
              find.widgetWithText(OutlinedButton, 'Bilan PDF de la section'),
            )
            .onPressed,
        isNull,
      );
      expect(tester.takeException(), isNull);
      await capture(tester, 'retards-sheet-390');
    },
  );

  testWidgets('la recherche des retards filtre et se réinitialise', (
    tester,
  ) async {
    await openRetards(tester);
    await tester.enterText(find.byType(TextField), 'Administration');
    await tester.pumpAndSettle();
    expect(find.text('BELINGA MARIE'), findsOneWidget);
    expect(find.text('DJANKO MBO UOMBOUO THIERRY'), findsNothing);
    await tester.tap(find.byTooltip('Effacer la recherche'));
    await tester.pumpAndSettle();
    expect(find.text('DJANKO MBO UOMBOUO THIERRY'), findsOneWidget);
  });

  testWidgets(
    'le clic sur un nom signale un téléchargement impossible hors ligne',
    (tester) async {
      await openRetards(tester);
      await tester.tap(find.text('DJANKO MBO UOMBOUO THIERRY'));
      await tester.pumpAndSettle();
      expect(
        find.text(
          'Pas de connexion internet. Vérifiez votre réseau et réessayez.',
        ),
        findsOneWidget,
      );
      expect(find.text('Télécharger un bilan'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'la tolérance se valide et se sauvegarde hors ligne depuis le sheet',
    (tester) async {
      await openRetards(tester);
      await tester.tap(find.text('Bilans PDF'));
      await tester.pumpAndSettle();
      final tolerance = find.widgetWithText(
        TextField,
        'Tolérance des retards (min)',
      );
      await tester.enterText(tolerance, '121');
      await tester.tap(find.text('Enregistrer la tolérance'));
      await tester.pumpAndSettle();
      expect(
        find.text('La tolérance doit être comprise entre 0 et 120 minutes.'),
        findsOneWidget,
      );
      await tester.enterText(tolerance, '15');
      await tester.tap(find.text('Enregistrer la tolérance'));
      await tester.pumpAndSettle();
      expect(find.text('Tolérance enregistrée hors ligne.'), findsOneWidget);
      expect(
        jsonDecode(
          storage['auditron_cache_admin_retards_parametres']!,
        )['tolerance_minutes'],
        15,
      );
      final actions = jsonDecode(storage['auditron_pending_actions']!) as List;
      expect(actions.single['path'], '/retards/parametres');
      expect(actions.single['body']['tolerance_minutes'], 15);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('le téléchargement cumulé affiche son erreur dans le sheet', (
    tester,
  ) async {
    await openRetards(tester);
    await tester.tap(find.text('Bilans PDF'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Bilan PDF cumulé'));
    await tester.pumpAndSettle();
    expect(
      find.text(
        'Pas de connexion internet. Vérifiez votre réseau et réessayez.',
      ),
      findsOneWidget,
    );
    expect(
      tester
          .widget<FilledButton>(
            find.widgetWithText(FilledButton, 'Bilan PDF cumulé'),
          )
          .onPressed,
      isNotNull,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'le clic sur un nom prépare et partage sa fiche avec la période sélectionnée',
    (tester) async {
      await openRetards(tester);
      online = true;
      final requests = <http.Request>[];
      await tester.runAsync(() async {
        final shared = Completer<void>();
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
            .setMockMethodCallHandler(shareChannel, (call) async {
              sharedFiles.addAll(
                ((call.arguments as Map)['paths'] as List).cast<String>(),
              );
              shared.complete();
              return 'saved';
            });
        return http.runWithClient(
          () async {
            final name = find.text('DJANKO MBO UOMBOUO THIERRY');
            final tile = tester.widget<InkWell>(
              find.ancestor(of: name, matching: find.byType(InkWell)).first,
            );
            tile.onTap!();
            await shared.future.timeout(const Duration(seconds: 5));
            await Future<void>.delayed(Duration.zero);
          },
          () => MockClient((request) async {
            requests.add(request);
            return http.Response('%PDF-1.4 test', 200);
          }),
        );
      });
      await tester.pumpAndSettle();
      expect(requests.single.url.path, '/api/retards/bilan/12');
      expect(
        requests.single.url.queryParameters.keys,
        containsAll(['debut', 'fin']),
      );
      expect(sharedFiles.single.endsWith('bilan-1148431X.pdf'), isTrue);
      expect(
        await tester.runAsync(() => File(sharedFiles.single).readAsString()),
        '%PDF-1.4 test',
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'la période choisie dans le sheet actualise la liste des retards',
    (tester) async {
      final now = DateTime.now();
      final previous = DateTime(now.year, now.month - 1, 1);
      final start = previous.toIso8601String().substring(0, 10);
      final end = DateTime(
        previous.year,
        previous.month + 1,
        0,
      ).toIso8601String().substring(0, 10);
      storage['auditron_cache_admin_retards_${start}_$end'] = jsonEncode([
        {
          'enseignant_id': 20,
          'nom': 'PERSONNEL DU MOIS PRÉCÉDENT',
          'matricule': '20',
          'section': 'STT',
          'jours_retard': 2,
          'minutes_retard_total': 30,
        },
      ]);
      final label = DateFormat('MMMM yyyy', 'fr').format(previous);
      final monthLabel = label[0].toUpperCase() + label.substring(1);
      await openRetards(tester);
      await tester.tap(find.text('Bilans PDF'));
      await tester.pumpAndSettle();
      await tester.tap(
        find.widgetWithText(AdminSearchableSelect, 'Période du bilan'),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text(monthLabel));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Fermer les bilans'));
      await tester.pumpAndSettle();
      expect(find.text(monthLabel), findsOneWidget);
      expect(find.text('PERSONNEL DU MOIS PRÉCÉDENT'), findsOneWidget);
      expect(find.text('DJANKO MBO UOMBOUO THIERRY'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('le PDF de section attend une sélection et conserve son filtre', (
    tester,
  ) async {
    await openRetards(tester);
    await tester.tap(find.text('Bilans PDF'));
    await tester.pumpAndSettle();
    final sectionPicker = find.widgetWithText(
      AdminSearchableSelect,
      'Section du bilan',
    );
    await tester.ensureVisible(sectionPicker);
    await tester.tap(sectionPicker);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Industrielle'));
    await tester.pumpAndSettle();
    expect(sharedFiles, isEmpty);
    final export = find.widgetWithText(
      OutlinedButton,
      'Bilan PDF de la section',
    );
    final onPressed = tester.widget<OutlinedButton>(export).onPressed!;
    online = true;
    final requests = <http.Request>[];
    await tester.runAsync(() async {
      final shared = Completer<void>();
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(shareChannel, (call) async {
            sharedFiles.addAll(
              ((call.arguments as Map)['paths'] as List).cast<String>(),
            );
            shared.complete();
            return 'saved';
          });
      return http.runWithClient(
        () async {
          onPressed();
          onPressed();
          await shared.future.timeout(const Duration(seconds: 5));
          await Future<void>.delayed(Duration.zero);
        },
        () => MockClient((request) async {
          requests.add(request);
          return http.Response('%PDF-1.4 test', 200);
        }),
      );
    });
    await tester.pumpAndSettle();
    expect(requests, hasLength(1));
    expect(requests.single.url.path, '/api/retards/bilan-cumule');
    expect(requests.single.url.queryParameters['section'], 'Industrielle');
    expect(
      requests.single.url.queryParameters.keys,
      containsAll(['debut', 'fin']),
    );
    expect(sharedFiles.single, contains('bilan-retards-industrielle-'));
    expect(find.text('PDF prêt à enregistrer ou partager.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  for (final viewport in [
    const Size(320, 844),
    const Size(600, 844),
    const Size(844, 390),
  ]) {
    for (final scale in [1.0, 2.0]) {
      testWidgets(
        'retards et sheet sans débordement à $viewport, texte x$scale',
        (tester) async {
          await openRetards(
            tester,
            width: viewport.width,
            height: viewport.height,
            scale: scale,
          );
          expect(tester.takeException(), isNull);
          await capture(tester, 'retards-${viewport.width.toInt()}-$scale');
          await tester.tap(find.text('Bilans PDF'));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          await tester.ensureVisible(find.text('Bilan PDF de la section'));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull);
          await capture(
            tester,
            'retards-sheet-${viewport.width.toInt()}-$scale',
          );
        },
      );
    }
  }

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
    'signalements : le journal distingue un justificatif du pointage',
    (tester) async {
      online = true;
      await http.runWithClient(
        () => pumpScreen(
          tester,
          const Scaffold(
            body: AdminJournalHebdomadaireTab(categorie: 'enseignant'),
          ),
          width: 320,
        ),
        () => MockClient(
          (request) async => http.Response(
            jsonEncode({
              'debut': getToday(),
              'jours': [
                {'libelle': 'Lun'},
                {'libelle': 'Mar'},
                {'libelle': 'Mer'},
              ],
              'lignes': [
                {
                  'nom': 'Personnel signale',
                  'section': 'Sciences',
                  'jours_presents': 2,
                  'jours_attendus': 2,
                  'taux_assiduite': 100,
                  'jours': [
                    {
                      'attendu': true,
                      'present': false,
                      'est_signale': true,
                      'type_signalement': 'Mission',
                    },
                    {
                      'attendu': true,
                      'present': true,
                      'est_signale': true,
                      'heure_arrivee': '08:00',
                    },
                    {
                      'attendu': true,
                      'present': false,
                      'est_signale': false,
                      'a_venir': true,
                    },
                  ],
                },
              ],
            }),
            200,
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('SIG'), findsOneWidget);
      expect(find.text('08:00'), findsOneWidget);
      expect(find.text('ABS'), findsNothing);
      expect(find.text('100 %'), findsOneWidget);
      expect(find.text('2 / 2'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await capture(tester, 'signalements-journal-320');
    },
  );

  testWidgets('signalements : le dashboard affiche les personnes signalees', (
    tester,
  ) async {
    final key = 'auditron_cache_admin_dashboard_${getToday()}';
    final data = jsonDecode(storage[key]!) as Map<String, dynamic>;
    data['signales_liste'] = [
      {
        'enseignant_id': 13,
        'nom': 'Personnel signale',
        'est_signale': true,
        'type_signalement': 'Mission',
      },
    ];
    storage[key] = jsonEncode(data);
    await pumpScreen(tester, const AdminHomeScreen());
    await tester.scrollUntilVisible(
      find.text('Personnel signale'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pumpAndSettle();
    expect(find.text('Personnel signale'), findsOneWidget);
    expect(find.text('Signalé : Mission'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await capture(tester, 'signalements-dashboard-390');
  });

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
