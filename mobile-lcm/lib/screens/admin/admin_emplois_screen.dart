import 'package:flutter/material.dart';

import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';

/// Gestion des emplois du temps (§4.2 — admin-mobile) — équivalent mobile de
/// `EmploisPage.jsx`. Le serveur rejette les créneaux qui se chevauchent pour
/// un même enseignant/classe un même jour avec une erreur 422 sur
/// `heure_debut` ; [AdminCrudScreen] affiche déjà ce message via un SnackBar.
class AdminEmploisScreen extends StatelessWidget {
  const AdminEmploisScreen({super.key});

  static const List<AdminFieldOption> _jours = [
    AdminFieldOption(1, 'Lundi'),
    AdminFieldOption(2, 'Mardi'),
    AdminFieldOption(3, 'Mercredi'),
    AdminFieldOption(4, 'Jeudi'),
    AdminFieldOption(5, 'Vendredi'),
    AdminFieldOption(6, 'Samedi'),
    AdminFieldOption(7, 'Dimanche'),
  ];

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'enseignant_id',
      label: 'Enseignant',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/personnel?per_page=500',
    ),
    const AdminFieldSpec(
      key: 'classe_id',
      label: 'Classe',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/classes',
    ),
    const AdminFieldSpec(
      key: 'discipline_id',
      label: 'Discipline',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/disciplines',
    ),
    const AdminFieldSpec(
      key: 'jour',
      label: 'Jour',
      type: AdminFieldType.select,
      required: true,
      options: _jours,
    ),
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Heure de début',
      required: true,
      helperText: 'Format HH:MM',
    ),
    const AdminFieldSpec(
      key: 'heure_fin',
      label: 'Heure de fin',
      required: true,
      helperText: 'Format HH:MM',
    ),
    const AdminFieldSpec(key: 'salle', label: 'Salle'),
    const AdminFieldSpec(key: 'type_cours', label: 'Type de cours'),
  ];

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Emplois du temps',
      resourcePath: '/emplois',
      cacheKey: 'admin_emplois',
      icon: Icons.schedule,
      fields: _fields,
      itemTitle: (item) =>
          '${item['classe']?['nom'] ?? item['classe_id']} — ${item['discipline']?['nom'] ?? item['discipline_id']}',
      itemSubtitle: (item) =>
          '${item['enseignant']?['nom'] ?? ''} · jour ${item['jour']} · ${item['heure_debut']}–${item['heure_fin']}'
          '${item['salle'] != null ? ' · ${item['salle']}' : ''}',
      headerActions: (context, refresh) => [
        SpreadsheetActionsBar(entity: 'emplois', onImported: refresh),
      ],
    );
  }
}
