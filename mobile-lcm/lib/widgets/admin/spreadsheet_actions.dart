import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';

/// Barre "Modèle / Exporter / Importer" (§admin-mobile) — équivalent mobile
/// de `SpreadsheetActions.jsx` : modèle et export sont téléchargés puis
/// proposés au partage/à l'ouverture système (pas de lecteur intégré),
/// l'import passe par un sélecteur de fichier puis un envoi multipart.
class SpreadsheetActionsBar extends StatefulWidget {
  final String entity;
  final VoidCallback onImported;

  const SpreadsheetActionsBar({super.key, required this.entity, required this.onImported});

  @override
  State<SpreadsheetActionsBar> createState() => _SpreadsheetActionsBarState();
}

class _SpreadsheetActionsBarState extends State<SpreadsheetActionsBar> {
  bool _busy = false;

  Future<void> _download(String action, String filename) async {
    setState(() => _busy = true);
    try {
      final bytes = await AdminApiClient.instance.getBytes('/spreadsheet/${widget.entity}/$action');
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/$filename');
      await file.writeAsBytes(bytes);
      await Share.shareXFiles([XFile(file.path)]);
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _import() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['xlsx', 'xls', 'csv'],
      withData: true,
    );
    final picked = result?.files.single;
    if (picked?.bytes == null) return;

    setState(() => _busy = true);
    try {
      final response = await AdminApiClient.instance.sendMultipart(
        '/spreadsheet/${widget.entity}/import',
        fileField: 'file',
        fileBytes: picked!.bytes!,
        filename: picked.name,
      );
      final importes = response is Map ? response['importes'] : null;
      final erreurs = response is Map && response['erreurs'] is List ? (response['erreurs'] as List).length : 0;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$importes importé(s)${erreurs > 0 ? ', $erreurs erreur(s)' : ''}.')),
        );
      }
      widget.onImported();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_busy) {
      return const Padding(
        padding: EdgeInsets.symmetric(horizontal: 8),
        child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
      );
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          tooltip: 'Modèle',
          icon: const Icon(Icons.description_outlined, size: 20),
          onPressed: () => _download('template', '${widget.entity}-modele.xlsx'),
        ),
        IconButton(
          tooltip: 'Exporter',
          icon: const Icon(Icons.file_download_outlined, size: 20),
          onPressed: () => _download('export', '${widget.entity}-export.xlsx'),
        ),
        IconButton(
          tooltip: 'Importer',
          icon: const Icon(Icons.file_upload_outlined, size: 20),
          onPressed: _import,
        ),
      ],
    );
  }
}
