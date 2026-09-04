import 'package:flutter/material.dart';
import '../models/teacher_notification.dart';
import '../services/api_client.dart';

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
    final data = await ApiClient.instance.get('/notifications') as List<dynamic>;
    return data.map((e) => TeacherNotificationEntry.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> _markRead(TeacherNotificationEntry entry) async {
    if (entry.isRead) return;
    await ApiClient.instance.post('/notifications/${entry.id}/read', {});
    setState(() => _future = _load());
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
