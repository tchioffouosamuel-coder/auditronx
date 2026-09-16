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

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(key: 'nom', label: 'Nom', required: true),
    const AdminFieldSpec(key: 'matricule', label: 'Matricule', required: true),
    const AdminFieldSpec(key: 'email', label: 'Email'),
    const AdminFieldSpec(key: 'fonction', label: 'Fonction'),
    const AdminFieldSpec(key: 'section', label: 'Section'),
    const AdminFieldSpec(key: 'grade', label: 'Grade'),
    const AdminFieldSpec(key: 'tel', label: 'Téléphone'),
    const AdminFieldSpec(key: 'poste', label: 'Poste'),
    const AdminFieldSpec(
      key: 'password',
      label: 'Mot de passe',
      type: AdminFieldType.password,
      helperText: 'Mot de passe de connexion mobile — laisser vide pour ne pas changer',
    ),
    const AdminFieldSpec(
      key: 'est_admin',
      label: 'Accès direct sans OTP (admin)',
      type: AdminFieldType.checkbox,
    ),
  ];

  Future<void> _changePhoto(BuildContext context, Map<String, dynamic> item) async {
    final result = await FilePicker.platform.pickFiles(type: FileType.image, withData: true);
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
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _deletePhoto(BuildContext context, Map<String, dynamic> item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Supprimer cette photo ?'),
        content: Text(item['nom']?.toString() ?? ''),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Supprimer')),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await AdminApiClient.instance.delete('/personnel/${item['id']}/photo');
      await _key.currentState?.refresh();
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Future<void> _openPhotoMenu(BuildContext context, Map<String, dynamic> item) async {
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
      itemTitle: (item) => item['nom']?.toString() ?? '—',
      itemSubtitle: (item) => [item['matricule'], item['fonction'], item['section']]
          .where((v) => v != null && v.toString().isNotEmpty)
          .join(' · '),
      searchFields: const ['nom', 'matricule', 'email', 'fonction', 'section', 'grade', 'tel', 'poste'],
      searchHint: 'Rechercher un nom, matricule, email…',
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
