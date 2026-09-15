import 'package:flutter/material.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';

/// Gestion des classes (§admin-mobile) — équivalent mobile de ClassesPage.jsx.
class AdminClassesScreen extends StatelessWidget {
  const AdminClassesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Classes',
      resourcePath: '/classes',
      cacheKey: 'admin_classes',
      icon: Icons.school,
      fields: const [
        AdminFieldSpec(key: 'nom', label: 'Nom', required: true),
        AdminFieldSpec(key: 'code', label: 'Code', required: true),
        AdminFieldSpec(key: 'niveau', label: 'Niveau'),
        AdminFieldSpec(key: 'specialite', label: 'Spécialité'),
        AdminFieldSpec(key: 'effectif', label: 'Effectif', type: AdminFieldType.number),
      ],
      itemTitle: (item) => item['nom']?.toString() ?? '—',
      itemSubtitle: (item) => [
        if (item['code'] != null) item['code'],
        if (item['effectif'] != null) '${item['effectif']} élèves',
      ].join(' · '),
      headerActions: (context, refresh) => [
        SpreadsheetActionsBar(entity: 'classes', onImported: refresh),
      ],
    );
  }
}
