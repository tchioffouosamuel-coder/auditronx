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
          _EmploisTabs(items: items, onEdit: onEdit, onDelete: onDelete),
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

/// Deux entrées de lecture sur le même jeu de créneaux : par classe (vue
/// historique) et par enseignant — un administrateur cherche souvent
/// l'emploi du temps d'un enseignant précis pour corriger une séance.
class _EmploisTabs extends StatelessWidget {
  final List<dynamic> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _EmploisTabs({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          TabBar(
            labelColor: AuditronColors.brand700,
            indicatorColor: AuditronColors.brand700,
            tabs: const [
              Tab(icon: Icon(Icons.school_outlined, size: 20), text: 'Classes'),
              Tab(
                icon: Icon(Icons.person_outline, size: 20),
                text: 'Enseignants',
              ),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                _GroupedEmploisList(
                  items: items,
                  onEdit: onEdit,
                  onDelete: onDelete,
                ),
                _TeachersList(items: items, onEdit: onEdit, onDelete: onDelete),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Onglet « Enseignants » : un enseignant par ligne (nom + volume de
/// séances), avec recherche. Un appui ouvre son emploi du temps dans un
/// sheet, où chaque séance peut être modifiée ou supprimée.
class _TeachersList extends StatefulWidget {
  final List<dynamic> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _TeachersList({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  State<_TeachersList> createState() => _TeachersListState();
}

class _TeachersListState extends State<_TeachersList> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Regroupe par `enseignant_id` (et non par nom) : après une édition,
  /// l'API renvoie le créneau sans ses relations, donc certains éléments
  /// n'ont plus que l'identifiant. Le nom est alors repris d'un autre
  /// créneau du même enseignant.
  List<_TeacherGroup> _groups() {
    final byTeacher = <String, List<Map<String, dynamic>>>{};
    final names = <String, String>{};
    for (final raw in widget.items) {
      final item = Map<String, dynamic>.from(raw as Map);
      final key =
          '${item['enseignant_id'] ?? item['enseignant']?['id'] ?? item['enseignant']?['nom'] ?? '—'}';
      byTeacher.putIfAbsent(key, () => []).add(item);
      final nom = item['enseignant']?['nom']?.toString();
      if (nom != null && nom.isNotEmpty) names[key] = nom;
    }
    final groups =
        byTeacher.entries
            .map(
              (entry) => _TeacherGroup(
                name: names[entry.key] ?? 'Enseignant #${entry.key}',
                items: entry.value,
              ),
            )
            .toList()
          ..sort(
            (a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()),
          );
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return groups;
    return groups
        .where((group) => group.name.toLowerCase().contains(query))
        .toList();
  }

  Future<void> _openSchedule(_TeacherGroup group) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => _TeacherScheduleSheet(
        group: group,
        // Le sheet est fermé avant l'action : le formulaire d'édition et la
        // confirmation de suppression sont eux aussi des sheets/dialogs, et
        // la liste affichée ici serait de toute façon périmée après coup.
        onEdit: (item) async {
          Navigator.pop(sheetContext);
          await widget.onEdit(item);
        },
        onDelete: (item) async {
          Navigator.pop(sheetContext);
          await widget.onDelete(item);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final groups = _groups();
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: _searchController,
            onChanged: (value) => setState(() => _query = value),
            decoration: InputDecoration(
              hintText: 'Rechercher un enseignant…',
              isDense: true,
              prefixIcon: const Icon(Icons.search, size: 20),
              suffixIcon: _query.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _query = '');
                      },
                    ),
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
        ),
        Expanded(
          child: groups.isEmpty
              ? ListView(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        _query.trim().isEmpty
                            ? 'Aucun enseignant.'
                            : 'Aucun enseignant pour « ${_query.trim()} ».',
                      ),
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  itemCount: groups.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final group = groups[index];
                    return Card(
                      child: ListTile(
                        onTap: () => _openSchedule(group),
                        leading: CircleAvatar(
                          backgroundColor: AuditronColors.brand700.withValues(
                            alpha: 0.12,
                          ),
                          child: Text(
                            group.initials,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              color: AuditronColors.brand700,
                            ),
                          ),
                        ),
                        title: Text(
                          group.name,
                          overflow: TextOverflow.ellipsis,
                        ),
                        subtitle: Text(
                          group.summary,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: const Icon(Icons.chevron_right),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

class _TeacherGroup {
  final String name;
  final List<Map<String, dynamic>> items;

  const _TeacherGroup({required this.name, required this.items});

  String get initials {
    final parts = name
        .split(RegExp(r'[\s-]+'))
        .where((part) => part.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    return parts
        .take(2)
        .map((part) => part.substring(0, 1).toUpperCase())
        .join();
  }

  String get summary {
    final days = items
        .map((item) => (item['jour'] as num?)?.toInt() ?? 0)
        .toSet()
        .length;
    final seances = items.length == 1 ? '1 séance' : '${items.length} séances';
    return '$seances · ${days == 1 ? '1 jour' : '$days jours'}';
  }
}

/// Emploi du temps d'un enseignant, jour par jour, avec édition et
/// suppression de chaque séance.
class _TeacherScheduleSheet extends StatelessWidget {
  final _TeacherGroup group;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _TeacherScheduleSheet({
    required this.group,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final byDay = <int, List<Map<String, dynamic>>>{};
    for (final item in group.items) {
      byDay
          .putIfAbsent((item['jour'] as num?)?.toInt() ?? 0, () => [])
          .add(item);
    }
    final days = byDay.keys.toList()..sort();
    final today = DateTime.now().weekday;
    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 12),
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
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    group.name,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    group.summary,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AuditronColors.brand700,
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 16),
            Flexible(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(8, 0, 8, 16),
                children: [
                  for (final day in days) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
                      child: Text(
                        _DaysSchedule._dayName(day),
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: day == today ? AuditronColors.brand700 : null,
                        ),
                      ),
                    ),
                    for (final item
                        in (byDay[day]!..sort(
                          (a, b) => '${a['heure_debut']}'.compareTo(
                            '${b['heure_debut']}',
                          ),
                        )))
                      _TeacherCourseTile(
                        item: item,
                        onEdit: onEdit,
                        onDelete: onDelete,
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TeacherCourseTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _TeacherCourseTile({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final classe =
        item['classe']?['nom']?.toString() ??
        item['classe_id']?.toString() ??
        'Classe';
    final discipline =
        item['discipline']?['nom']?.toString() ??
        item['discipline_id']?.toString() ??
        'Matière';
    return ListTile(
      leading: Icon(Icons.access_time, color: AuditronColors.brand700),
      title: Text(
        '${item['heure_debut']}–${item['heure_fin']} · $classe',
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        '$discipline${item['salle'] != null ? ' · ${item['salle']}' : ''}'
        '${item['type_cours'] != null ? ' · ${item['type_cours']}' : ''}',
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
