import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/api_client.dart';
import '../services/session.dart';
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
        builder: (_) => ScanScreen(
          title: 'Scanner ma présence',
          onScanned: (qrCode, bssid) async {
            final result = await ApiClient.instance.post('/attendance/scan', {
              'qr_code': qrCode,
              'bssid': bssid,
            });
            return result as Map<String, dynamic>;
          },
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();

    final pages = [
      _ScanTab(onScan: _openSelfScan, nom: session.nom),
      const HistoriqueScreen(),
      const ProcurationScreen(),
      const NotificationsScreen(),
    ];

    final titles = ['Auditron X', 'Mon historique', 'Procuration', 'Notifications'];

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[_tab]),
      ),
      body: pages[_tab],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.qr_code_scanner), label: 'Scanner'),
          NavigationDestination(icon: Icon(Icons.history), label: 'Historique'),
          NavigationDestination(icon: Icon(Icons.badge), label: 'Procuration'),
          NavigationDestination(icon: Icon(Icons.notifications), label: 'Alertes'),
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
