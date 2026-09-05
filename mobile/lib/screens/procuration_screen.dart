import 'package:flutter/material.dart';
import '../models/enseignant.dart';
import '../services/api_client.dart';
import 'scan_screen.dart';

/// Mode procuration (§4.1, rôle restreint) : sélection de l'enseignant
/// concerné, motif obligatoire, puis scan du QR — envoyé à l'API avec
/// source=admin_proxy.
class ProcurationScreen extends StatefulWidget {
  const ProcurationScreen({super.key});

  @override
  State<ProcurationScreen> createState() => _ProcurationScreenState();
}

class _ProcurationScreenState extends State<ProcurationScreen> {
  final _searchController = TextEditingController();
  final _motifController = TextEditingController();
  Enseignant? _selected;
  List<Enseignant> _resultats = [];
  bool _searching = false;

  Future<void> _search(String query) async {
    setState(() => _searching = true);
    try {
      final data = await ApiClient.instance.get('/personnel', query: {'q': query, 'per_page': '20'});
      final list = (data['data'] as List<dynamic>).map((e) => Enseignant.fromJson(e as Map<String, dynamic>)).toList();
      setState(() => _resultats = list);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  void _startScan() {
    if (_selected == null || _motifController.text.trim().isEmpty) return;

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ScanScreen(
          title: 'Scan par procuration — ${_selected!.nom}',
          type: 'admin_proxy',
          enseignantId: _selected!.id,
          motif: _motifController.text.trim(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final canScan = _selected != null && _motifController.text.trim().isNotEmpty;

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Scan par procuration', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          const Text('Sélectionnez l\'enseignant concerné, puis indiquez le motif avant de scanner.'),
          const SizedBox(height: 16),
          TextField(
            controller: _searchController,
            decoration: const InputDecoration(
              labelText: 'Rechercher un enseignant',
              suffixIcon: Icon(Icons.search),
              border: OutlineInputBorder(),
            ),
            onChanged: (v) => v.length >= 2 ? _search(v) : null,
          ),
          const SizedBox(height: 8),
          if (_searching) const LinearProgressIndicator(),
          if (_selected == null)
            Expanded(
              child: ListView.builder(
                itemCount: _resultats.length,
                itemBuilder: (context, i) {
                  final e = _resultats[i];
                  return ListTile(
                    title: Text(e.nom),
                    subtitle: Text('${e.matricule}${e.section != null ? ' — ${e.section}' : ''}'),
                    onTap: () => setState(() => _selected = e),
                  );
                },
              ),
            )
          else ...[
            Card(
              child: ListTile(
                title: Text(_selected!.nom),
                subtitle: Text(_selected!.matricule),
                trailing: IconButton(icon: const Icon(Icons.close), onPressed: () => setState(() => _selected = null)),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _motifController,
              decoration: const InputDecoration(labelText: 'Motif (obligatoire)', border: OutlineInputBorder()),
              onChanged: (_) => setState(() {}),
            ),
            const Spacer(),
            FilledButton.icon(
              onPressed: canScan ? _startScan : null,
              icon: const Icon(Icons.qr_code_scanner),
              label: const Text('Scanner le QR'),
            ),
          ],
        ],
      ),
    );
  }
}
