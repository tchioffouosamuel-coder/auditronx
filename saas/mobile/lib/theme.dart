import 'package:flutter/material.dart';

class AuditronFonts {
  static const body = 'Manrope';
  static const display = 'Space Grotesk';
}

/// Palette commune aux interfaces mobiles de la vitrine et de l'application.
class AuditronColors {
  static const brand900 = Color(0xFF043B2B);
  static const brand800 = Color(0xFF064B36);
  static const brand700 = Color(0xFF075F43);
  static const brand600 = Color(0xFF08734F);
  static const brand500 = Color(0xFF14825E);
  static const brand100 = Color(0xFFD8F1E5);
  static const brand50 = Color(0xFFEEF9F4);

  static const orange700 = Color(0xFFC2410C);
  static const orange500 = Color(0xFFF97316);
  static const orange100 = Color(0xFFFFEDD5);
  static const gold600 = Color(0xFFCA8A04);
  static const gold500 = Color(0xFFEAB308);
  static const gold100 = Color(0xFFFEF3C7);

  static const ink900 = Color(0xFF0F172A);
  static const ink700 = Color(0xFF334155);
  static const ink500 = Color(0xFF64748B);
  static const ink100 = Color(0xFFE2E8F0);
  static const ink50 = Color(0xFFF8FAFC);
}

ThemeData buildAuditronTheme() {
  final colorScheme = ColorScheme.fromSeed(
    seedColor: AuditronColors.brand700,
    brightness: Brightness.light,
    primary: AuditronColors.brand700,
    secondary: AuditronColors.orange700,
    surface: Colors.white,
    onSurface: AuditronColors.ink900,
  );

  // Les deux polices de la vitrine sont embarquees pour le mode hors ligne.
  final textTheme = _auditronTextTheme().apply(
    bodyColor: AuditronColors.ink700,
    displayColor: AuditronColors.ink900,
  );

  return ThemeData(
    useMaterial3: true,
    fontFamily: AuditronFonts.body,
    colorScheme: colorScheme,
    scaffoldBackgroundColor: AuditronColors.ink50,
    dividerColor: AuditronColors.ink100,
    appBarTheme: const AppBarTheme(
      backgroundColor: AuditronColors.brand700,
      foregroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
        fontFamily: AuditronFonts.display,
        fontSize: 20,
        fontWeight: FontWeight.w700,
        color: Colors.white,
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AuditronColors.brand700,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        textStyle: const TextStyle(
          fontFamily: AuditronFonts.body,
          fontWeight: FontWeight.w700,
          fontSize: 15,
        ),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AuditronColors.ink100),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AuditronColors.ink100),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(8),
        borderSide: const BorderSide(color: AuditronColors.brand700, width: 2),
      ),
    ),
    cardTheme: CardThemeData(
      margin: EdgeInsets.zero,
      elevation: 0,
      color: Colors.white,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
        side: const BorderSide(color: AuditronColors.ink100),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      indicatorColor: AuditronColors.brand100,
      height: 72,
      labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontFamily: AuditronFonts.body,
          fontSize: 11,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w500,
          color: states.contains(WidgetState.selected)
              ? AuditronColors.brand700
              : AuditronColors.ink500,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          color: states.contains(WidgetState.selected)
              ? AuditronColors.brand700
              : AuditronColors.ink500,
        ),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: Colors.white,
      selectedColor: AuditronColors.brand100,
      labelStyle: const TextStyle(
        fontFamily: AuditronFonts.body,
        color: AuditronColors.brand700,
        fontWeight: FontWeight.w600,
      ),
      side: const BorderSide(color: AuditronColors.ink100),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
    ),
    textTheme: textTheme.copyWith(
      headlineSmall: textTheme.headlineSmall?.copyWith(
        fontWeight: FontWeight.w700,
      ),
      titleLarge: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      titleMedium: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
      bodyMedium: textTheme.bodyMedium?.copyWith(height: 1.4),
    ),
  );
}

ThemeData buildAuditronAdminTheme(ThemeData base) {
  final actionStyle = FilledButton.styleFrom(
    backgroundColor: AuditronColors.orange700,
    foregroundColor: Colors.white,
  );
  return base.copyWith(
    filledButtonTheme: FilledButtonThemeData(
      style: (base.filledButtonTheme.style ?? const ButtonStyle()).copyWith(
        backgroundColor: actionStyle.backgroundColor,
        foregroundColor: actionStyle.foregroundColor,
      ),
    ),
    floatingActionButtonTheme: base.floatingActionButtonTheme.copyWith(
      backgroundColor: AuditronColors.orange700,
      foregroundColor: Colors.white,
    ),
    navigationBarTheme: base.navigationBarTheme.copyWith(
      indicatorColor: AuditronColors.orange100,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontFamily: AuditronFonts.body,
          fontSize: 11,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w500,
          color: states.contains(WidgetState.selected)
              ? AuditronColors.orange700
              : AuditronColors.ink500,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          color: states.contains(WidgetState.selected)
              ? AuditronColors.orange700
              : AuditronColors.ink500,
        ),
      ),
    ),
  );
}

TextTheme _auditronTextTheme() {
  final base = ThemeData.light().textTheme;
  TextStyle? style(TextStyle? value, {bool heading = false}) => value?.copyWith(
    fontFamily: heading ? AuditronFonts.display : AuditronFonts.body,
    letterSpacing: 0,
  );
  return TextTheme(
    displayLarge: style(base.displayLarge, heading: true),
    displayMedium: style(base.displayMedium, heading: true),
    displaySmall: style(base.displaySmall, heading: true),
    headlineLarge: style(base.headlineLarge, heading: true),
    headlineMedium: style(base.headlineMedium, heading: true),
    headlineSmall: style(base.headlineSmall, heading: true),
    titleLarge: style(base.titleLarge, heading: true),
    titleMedium: style(base.titleMedium, heading: true),
    titleSmall: style(base.titleSmall, heading: true),
    bodyLarge: style(base.bodyLarge),
    bodyMedium: style(base.bodyMedium),
    bodySmall: style(base.bodySmall),
    labelLarge: style(base.labelLarge),
    labelMedium: style(base.labelMedium),
    labelSmall: style(base.labelSmall),
  );
}
