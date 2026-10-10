import 'package:flutter/material.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';

/// Gestion des disciplines (§admin-mobile) — équivalent mobile de
/// DisciplinesPage.jsx.
class AdminDisciplinesScreen extends StatelessWidget {
  const AdminDisciplinesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Disciplines',
      resourcePath: '/disciplines',
      cacheKey: 'admin_disciplines',
      icon: Icons.menu_book,
      fields: const [
        AdminFieldSpec(key: 'nom', label: 'Nom', required: true),
        AdminFieldSpec(key: 'code', label: 'Code', required: true),
        AdminFieldSpec(key: 'coefficient', label: 'Coefficient', type: AdminFieldType.number),
        AdminFieldSpec(key: 'departement', label: 'Département'),
      ],
      itemTitle: (item) => item['nom']?.toString() ?? '—',
      itemSubtitle: (item) => [
        if (item['code'] != null) item['code'],
        if (item['coefficient'] != null) 'Coef. ${item['coefficient']}',
      ].join(' · '),
      headerActions: (context, refresh) => [
        SpreadsheetActionsBar(entity: 'disciplines', onImported: refresh),
      ],
    );
  }
}
