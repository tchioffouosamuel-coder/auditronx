import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/presence.dart';
import 'api_client.dart';

class PresenceRepository {
  PresenceRepository({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _cacheKey = 'auditron_presence_history_cache';
  static const _localArrivalKey = 'auditron_local_arrival_at';
  static const _localHistoryKey = 'auditron_local_presence_history';
  static const _minimumPresenceDuration = Duration(minutes: 40);

  final FlutterSecureStorage _storage;

  Future<List<PresenceEntry>> load() async {
    final localEntries = await _loadLocalHistory();
    try {
      final data =
          await ApiClient.instance.get('/mes-presences') as List<dynamic>;
      final serverEntries = _parse(data);
      await _reconcileLocalHistory(serverEntries, localEntries);
      final entries = _merge(serverEntries, localEntries);
      await _cache(entries);
      return entries;
    } catch (_) {
      final cached = await _loadCache();
      if (cached != null) return _merge(cached, localEntries);
      if (localEntries.isNotEmpty) return localEntries;
      rethrow;
    }
  }

  Future<void> clearCache() => _storage.delete(key: _cacheKey);

  /// Indique si le prochain scan personnel serait un départ trop précoce.
  /// L'état local couvre le cas où la borne a déjà accepté l'arrivée mais où
  /// la synchronisation API n'est pas encore terminée.
  Future<bool> departureTooEarlyToday() async {
    return await departureTimeRemainingToday() != null;
  }

  /// Retourne le temps restant avant de pouvoir pointer une sortie, ou null
  /// s'il n'y a pas d'arrivée ouverte trop récente aujourd'hui.
  Future<Duration?> departureTimeRemainingToday() async {
    final now = DateTime.now();
    final arrivals = <DateTime>[];

    final localRaw = await _storage.read(key: _localArrivalKey);
    final localArrival = localRaw == null ? null : DateTime.tryParse(localRaw);
    if (localArrival != null && _isToday(localArrival, now)) {
      arrivals.add(localArrival);
    }

    try {
      final entries = await load();
      for (final entry in entries) {
        if (entry.date != _dateKey(now) ||
            entry.heureArrivee == null ||
            entry.heureDepart != null) {
          continue;
        }
        final arrival = DateTime.tryParse(entry.heureArrivee!);
        if (arrival != null) {
          arrivals.add(arrival.toLocal());
        }
      }
    } catch (_) {
      // La borne continue à fonctionner hors ligne grâce à l'état local.
    }

    if (arrivals.isEmpty) return null;
    final latestArrival = arrivals.reduce((a, b) => a.isAfter(b) ? a : b);
    final remaining = _minimumPresenceDuration - now.difference(latestArrival);
    return remaining.isNegative ? null : remaining;
  }

  Future<bool> hasOpenArrivalToday() async {
    final now = DateTime.now();
    final localRaw = await _storage.read(key: _localArrivalKey);
    final localArrival = localRaw == null ? null : DateTime.tryParse(localRaw);
    if (localArrival != null && _isToday(localArrival, now)) return true;

    try {
      final entries = await load();
      return entries.any(
        (entry) =>
            entry.date == _dateKey(now) &&
            entry.heureArrivee != null &&
            entry.heureDepart == null,
      );
    } catch (_) {
      return false;
    }
  }

  Future<void> markSuccessfulScan({required bool wasDeparture}) async {
    final now = DateTime.now();
    final entries = await _loadLocalHistory();
    final date = _dateKey(now);
    final index = entries.indexWhere((entry) => entry.date == date);
    final current = index >= 0 ? entries[index] : null;
    final updated = PresenceEntry(
      id: current?.id ?? -now.millisecondsSinceEpoch,
      date: date,
      heureArrivee:
          current?.heureArrivee ??
          (wasDeparture ? null : now.toIso8601String()),
      heureDepart: wasDeparture ? now.toIso8601String() : current?.heureDepart,
      source: current?.source ?? 'app_mobile',
      minutesRetard: current?.minutesRetard,
    );

    if (index >= 0) {
      entries[index] = updated;
    } else {
      entries.add(updated);
    }
    await _saveLocalHistory(entries);

    if (wasDeparture) {
      await _storage.delete(key: _localArrivalKey);
    } else {
      await _storage.write(key: _localArrivalKey, value: now.toIso8601String());
    }
  }

  bool _isToday(DateTime value, DateTime now) =>
      value.year == now.year &&
      value.month == now.month &&
      value.day == now.day;

  String _dateKey(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  List<PresenceEntry> _parse(List<dynamic> data) => data
      .map((entry) => PresenceEntry.fromJson(entry as Map<String, dynamic>))
      .toList();

  Future<void> _cache(List<PresenceEntry> entries) => _storage.write(
    key: _cacheKey,
    value: jsonEncode(entries.map(_toJson).toList()),
  );

  Future<List<PresenceEntry>> _loadLocalHistory() async {
    final raw = await _storage.read(key: _localHistoryKey);
    if (raw == null) return [];

    try {
      return _parse(jsonDecode(raw) as List<dynamic>);
    } catch (_) {
      return [];
    }
  }

  Future<void> _saveLocalHistory(List<PresenceEntry> entries) => _storage.write(
    key: _localHistoryKey,
    value: jsonEncode(entries.map(_toJson).toList()),
  );

  Future<void> _reconcileLocalHistory(
    List<PresenceEntry> serverEntries,
    List<PresenceEntry> localEntries,
  ) async {
    final completedDates = serverEntries
        .where((entry) => entry.heureDepart != null)
        .map((entry) => entry.date)
        .toSet();
    final remaining = localEntries
        .where((entry) => !completedDates.contains(entry.date))
        .toList();
    if (remaining.length != localEntries.length) {
      await _saveLocalHistory(remaining);
    }
  }

  List<PresenceEntry> _merge(
    List<PresenceEntry> serverEntries,
    List<PresenceEntry> localEntries,
  ) {
    final merged = <String, PresenceEntry>{
      for (final entry in serverEntries) entry.date: entry,
    };
    for (final local in localEntries) {
      final server = merged[local.date];
      merged[local.date] = PresenceEntry(
        id: server?.id ?? local.id,
        date: local.date,
        heureArrivee: server?.heureArrivee ?? local.heureArrivee,
        heureDepart: server?.heureDepart ?? local.heureDepart,
        source: server?.source ?? local.source,
        minutesRetard: server?.minutesRetard ?? local.minutesRetard,
      );
    }
    return merged.values.toList()..sort((a, b) => b.date.compareTo(a.date));
  }

  Map<String, dynamic> _toJson(PresenceEntry entry) => {
    'id': entry.id,
    'date': entry.date,
    'heure_arrivee': entry.heureArrivee,
    'heure_depart': entry.heureDepart,
    'source': entry.source,
    'minutes_retard': entry.minutesRetard,
  };

  Future<List<PresenceEntry>?> _loadCache() async {
    final raw = await _storage.read(key: _cacheKey);
    if (raw == null) return null;

    try {
      return _parse(jsonDecode(raw) as List<dynamic>);
    } catch (_) {
      return null;
    }
  }
}
