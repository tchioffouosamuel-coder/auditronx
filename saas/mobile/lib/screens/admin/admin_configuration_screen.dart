import 'package:flutter/material.dart';

import '../../theme.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/admin_select_field.dart';
import 'admin_emplois_screen.dart' show heureAffichee, plageAffichee;

const List<AdminFieldOption> _jours = [
  AdminFieldOption(1, 'Lundi'),
  AdminFieldOption(2, 'Mardi'),
  AdminFieldOption(3, 'Mercredi'),
  AdminFieldOption(4, 'Jeudi'),
  AdminFieldOption(5, 'Vendredi'),
  AdminFieldOption(6, 'Samedi'),
  AdminFieldOption(7, 'Dimanche'),
];

String _nomJour(dynamic jour) =>
    _jours
        .where((option) => option.value == (jour as num?)?.toInt())
        .map((option) => option.label)
        .firstOrNull ??
    'Jour inconnu';

/// Configuration de l'établissement (§4.2) — équivalent mobile de
/// `ConfigurationPage.jsx`. Deux réglages qui décident de ce que l'application
/// propose à la saisie et de ce qu'elle attend d'une personne :
///
/// - les **périodes de cours**, seule source des listes déroulantes d'horaire
///   de l'emploi du temps ;
/// - les **emplois du temps spéciaux**, pour un personnel dont l'activité est
///   générale et ne se découpe pas en cours.
class AdminConfigurationScreen extends StatefulWidget {
  const AdminConfigurationScreen({super.key});

  @override
  State<AdminConfigurationScreen> createState() =>
      _AdminConfigurationScreenState();
}

class _AdminConfigurationScreenState extends State<AdminConfigurationScreen> {
  @override
  void dispose() {
    // Les options de sélection sont mémorisées par endpoint : sans cette
    // purge, l'écran des emplois du temps proposerait encore l'ancienne
    // grille horaire jusqu'au prochain démarrage de l'application.
    AdminSelectField.invalidateCache('/periodes-cours');
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          TabBar(
            labelColor: AuditronColors.brand700,
            indicatorColor: AuditronColors.brand700,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: const [
              Tab(
                icon: Icon(Icons.schedule_outlined, size: 20),
                text: 'Périodes de cours',
              ),
              Tab(
                icon: Icon(Icons.work_outline, size: 20),
                text: 'Emplois du temps spéciaux',
              ),
            ],
          ),
          const Expanded(
            child: TabBarView(
              children: [_PeriodesTab(), _EmploisSpeciauxTab()],
            ),
          ),
        ],
      ),
    );
  }
}

/// Grille horaire de l'établissement. Chaque période est un couple début/fin :
/// les deux listes d'heures indépendantes d'avant laissaient composer des
/// créneaux impossibles (début 15h30, fin 08h10).
class _PeriodesTab extends StatelessWidget {
  const _PeriodesTab();

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'ordre',
      label: 'Ordre',
      type: AdminFieldType.number,
      required: true,
      helperText: 'Position de la période dans la journée (1, 2, 3…).',
    ),
    const AdminFieldSpec(
      key: 'libelle',
      label: 'Libellé',
      helperText: 'Facultatif : P1, Récréation…',
    ),
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Heure de début',
      type: AdminFieldType.time,
      required: true,
    ),
    const AdminFieldSpec(
      key: 'heure_fin',
      label: 'Heure de fin',
      type: AdminFieldType.time,
      required: true,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Périodes de cours',
      resourcePath: '/periodes-cours',
      cacheKey: 'admin_periodes_cours',
      icon: Icons.schedule_outlined,
      fields: _fields,
      itemTitle: (item) =>
          item['libelle_complet']?.toString() ??
          plageAffichee(item['heure_debut'], item['heure_fin']),
      itemSubtitle: (item) => 'Période ${item['ordre']}',
      searchFields: const ['libelle'],
      searchHint: 'Rechercher une période…',
    );
  }
}

/// Un emploi du temps spécial est attribué nominativement : l'avoir suffit à
/// y être soumis, et il prime alors sur les cours comme sur l'horaire
/// administratif. La fiche d'assiduité de son titulaire ne porte plus aucune
/// période de cours à rattraper.
class _EmploisSpeciauxTab extends StatelessWidget {
  const _EmploisSpeciauxTab();

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'enseignant_id',
      label: 'Membre du personnel',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/personnel?per_page=500',
    ),
    const AdminFieldSpec(
      key: 'jour',
      label: 'Jour',
      type: AdminFieldType.select,
      required: true,
      options: _jours,
      helperText: 'Une seule plage par jour et par personne.',
    ),
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Heure de début',
      type: AdminFieldType.time,
      required: true,
    ),
    const AdminFieldSpec(
      key: 'heure_fin',
      label: 'Heure de fin',
      type: AdminFieldType.time,
      required: true,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Emplois du temps spéciaux',
      resourcePath: '/emplois-speciaux',
      cacheKey: 'admin_emplois_speciaux',
      icon: Icons.work_outline,
      fields: _fields,
      itemTitle: (item) =>
          item['enseignant']?['nom']?.toString() ??
          'Personnel #${item['enseignant_id']}',
      itemSubtitle: (item) =>
          '${_nomJour(item['jour'])} · '
          '${heureAffichee(item['heure_debut'])}–'
          '${heureAffichee(item['heure_fin'])}',
      searchHint: 'Rechercher un membre du personnel…',
    );
  }
}
