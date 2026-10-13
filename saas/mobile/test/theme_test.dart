import 'package:auditron_x_app/theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Manrope pour le texte courant et les controles', () {
    final theme = buildAuditronTheme();
    for (final style in [
      theme.textTheme.bodyLarge,
      theme.textTheme.bodyMedium,
      theme.textTheme.bodySmall,
      theme.textTheme.labelLarge,
      theme.textTheme.labelMedium,
      theme.textTheme.labelSmall,
      theme.filledButtonTheme.style!.textStyle!.resolve({}),
      theme.chipTheme.labelStyle,
      theme.navigationBarTheme.labelTextStyle!.resolve({WidgetState.selected}),
    ]) {
      expect(style!.fontFamily, AuditronFonts.body);
    }
  });

  test('Space Grotesk pour les titres et la barre de navigation', () {
    final theme = buildAuditronTheme();
    for (final style in [
      theme.textTheme.displayLarge,
      theme.textTheme.displayMedium,
      theme.textTheme.displaySmall,
      theme.textTheme.headlineLarge,
      theme.textTheme.headlineMedium,
      theme.textTheme.headlineSmall,
      theme.textTheme.titleLarge,
      theme.textTheme.titleMedium,
      theme.textTheme.titleSmall,
      theme.appBarTheme.titleTextStyle,
    ]) {
      expect(style!.fontFamily, AuditronFonts.display);
    }
  });

  test('le theme administration conserve les deux familles', () {
    final theme = buildAuditronAdminTheme(buildAuditronTheme());
    expect(theme.textTheme.bodyMedium!.fontFamily, AuditronFonts.body);
    expect(theme.textTheme.titleLarge!.fontFamily, AuditronFonts.display);
    expect(
      theme.navigationBarTheme.labelTextStyle!.resolve({})!.fontFamily,
      AuditronFonts.body,
    );
    expect(theme.textTheme.bodyMedium!.letterSpacing, 0);
    expect(theme.textTheme.titleLarge!.letterSpacing, 0);
  });
}
