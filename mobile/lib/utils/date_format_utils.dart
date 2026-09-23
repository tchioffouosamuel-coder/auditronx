import 'package:intl/intl.dart';

final _dateTimeFormat = DateFormat('dd/MM/yyyy HH:mm', 'fr');
final _timeFormat = DateFormat('HH:mm', 'fr');

/// Formate une date/heure ISO-8601 (ex. `2026-09-22T18:20:48.000000Z`,
/// renvoyée telle quelle par l'API) en `dd/MM/yyyy HH:mm` local, pour
/// affichage (§notifications, §historique). Renvoie la chaîne d'origine si
/// elle n'est pas parsable.
String formatDateTime(String? iso) {
  if (iso == null || iso.isEmpty) return '—';
  final parsed = DateTime.tryParse(iso);
  if (parsed == null) return iso;
  return _dateTimeFormat.format(parsed.toLocal());
}

/// Formate seulement l'heure (`HH:mm`) d'une date/heure ISO-8601.
String formatTime(String? iso) {
  if (iso == null || iso.isEmpty) return '—';
  final parsed = DateTime.tryParse(iso);
  if (parsed == null) return iso;
  return _timeFormat.format(parsed.toLocal());
}
