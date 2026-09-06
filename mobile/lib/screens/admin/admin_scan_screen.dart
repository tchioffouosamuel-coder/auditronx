import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../models/enseignant.dart';
import '../../services/admin_api_client.dart';
import '../../services/admin_session.dart';
import '../scan_screen.dart';

/// Scan depuis le dashboard back-office (§admin-mobile), compte `User` —
/// deux usages : sa propre présence (nécessite un Enseignant lié, voir
/// `User::enseignant`) et le scan par procuration au nom d'un tiers.
class AdminScanScreen extends StatelessWidget {
  const AdminScanScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          const TabBar(tabs: [Tab(text: 'Ma présence'), Tab(text: 'Procuration')]),
          const Expanded(
            child: TabBarView(children: [_AdminSelfScanTab(), _AdminProxyScanTab()]),
          ),
        ],
      ),
    );
  }
}

class _AdminSelfScanTab extends StatelessWidget {
  const _AdminSelfScanTab();

  void _openSelfScan(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ScanScreen(
          title: 'Scanner ma présence',
          type: 'scan',
          tokenProvider: () => AdminApiClient.instance.token,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final nom = context.watch<AdminSession>().nom;

    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text('Bonjour, $nom', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: () => _openSelfScan(context),
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Scanner ma présence'),
            style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 20)),
          ),
        ],
      ),
    );
  }
}

class _AdminProxyScanTab extends StatefulWidget {
  const _AdminProxyScanTab();

  @override
  State<_AdminProxyScanTab> createState() => _AdminProxyScanTabState();
}

class _AdminProxyScanTabState extends State<_AdminProxyScanTab> {
  final _searchController = TextEditingController();
  final _motifController = TextEditingController();
  Enseignant? _selected;
  List<Enseignant> _resultats = [];
  bool _searching = false;

  Future<void> _search(String query) async {
    setState(() => _searching = true);
    try {
      final data = await AdminApiClient.instance.get('/personnel', query: {'q': query, 'per_page': '20'});
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
          tokenProvider: () => AdminApiClient.instance.token,
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
