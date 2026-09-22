import 'package:flutter/material.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';

/// Gestion des bornes BLE (§admin-mobile) — équivalent mobile de l'onglet
/// « Bornes (BLE) » de AppareilsPage.jsx.
class AdminBornesScreen extends StatelessWidget {
  const AdminBornesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Bornes (BLE)',
      resourcePath: '/access-points',
      cacheKey: 'admin_bornes',
      icon: Icons.bluetooth,
      readOnly: true,
      fields: const [
        AdminFieldSpec(
          key: 'bssid',
          label: 'Adresse BLE de la borne',
          required: true,
          helperText: 'Ex: AA:BB:CC:DD:EE:FF',
        ),
        AdminFieldSpec(key: 'ssid', label: 'Nom BLE annoncé (facultatif)'),
        AdminFieldSpec(key: 'label', label: 'Libellé'),
      ],
      itemTitle: (item) =>
          item['label']?.toString() ?? item['bssid']?.toString() ?? '—',
      itemSubtitle: (item) => [
        item['bssid'],
        item['ssid'],
      ].where((v) => v != null && v.toString().isNotEmpty).join(' · '),
    );
  }
}
