import 'dart:ui' as ui;
import 'package:auditron_x_app/widgets/auth/auth_pattern_background.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await const SvgAssetLoader(AuthPatternBackground.assetPath).loadBytes(null);
  });

  for (final size in [
    const Size(240, 240),
    const Size(320, 640),
    const Size(600, 360),
    const Size(1280, 800),
  ]) {
    testWidgets('le SVG est visible et couvre le fond à $size', (tester) async {
      tester.view.physicalSize = size;
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final key = GlobalKey();
      await tester.pumpWidget(
        RepaintBoundary(
          key: key,
          child: const Directionality(
            textDirection: TextDirection.ltr,
            child: ColoredBox(
              color: Colors.white,
              child: AuthPatternBackground(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      final expectedTiles =
          (size.width / AuthPatternBackground.tileSize).ceil() *
          (size.height / AuthPatternBackground.tileSize).ceil();
      expect(find.byType(SvgPicture), findsNWidgets(expectedTiles));
      final ignore = tester.widget<IgnorePointer>(
        find.descendant(
          of: find.byType(AuthPatternBackground),
          matching: find.byType(IgnorePointer),
        ),
      );
      expect(ignore.ignoring, isTrue);
      expect(
        find.descendant(
          of: find.byType(AuthPatternBackground),
          matching: find.byType(ExcludeSemantics),
        ),
        findsOneWidget,
      );

      await tester.runAsync(() async {
        final boundary =
            key.currentContext!.findRenderObject() as RenderRepaintBoundary;
        final image = await boundary.toImage();
        final bytes = await image.toByteData(
          format: ui.ImageByteFormat.rawRgba,
        );
        final pixels = bytes!.buffer.asUint8List(
          bytes.offsetInBytes,
          bytes.lengthInBytes,
        );
        var visiblePixels = 0;
        var darkPixels = 0;
        for (var offset = 0; offset < pixels.length; offset += 4) {
          if (pixels[offset] < 250 ||
              pixels[offset + 1] < 250 ||
              pixels[offset + 2] < 250) {
            visiblePixels++;
          }
          if (pixels[offset] < 200 ||
              pixels[offset + 1] < 200 ||
              pixels[offset + 2] < 200) {
            darkPixels++;
          }
        }
        expect(visiblePixels, greaterThan(250));
        expect(visiblePixels, lessThan(size.width * size.height * 0.15));
        expect(darkPixels, 0);
        image.dispose();
      });
    });
  }

  testWidgets('le motif laisse les boutons recevoir les clics', (tester) async {
    var taps = 0;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Stack(
            fit: StackFit.expand,
            children: [
              Center(
                child: FilledButton(
                  onPressed: () => taps++,
                  child: const Text('Continuer'),
                ),
              ),
              const AuthPatternBackground(),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Continuer'));
    expect(taps, 1);
  });
}
