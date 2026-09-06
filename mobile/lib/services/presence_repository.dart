import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/presence.dart';
import 'api_client.dart';

class PresenceRepository {
  PresenceRepository({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _cacheKey = 'auditron_presence_history_cache';

  final FlutterSecureStorage _storage;

  Future<List<PresenceEntry>> load() async {
    try {
      final data =
          await ApiClient.instance.get('/mes-presences') as List<dynamic>;
      final entries = _parse(data);
      await _cache(data);
      return entries;
    } catch (_) {
      final cached = await _loadCache();
      if (cached != null) return cached;
      rethrow;
    }
  }

  Future<void> clearCache() => _storage.delete(key: _cacheKey);

  List<PresenceEntry> _parse(List<dynamic> data) => data
      .map((entry) => PresenceEntry.fromJson(entry as Map<String, dynamic>))
      .toList();

  Future<void> _cache(List<dynamic> data) =>
      _storage.write(key: _cacheKey, value: jsonEncode(data));

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
