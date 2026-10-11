import 'package:intl/intl.dart';

const borneOnlineWindow = Duration(minutes: 2);

DateTime? borneLastSeen(Map<String, dynamic> item) {
  final value = item['last_seen_at'];
  if (value is! String || value.isEmpty) return null;
  return DateTime.tryParse(value);
}

bool borneIsOnline(Map<String, dynamic> item, {DateTime? now}) {
  if (item['revoked_at'] != null) return false;
  final seenAt = borneLastSeen(item);
  if (seenAt == null) return false;
  final age = (now ?? DateTime.now()).difference(seenAt);
  return !age.isNegative && age <= borneOnlineWindow;
}

String borneLastSeenLabel(Map<String, dynamic> item) {
  final seenAt = borneLastSeen(item);
  return seenAt == null
      ? 'Jamais'
      : DateFormat('dd/MM/yyyy HH:mm:ss').format(seenAt.toLocal());
}
