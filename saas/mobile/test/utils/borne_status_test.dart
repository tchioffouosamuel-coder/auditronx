import 'package:flutter_test/flutter_test.dart';
import 'package:auditron_x_app/utils/borne_status.dart';

void main() {
  final now = DateTime.utc(2026, 10, 11, 12);

  test(
    'une borne est en ligne jusqu’à deux minutes après son dernier signe',
    () {
      final seenAt = now.subtract(const Duration(minutes: 2));

      expect(
        borneIsOnline({'last_seen_at': seenAt.toIso8601String()}, now: now),
        isTrue,
      );
      expect(
        borneIsOnline({
          'last_seen_at': now
              .subtract(const Duration(minutes: 2, seconds: 1))
              .toIso8601String(),
        }, now: now),
        isFalse,
      );
    },
  );

  test('sans signe de vie récent ou si révoquée la borne est hors ligne', () {
    expect(borneIsOnline(const {}, now: now), isFalse);
    expect(
      borneIsOnline({
        'last_seen_at': now.toIso8601String(),
        'revoked_at': now.toIso8601String(),
      }, now: now),
      isFalse,
    );
  });
}
