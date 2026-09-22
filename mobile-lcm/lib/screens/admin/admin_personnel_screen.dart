import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../theme.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';

/// Gestion du personnel (§4.2 — admin-mobile) — équivalent mobile de
/// `PersonnelPage.jsx` : CRUD générique + gestion de la photo de référence
/// (§5 — enrôlement facial embarqué ESP-WHO), qui passe par un endpoint
/// multipart dédié (`POST/DELETE /personnel/{id}/photo`) séparé du
/// formulaire CRUD standard.
class AdminPersonnelScreen extends StatelessWidget {
  AdminPersonnelScreen({super.key});

  final _key = GlobalKey<AdminCrudScreenState>();

  static const List<AdminFieldOption> _sections = [
    AdminFieldOption('Industrielle', 'Industrielle'),
    AdminFieldOption('STT', 'STT'),
    AdminFieldOption('Générale', 'Générale'),
    AdminFieldOption('Administration', 'Administration'),
    AdminFieldOption('Anglophone', 'Anglophone'),
    AdminFieldOption('Francophone', 'Francophone'),
  ];

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(key: 'nom', label: 'Nom', required: true),
    const AdminFieldSpec(key: 'matricule', label: 'Matricule', required: true),
    const AdminFieldSpec(key: 'email', label: 'Email'),
    const AdminFieldSpec(key: 'fonction', label: 'Fonction'),
    const AdminFieldSpec(
      key: 'section',
      label: 'Section',
      type: AdminFieldType.select,
      options: _sections,
    ),
    const AdminFieldSpec(key: 'grade', label: 'Grade'),
    const AdminFieldSpec(key: 'tel', label: 'Téléphone'),
    const AdminFieldSpec(key: 'poste', label: 'Poste'),
    const AdminFieldSpec(
      key: 'password',
      label: 'Mot de passe',
      type: AdminFieldType.password,
      helperText:
          'Mot de passe de connexion mobile — laisser vide pour ne pas changer',
    ),
    const AdminFieldSpec(
      key: 'est_admin',
      label: 'Accès direct sans OTP (admin)',
      type: AdminFieldType.checkbox,
    ),
  ];

  Future<void> _openDetails(
    BuildContext context,
    Map<String, dynamic> item,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _PersonnelDetailsSheet(
        item: item,
        onEdit: () async {
          Navigator.pop(context);
          await _key.currentState?.editItem(item);
        },
        onDelete: () async {
          Navigator.pop(context);
          await _key.currentState?.deleteItem(item);
        },
      ),
    );
  }

  Future<void> _changePhoto(
    BuildContext context,
    Map<String, dynamic> item,
  ) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final picked = result?.files.single;
    if (picked?.bytes == null) return;

    try {
      await AdminApiClient.instance.sendMultipart(
        '/personnel/${item['id']}/photo',
        fileField: 'photo',
        fileBytes: picked!.bytes!,
        filename: picked.name,
      );
      await _key.currentState?.refresh();
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _deletePhoto(
    BuildContext context,
    Map<String, dynamic> item,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Supprimer cette photo ?'),
        content: Text(item['nom']?.toString() ?? ''),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Supprimer'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await AdminApiClient.instance.delete('/personnel/${item['id']}/photo');
      await _key.currentState?.refresh();
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _openPhotoMenu(
    BuildContext context,
    Map<String, dynamic> item,
  ) async {
    final hasPhoto = item['photo_url'] != null;
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Changer la photo'),
              onTap: () => Navigator.pop(context, 'change'),
            ),
            if (hasPhoto)
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Supprimer la photo'),
                onTap: () => Navigator.pop(context, 'delete'),
              ),
          ],
        ),
      ),
    );
    if (choice == 'change' && context.mounted) {
      await _changePhoto(context, item);
    } else if (choice == 'delete' && context.mounted) {
      await _deletePhoto(context, item);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      key: _key,
      title: 'Personnel',
      resourcePath: '/personnel',
      cacheKey: 'admin_personnel',
      icon: Icons.badge,
      fields: _fields,
      onItemTap: _openDetails,
      itemTitle: (item) => item['nom']?.toString() ?? '—',
      itemSubtitle: (item) => [
        item['matricule'],
        item['fonction'],
        item['section'],
      ].where((v) => v != null && v.toString().isNotEmpty).join(' · '),
      headerActions: (context, refresh) => [
        SpreadsheetActionsBar(entity: 'personnel', onImported: refresh),
      ],
      leadingBuilder: (context, item) {
        final photoUrl = item['photo_url'] as String?;
        if (photoUrl != null && photoUrl.isNotEmpty) {
          return CircleAvatar(
            backgroundColor: AuditronColors.brand700.withValues(alpha: 0.1),
            backgroundImage: NetworkImage(photoUrl),
            onBackgroundImageError: (_, _) {},
          );
        }
        return CircleAvatar(
          backgroundColor: AuditronColors.brand700.withValues(alpha: 0.1),
          child: Icon(Icons.person, color: AuditronColors.brand700),
        );
      },
      extraRowActions: (context, item) => [
        IconButton(
          icon: const Icon(Icons.photo_camera_outlined, size: 20),
          onPressed: () => _openPhotoMenu(context, item),
        ),
      ],
    );
  }
}

