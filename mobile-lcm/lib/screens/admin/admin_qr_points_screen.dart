import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../theme.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';

/// Gestion des points QR (§admin-mobile) — équivalent mobile de l'onglet
/// « Points QR » de AppareilsPage.jsx. Le champ `code` (UUID) est généré côté
/// serveur (cf. QrPointController@store) et n'est donc pas éditable, seule sa
/// consultation via QR est proposée ici (pas d'impression native sur mobile).
class AdminQrPointsScreen extends StatelessWidget {
  const AdminQrPointsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Points QR',
      resourcePath: '/qr-points',
      cacheKey: 'admin_qr_points',
      icon: Icons.qr_code,
      fields: const [
        AdminFieldSpec(key: 'label', label: 'Libellé', required: true),
      ],
      itemTitle: (item) => item['label']?.toString() ?? item['code']?.toString() ?? '—',
      itemSubtitle: (item) => item['code']?.toString(),
      extraRowActions: (context, item) => [
        IconButton(
          icon: const Icon(Icons.qr_code_2, size: 20),
          onPressed: () => _showQr(context, item),
        ),
      ],
    );
  }

  void _showQr(BuildContext context, Map<String, dynamic> item) {
    final code = item['code']?.toString() ?? '';
    final label = item['label']?.toString();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 24, 24, 32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (label != null && label.isNotEmpty)
              Text(label, style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AuditronColors.ink100),
              ),
              child: code.isEmpty
                  ? const SizedBox(
                      width: 240,
                      height: 240,
                      child: Center(child: Text('Code indisponible')),
                    )
                  : QrImageView(data: code, size: 240),
            ),
            const SizedBox(height: 16),
            SelectableText(
              code,
              textAlign: TextAlign.center,
              style: const TextStyle(fontFamily: 'monospace', color: AuditronColors.ink500),
            ),
          ],
        ),
      ),
    );
  }
}
