import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/offline/sync_engine.dart';
import '../theme.dart';

/// Bandeau d'état de synchro (§offline-sync) : affiché en haut des écrans
/// principaux (enseignant et admin) quand l'app est hors-ligne, ou qu'il reste
/// des actions en attente de synchro — transparence sur l'état des données
/// affichées (potentiellement en cache) et des actions faites hors-ligne.
class SyncStatusBanner extends StatelessWidget {
  const SyncStatusBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final sync = context.watch<SyncEngine>();

    if (sync.online && sync.pendingCount == 0) return const SizedBox.shrink();

    final String message;
    final Color color;
    final IconData icon;

    if (!sync.online) {
      message = sync.pendingCount > 0
          ? 'Hors-ligne — données en cache, ${sync.pendingCount} action(s) en attente'
          : 'Hors-ligne — données en cache';
      color = AuditronColors.ink700;
      icon = Icons.cloud_off;
    } else {
      message = sync.syncing ? 'Synchronisation en cours…' : '${sync.pendingCount} action(s) en attente de synchro';
      color = AuditronColors.gold600;
      icon = Icons.sync;
    }

    return Container(
      width: double.infinity,
      color: color,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Icon(icon, color: Colors.white, size: 16),
          const SizedBox(width: 8),
          Expanded(child: Text(message, style: const TextStyle(color: Colors.white, fontSize: 12))),
        ],
      ),
    );
  }
}
