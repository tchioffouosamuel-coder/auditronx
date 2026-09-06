import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/session.dart';
import '../widgets/sync_status_banner.dart';
import 'historique_screen.dart';
import 'notifications_screen.dart';
import 'procuration_screen.dart';
import 'scan_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;

  void _openSelfScan() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => const ScanScreen(title: 'Scanner ma présence', type: 'scan'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    // Le scan par procuration engage la présence d'un tiers en son nom : seul
    // un enseignant admin (`est_admin`, §admin-mobile) doit pouvoir y accéder,
    // pas n'importe quel enseignant.
    final isAdmin = session.me?['est_admin'] == true;

    final pages = [
      _ScanTab(onScan: _openSelfScan, nom: session.nom),
      const HistoriqueScreen(),
      if (isAdmin) const ProcurationScreen(),
      const NotificationsScreen(),
    ];

    final titles = ['Auditron X', 'Mon historique', if (isAdmin) 'Procuration', 'Notifications'];

    if (_tab >= pages.length) _tab = 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[_tab]),
      ),
      body: Column(
        children: [
          const SyncStatusBanner(),
          Expanded(child: pages[_tab]),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: [
          const NavigationDestination(icon: Icon(Icons.qr_code_scanner), label: 'Scanner'),
          const NavigationDestination(icon: Icon(Icons.history), label: 'Historique'),
          if (isAdmin) const NavigationDestination(icon: Icon(Icons.badge), label: 'Procuration'),
          const NavigationDestination(icon: Icon(Icons.notifications), label: 'Alertes'),
        ],
      ),
    );
  }
}

class _ScanTab extends StatelessWidget {
  final VoidCallback onScan;
  final String nom;

  const _ScanTab({required this.onScan, required this.nom});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Image.asset('assets/logo.png', height: 64),
          const SizedBox(height: 16),
          Text('Bonjour, $nom', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 24),
          FilledButton.icon(
            onPressed: onScan,
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Scanner ma présence'),
            style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 20)),
          ),
        ],
      ),
    );
  }
}
