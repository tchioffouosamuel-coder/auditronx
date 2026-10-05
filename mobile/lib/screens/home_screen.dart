import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/session.dart';
import '../widgets/assiduite_mensuelle_card.dart';
import '../widgets/sync_status_banner.dart';
import 'change_password_screen.dart';
import 'historique_screen.dart';
import 'notifications_screen.dart';
import 'procuration_screen.dart';
import 'scan_screen.dart';
import 'cours_du_jour_screen.dart';
import 'emploi_du_temps_screen.dart';

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
        builder: (_) =>
            const ScanScreen(title: 'Scanner ma présence', type: 'scan'),
      ),
    );
  }

  void _openChangePassword() {
    final session = context.read<Session>();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ChangePasswordScreen(onSubmit: session.updatePassword),
      ),
    );
  }

  void _openSchedule(dynamic rawId) {
    final enseignantId = rawId is num
        ? rawId.toInt()
        : int.tryParse(rawId?.toString() ?? '');
    if (enseignantId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Impossible de trouver votre profil.')),
      );
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => EmploiDuTempsScreen(enseignantId: enseignantId),
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
      const CoursDuJourScreen(),
      if (isAdmin) const ProcurationScreen(),
      const NotificationsScreen(),
    ];

    final titles = [
      'Auditron X',
      'Mon historique',
      'Mes cours du jour',
      if (isAdmin) 'Procuration',
      'Notifications',
    ];

    if (_tab >= pages.length) _tab = 0;

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[_tab]),
        actions: [
          IconButton(
            onPressed: () => _openSchedule(session.me?['id']),
            icon: const Icon(Icons.calendar_month_outlined),
            tooltip: 'Mon emploi du temps',
          ),
          IconButton(
            onPressed: _openChangePassword,
            icon: const Icon(Icons.lock_outline),
            tooltip: 'Modifier le mot de passe',
          ),
        ],
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
          const NavigationDestination(
            icon: Icon(Icons.qr_code_scanner),
            label: 'Scanner',
          ),
          const NavigationDestination(
            icon: Icon(Icons.history),
            label: 'Historique',
          ),
          const NavigationDestination(
            icon: Icon(Icons.menu_book),
            label: 'Mes cours',
          ),
          if (isAdmin)
            const NavigationDestination(
              icon: Icon(Icons.badge),
              label: 'Procuration',
            ),
          const NavigationDestination(
            icon: Icon(Icons.notifications),
            label: 'Alertes',
          ),
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
    // Liste défilante plutôt que colonne centrée : la carte d'assiduité
    // déborderait sur un petit écran en mode paysage.
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 28, 20, 28),
      children: [
        Image.asset('assets/logo.png', height: 64),
        const SizedBox(height: 16),
        Text(
          'Bonjour, $nom',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge,
        ),
        const SizedBox(height: 24),
        Center(
          child: FilledButton.icon(
            onPressed: onScan,
            icon: const Icon(Icons.qr_code_scanner),
            label: const Text('Scanner ma présence'),
            style: FilledButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 20),
            ),
          ),
        ),
        const SizedBox(height: 28),
        const AssiduiteMensuelleCard(),
      ],
    );
  }
}
