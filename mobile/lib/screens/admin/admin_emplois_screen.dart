import 'package:flutter/material.dart';

import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';
import '../../theme.dart';

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

  static const List<AdminFieldOption> _heuresDebut = [
    AdminFieldOption('07:30', '07h30'),
    AdminFieldOption('08:10', '08h10'),
    AdminFieldOption('08:50', '08h50'),
    AdminFieldOption('09:30', '09h30'),
    AdminFieldOption('10:10', '10h10'),
    AdminFieldOption('10:20', '10h20'),
    AdminFieldOption('11:00', '11h00'),
    AdminFieldOption('11:40', '11h40'),
    AdminFieldOption('12:00', '12h00'),
    AdminFieldOption('12:40', '12h40'),
    AdminFieldOption('13:20', '13h20'),
    AdminFieldOption('13:30', '13h30'),
    AdminFieldOption('14:10', '14h10'),
    AdminFieldOption('14:50', '14h50'),
    AdminFieldOption('15:30', '15h30'),
  ];

  static const List<AdminFieldOption> _heuresFin = [
    AdminFieldOption('08:10', '08h10'),
    AdminFieldOption('08:50', '08h50'),
    AdminFieldOption('09:30', '09h30'),
    AdminFieldOption('10:10', '10h10'),
    AdminFieldOption('10:20', '10h20'),
    AdminFieldOption('11:00', '11h00'),
    AdminFieldOption('11:40', '11h40'),
    AdminFieldOption('12:00', '12h00'),
    AdminFieldOption('12:40', '12h40'),
    AdminFieldOption('13:20', '13h20'),
    AdminFieldOption('13:30', '13h30'),
    AdminFieldOption('14:10', '14h10'),
    AdminFieldOption('14:50', '14h50'),
    AdminFieldOption('15:30', '15h30'),
    AdminFieldOption('16:10', '16h10'),
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
      type: AdminFieldType.select,
      required: true,
      options: _heuresDebut,
    ),
    const AdminFieldSpec(
      key: 'heure_fin',
      label: 'Heure de fin',
      type: AdminFieldType.select,
      required: true,
      options: _heuresFin,
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
      customListBuilder: (context, items, onEdit, onDelete) =>
          _GroupedEmploisList(items: items, onEdit: onEdit, onDelete: onDelete),
    );
  }
}

class _GroupedEmploisList extends StatelessWidget {
  final List<dynamic> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _GroupedEmploisList({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final raw in items) {
      final item = Map<String, dynamic>.from(raw as Map);
      final key =
          '${item['classe_id'] ?? item['classe']?['id'] ?? item['classe']?['nom'] ?? '—'}';
      groups.putIfAbsent(key, () => []).add(item);
    }
    final entries = groups.entries.toList()
      ..sort(
        (a, b) =>
            _className(a.value.first).compareTo(_className(b.value.first)),
      );
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      itemCount: entries.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final group = entries[index].value;
        return Card(
          child: ExpansionTile(
            leading: Icon(
              Icons.school_outlined,
              color: AuditronColors.brand700,
            ),
            title: Text(_className(group.first)),
            subtitle: Text('${group.length} cours'),
            children: [
              _DaysSchedule(items: group, onEdit: onEdit, onDelete: onDelete),
            ],
          ),
        );
      },
    );
  }

  static String _className(Map<String, dynamic> item) =>
      item['classe']?['nom']?.toString() ??
      item['classe_id']?.toString() ??
      'Classe inconnue';
}

class _DaysSchedule extends StatelessWidget {
  final List<Map<String, dynamic>> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _DaysSchedule({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final byDay = <int, List<Map<String, dynamic>>>{};
    for (final item in items) {
      byDay
          .putIfAbsent((item['jour'] as num?)?.toInt() ?? 0, () => [])
          .add(item);
    }
    final today = DateTime.now().weekday;
    final days = byDay.keys.toList()
      ..sort((a, b) => _dayOrder(a, today).compareTo(_dayOrder(b, today)));
    return Column(
      children: [
        for (final day in days)
          ExpansionTile(
            key: PageStorageKey('emploi-jour-$day'),
            initiallyExpanded: day == today,
            backgroundColor: day == today
                ? AuditronColors.brand700.withValues(alpha: 0.08)
                : null,
            title: Text(
              _dayName(day),
              style: TextStyle(
                fontWeight: day == today ? FontWeight.w700 : FontWeight.w400,
                color: day == today ? AuditronColors.brand700 : null,
              ),
            ),
            subtitle: day == today ? const Text("Aujourd'hui") : null,
            children: [
              for (final item
                  in (byDay[day]!..sort(
                    (a, b) =>
                        '${a['heure_debut']}'.compareTo('${b['heure_debut']}'),
                  )))
                _CourseTile(item: item, onEdit: onEdit, onDelete: onDelete),
            ],
          ),
      ],
    );
  }

  static int _dayOrder(int day, int today) => (day - today + 7) % 7;
  static String _dayName(int day) =>
      const {
        1: 'Lundi',
        2: 'Mardi',
        3: 'Mercredi',
        4: 'Jeudi',
        5: 'Vendredi',
        6: 'Samedi',
        7: 'Dimanche',
      }[day] ??
      'Jour inconnu';
}

class _CourseTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _CourseTile({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  Future<void> _showDetails(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CourseDetailsSheet(
        item: item,
        onEdit: () async {
          Navigator.pop(context);
          await onEdit(item);
        },
        onDelete: () async {
          Navigator.pop(context);
          await onDelete(item);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final discipline =
        item['discipline']?['nom']?.toString() ??
        item['discipline_id']?.toString() ??
        'Matière';
    final teacher =
        item['enseignant']?['nom']?.toString() ?? 'Enseignant inconnu';
    return ListTile(
      onTap: () => _showDetails(context),
      leading: Icon(Icons.menu_book_outlined, color: AuditronColors.brand700),
      title: Text(discipline, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        '$teacher · ${item['heure_debut']}–${item['heure_fin']}${item['salle'] != null ? ' · ${item['salle']}' : ''}',
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Modifier',
            icon: const Icon(Icons.edit, size: 19),
            onPressed: () => onEdit(item),
          ),
          IconButton(
            tooltip: 'Supprimer',
            icon: const Icon(Icons.delete_outline, size: 19),
            onPressed: () => onDelete(item),
          ),
        ],
      ),
    );
  }
}

class _CourseDetailsSheet extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function() onEdit;
  final Future<void> Function() onDelete;

  const _CourseDetailsSheet({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final fields =
        [
              ('Classe', item['classe']?['nom'] ?? item['classe_id']),
              ('Matière', item['discipline']?['nom'] ?? item['discipline_id']),
              (
                'Enseignant',
                item['enseignant']?['nom'] ?? item['enseignant_id'],
              ),
              (
                'Jour',
                _DaysSchedule._dayName((item['jour'] as num?)?.toInt() ?? 0),
              ),
              ('Horaire', '${item['heure_debut']}–${item['heure_fin']}'),
              ('Salle', item['salle']),
              ('Type de cours', item['type_cours']),
            ]
            .where(
              (entry) => entry.$2 != null && entry.$2.toString().isNotEmpty,
            )
            .toList();
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.black26,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Détail du cours',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit),
                      label: const Text('Modifier'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: onDelete,
                      icon: const Icon(Icons.delete_outline),
                      label: const Text('Supprimer'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              ...fields.map(
                (field) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  title: Text(
                    field.$1,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AuditronColors.brand700,
                      letterSpacing: 0.3,
                    ),
                  ),
                  subtitle: Text(
                    field.$2.toString(),
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: AuditronColors.ink900,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
