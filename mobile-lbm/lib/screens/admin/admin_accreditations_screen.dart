import 'package:flutter/material.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';

/// Gestion des accréditations (§admin-mobile) — équivalent mobile
/// d'AccreditationsPage.jsx.
class AdminAccreditationsScreen extends StatelessWidget {
  const AdminAccreditationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Accréditations',
      resourcePath: '/accreditations',
      cacheKey: 'admin_accreditations',
      icon: Icons.verified_user,
      fields: const [
        AdminFieldSpec(key: 'label', label: 'Libellé', required: true),
        AdminFieldSpec(key: 'groupe', label: "Groupe (section, ou '*' pour accès complet)"),
        AdminFieldSpec(key: 'niveau', label: 'Niveau (1-4)', type: AdminFieldType.number),
      ],
      itemTitle: (item) => item['label']?.toString() ?? '—',
      itemSubtitle: (item) => [
        if (item['groupe'] != null) 'Groupe : ${item['groupe']}',
        if (item['niveau'] != null) 'Niveau ${item['niveau']}',
      ].join(' · '),
    );
  }
}
