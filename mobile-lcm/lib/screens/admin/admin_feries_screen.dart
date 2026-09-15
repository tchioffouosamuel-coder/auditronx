import 'package:flutter/material.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';

/// Gestion des jours fériés (§admin-mobile) — équivalent mobile de
/// FeriesPage.jsx.
class AdminFeriesScreen extends StatelessWidget {
  const AdminFeriesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Jours fériés',
      resourcePath: '/feries',
      cacheKey: 'admin_feries',
      icon: Icons.event_busy,
      fields: const [
        AdminFieldSpec(key: 'date', label: 'Date', type: AdminFieldType.date, required: true),
        AdminFieldSpec(key: 'libelle', label: 'Libellé', required: true),
        AdminFieldSpec(key: 'description', label: 'Description'),
      ],
      itemTitle: (item) => item['libelle']?.toString() ?? '—',
      itemSubtitle: (item) => item['date']?.toString(),
    );
  }
}
