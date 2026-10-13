import 'package:auditron_x_app/theme.dart';
import 'package:flutter/services.dart';

Future<void> loadAuditronTestFonts() async {
  final assets = {
    AuditronFonts.body: [
      'Manrope-Regular.ttf',
      'Manrope-Medium.ttf',
      'Manrope-SemiBold.ttf',
      'Manrope-Bold.ttf',
      'Manrope-ExtraBold.ttf',
    ],
    AuditronFonts.display: [
      'SpaceGrotesk-Regular.ttf',
      'SpaceGrotesk-Medium.ttf',
      'SpaceGrotesk-Bold.ttf',
    ],
  };
  for (final entry in assets.entries) {
    final loader = FontLoader(entry.key);
    for (final file in entry.value) {
      loader.addFont(rootBundle.load('assets/fonts/$file'));
    }
    await loader.load();
  }
}
