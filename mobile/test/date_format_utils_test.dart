import 'package:intl/date_symbol_data_local.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:auditron_x_app/utils/date_format_utils.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  test('formats API UTC timestamps in the institution timezone', () {
    const timestamp = '2026-09-26T08:15:00.000000Z';

    expect(formatTime(timestamp), '09:15');
    expect(formatDateTime(timestamp), '26/09/2026 09:15');
  });

  test('leaves timestamps without a timezone suffix unchanged', () {
    expect(formatTime('2026-09-26T08:15:00'), '08:15');
  });
}
