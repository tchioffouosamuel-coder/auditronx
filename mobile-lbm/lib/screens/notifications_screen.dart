import 'package:flutter/material.dart';
import '../models/teacher_notification.dart';
import '../services/api_client.dart';
import '../services/offline/offline_cache.dart';
import '../services/offline/pending_action.dart';
import '../services/offline/pending_actions_queue.dart';
import '../services/offline/sync_engine.dart';

const _cacheKey = 'teacher_notifications';

/// Notifications (§4.1) : alerte l'enseignant qu'un scan a été effectué en
/// son nom par un tiers (procuration).
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<List<TeacherNotificationEntry>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<TeacherNotificationEntry>> _load() async {
    final data = await OfflineCache.instance.readThrough(_cacheKey, () => ApiClient.instance.get('/notifications'));
    return (data as List<dynamic>).map((e) => TeacherNotificationEntry.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// Marque comme lu optimistiquement (§offline-sync) : l'échec réseau part en
  /// file d'attente plutôt que de bloquer l'action, l'écran se met à jour tout
  /// de suite.
  Future<void> _markRead(TeacherNotificationEntry entry) async {
    if (entry.isRead) return;

    final raw = await OfflineCache.instance.readThrough(_cacheKey, () => ApiClient.instance.get('/notifications')) as List<dynamic>;
    final entries = List<dynamic>.from(raw);
    final index = entries.indexWhere((e) => e['id'] == entry.id);
    if (index != -1) {
      final updated = Map<String, dynamic>.from(entries[index] as Map<String, dynamic>);
      updated['read_at'] = DateTime.now().toIso8601String();
      entries[index] = updated;
      await OfflineCache.instance.overwrite(_cacheKey, entries);
    }
    setState(() => _future = _load());

    try {
      await ApiClient.instance.post('/notifications/${entry.id}/read', {});
    } on ApiException catch (e) {
      if (e.statusCode != 0) rethrow;
      await PendingActionsQueue.instance.enqueue(
        authMode: AuthMode.teacher,
        path: '/notifications/${entry.id}/read',
        body: const {},
        label: 'Marquer la notification "${entry.message}" comme lue',
      );
      await SyncEngine.instance.notifyEnqueued();
    }
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<TeacherNotificationEntry>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }

          final entries = snapshot.data ?? [];
          if (entries.isEmpty) {
            return ListView(children: const [Padding(padding: EdgeInsets.all(24), child: Text('Aucune notification.'))]);
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: entries.length,
            separatorBuilder: (_, _) => const SizedBox(height: 8),
            itemBuilder: (context, i) {
              final e = entries[i];
              return Card(
                color: e.isRead ? null : Colors.blue.shade50,
                child: ListTile(
                  leading: Icon(e.isRead ? Icons.notifications_none : Icons.notifications_active, color: e.isRead ? Colors.grey : Colors.blue),
                  title: Text(e.message),
                  subtitle: Text(e.createdAt),
                  onTap: () => _markRead(e),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
