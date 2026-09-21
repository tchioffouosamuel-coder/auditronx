import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Identité visuelle Auditron X — palette extraite du logo (vert circuit + or),
/// partagée avec le backoffice React. Montserrat donne à l'app une présence
/// plus nette sur les écrans d'administration et les données denses.
class AuditronColors {
  static const brand900 = Color(0xFF00281A);
  static const brand800 = Color(0xFF003B27);
  static const brand700 = Color(0xFF0F6E49);
  static const brand600 = Color(0xFF16805A);
  static const brand500 = Color(0xFF23855D);
  static const brand100 = Color(0xFFE2F2EA);
  static const brand50 = Color(0xFFF2F9F6);

  static const gold600 = Color(0xFFC4972F);
  static const gold500 = Color(0xFFE0B44A);
  static const gold100 = Color(0xFFFBF1DC);

  static const ink900 = Color(0xFF16241D);
  static const ink700 = Color(0xFF33443C);
  static const ink500 = Color(0xFF5B6B63);
  static const ink100 = Color(0xFFE4E7E2);
  static const ink50 = Color(0xFFF7F8F5);
}

ThemeData buildAuditronTheme() {
  final colorScheme = ColorScheme.fromSeed(
    seedColor: AuditronColors.brand700,
    brightness: Brightness.light,
    primary: AuditronColors.brand700,
    secondary: AuditronColors.gold500,
    surface: Colors.white,
  );

  return ThemeData(
    useMaterial3: true,
    fontFamily: GoogleFonts.montserrat().fontFamily,
    colorScheme: colorScheme,
    scaffoldBackgroundColor: AuditronColors.ink50,
    appBarTheme: const AppBarTheme(
      backgroundColor: AuditronColors.brand900,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: false,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AuditronColors.brand700,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AuditronColors.ink100),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AuditronColors.ink100),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AuditronColors.brand700, width: 2),
      ),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      color: Colors.white,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AuditronColors.ink100),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: AuditronColors.brand900,
      indicatorColor: AuditronColors.gold500,
      indicatorShape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
      ),
      labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (states) => TextStyle(
          fontSize: 12,
          fontWeight: states.contains(WidgetState.selected)
              ? FontWeight.w700
              : FontWeight.w500,
          color: states.contains(WidgetState.selected)
              ? AuditronColors.brand900
              : Colors.white70,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (states) => IconThemeData(
          color: states.contains(WidgetState.selected)
              ? AuditronColors.brand900
              : Colors.white70,
        ),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: AuditronColors.gold100,
      labelStyle: const TextStyle(
        color: AuditronColors.gold600,
        fontWeight: FontWeight.w600,
      ),
      side: BorderSide.none,
    ),
    textTheme: GoogleFonts.montserratTextTheme().copyWith(
      headlineSmall: const TextStyle(
        fontWeight: FontWeight.w900,
        color: AuditronColors.ink900,
      ),
      titleLarge: const TextStyle(
        fontWeight: FontWeight.w700,
        color: AuditronColors.ink900,
      ),
      titleMedium: const TextStyle(
        fontWeight: FontWeight.w700,
        color: AuditronColors.ink900,
      ),
      bodyMedium: const TextStyle(color: AuditronColors.ink700),
    ),
  );
}