class _PersonnelDetailsSheet extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function() onEdit;
  final Future<void> Function() onDelete;
  const _PersonnelDetailsSheet({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  Future<Map<String, dynamic>> _loadAttendance() async {
    final data = await AdminApiClient.instance.get(
      '/personnel/${item['id']}/assiduite',
    );
    return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
  }

  Future<void> _openSchedule(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _TeacherScheduleSheet(item: item),
    );
  }

  @override
  Widget build(BuildContext context) {
    final details =
        [
              ('Matricule', item['matricule']),
              ('Email', item['email']),
              ('Fonction', item['fonction']),
              ('Section', item['section']),
              ('Grade', item['grade']),
              ('Téléphone', item['tel']),
              ('Poste', item['poste']),
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
                item['nom']?.toString() ?? 'Personnel',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 16),
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
              const SizedBox(height: 10),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: () => _openSchedule(context),
                  icon: const Icon(Icons.calendar_month_outlined),
                  label: const Text('Emploi du temps'),
                ),
              ),
              const SizedBox(height: 12),
              ...details.map(
                (entry) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  title: Text(
                    entry.$1,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AuditronColors.brand700,
                      letterSpacing: 0.3,
                    ),
                  ),
                  subtitle: Text(
                    entry.$2.toString(),
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: AuditronColors.ink900,
                    ),
                  ),
                ),
              ),
              const Divider(height: 24),
              Text(
                'Assiduité du mois',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              FutureBuilder<Map<String, dynamic>>(
                future: _loadAttendance(),
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done)
                    return const Padding(
                      padding: EdgeInsets.symmetric(vertical: 20),
                      child: Center(child: CircularProgressIndicator()),
                    );
                  if (snapshot.hasError)
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        'Taux indisponible : ${snapshot.error}',
                        style: const TextStyle(color: Colors.red),
                      ),
                    );
                  final data = snapshot.data ?? const <String, dynamic>{};
                  return ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(
                      Icons.insights,
                      color: AuditronColors.brand700,
                    ),
                    title: Text(
                      '${data['taux_assiduite'] ?? 0}%',
                      style: const TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    subtitle: Text(
                      '${data['jours_presents'] ?? 0} / ${data['jours_attendus'] ?? 0} jours attendus selon l’emploi du temps',
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TeacherScheduleSheet extends StatelessWidget {
  final Map<String, dynamic> item;
  const _TeacherScheduleSheet({required this.item});

  Future<List<Map<String, dynamic>>> _load() async {
    final data = await AdminApiClient.instance.getAllPages(
      '/emplois?enseignant_id=${item['id']}',
    );
    return (data as List? ?? const [])
        .whereType<Map>()
        .map((entry) => Map<String, dynamic>.from(entry))
        .toList();
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
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
            'Emploi du temps',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          Text(
            item['nom']?.toString() ?? 'Enseignant',
            style: const TextStyle(color: AuditronColors.ink500),
          ),
          const SizedBox(height: 12),
          Flexible(
            child: FutureBuilder<List<Map<String, dynamic>>>(
              future: _load(),
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done)
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.all(28),
                      child: CircularProgressIndicator(),
                    ),
                  );
                if (snapshot.hasError)
                  return Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      'Impossible de charger l’emploi du temps : ${snapshot.error}',
                      style: const TextStyle(color: Colors.red),
                    ),
                  );
                final byDay = <int, List<Map<String, dynamic>>>{};
                for (final course in snapshot.data ?? const []) {
                  byDay
                      .putIfAbsent(
                        (course['jour'] as num?)?.toInt() ?? 0,
                        () => [],
                      )
                      .add(course);
                }
                if (byDay.isEmpty)
                  return const Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('Aucun cours planifié.'),
                  );
                final today = DateTime.now().weekday;
                final days = byDay.keys.toList()
                  ..sort(
                    (a, b) =>
                        ((a - today + 7) % 7).compareTo((b - today + 7) % 7),
                  );
                return ListView(
                  shrinkWrap: true,
                  children: [
                    for (final day in days)
                      ExpansionTile(
                        initiallyExpanded: day == today,
                        title: Text(
                          _dayName(day),
                          style: TextStyle(
                            fontWeight: day == today
                                ? FontWeight.w700
                                : FontWeight.w400,
                            color: day == today
                                ? AuditronColors.brand700
                                : null,
                          ),
                        ),
                        subtitle: day == today
                            ? const Text("Aujourd'hui")
                            : null,
                        children: [
                          for (final course
                              in (byDay[day]!..sort(
                                (a, b) => '${a['heure_debut']}'.compareTo(
                                  '${b['heure_debut']}',
                                ),
                              )))
                            ListTile(
                              leading: Icon(
                                Icons.schedule,
                                color: AuditronColors.brand700,
                              ),
                              title: Text(
                                '${course['heure_debut']}–${course['heure_fin']}',
                              ),
                              subtitle: Text(
                                '${course['classe']?['nom'] ?? 'Classe'} · ${course['discipline']?['nom'] ?? 'Matière'}${course['salle'] != null ? ' · ${course['salle']}' : ''}',
                              ),
                            ),
                        ],
                      ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    ),
  );

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
