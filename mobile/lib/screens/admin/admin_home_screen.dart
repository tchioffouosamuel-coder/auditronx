import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/admin_session.dart';
import '../../widgets/sync_status_banner.dart';
import 'admin_activation_requests_screen.dart';
import 'admin_alertes_screen.dart';
import 'admin_dashboard_screen.dart';
import 'admin_devices_screen.dart';
import 'admin_scan_screen.dart';
import 'admin_validation_screen.dart';

/// Coquille de l'espace admin (§admin-mobile) : périmètre v1 = supervision et
/// actions rapides. Les tâches lourdes (emplois du temps, import/export Excel,
/// cahier de texte, fiches de progression...) restent web-only pour l'instant.
class AdminHomeScreen extends StatefulWidget {
  const AdminHomeScreen({super.key});

  @override
  State<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends State<AdminHomeScreen> {
  int _tab = 0;

  static const _pages = [
    AdminDashboardScreen(),
    AdminValidationScreen(),
    AdminAlertesScreen(),
    AdminActivationRequestsScreen(),
    AdminDevicesScreen(),
    AdminScanScreen(),
  ];

  static const _titles = ['Tableau de bord', 'Validation', 'Alertes', 'Activations', 'Appareils', 'Scanner'];

  Future<void> _logout() async {
    await context.read<AdminSession>().logout();
  }

  @override
  Widget build(BuildContext context) {
    // Coquille "one page" (§admin-mobile) : les onglets se pilotent par
    // setState, pas par le Navigator, donc rien à empiler pour le bouton
    // retour système. Sans ce PopScope, ce bouton fermerait directement l'app
    // depuis n'importe quel onglet au lieu de ramener au tableau de bord.
    return PopScope(
      canPop: _tab == 0,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) setState(() => _tab = 0);
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_titles[_tab]),
          actions: [
            IconButton(onPressed: _logout, icon: const Icon(Icons.logout), tooltip: 'Déconnexion'),
          ],
        ),
        body: Column(
          children: [
            const SyncStatusBanner(),
            Expanded(child: _pages[_tab]),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _tab,
          onDestinationSelected: (i) => setState(() => _tab = i),
          labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
          destinations: const [
            NavigationDestination(icon: Icon(Icons.dashboard), label: 'Dashboard'),
            NavigationDestination(icon: Icon(Icons.fact_check), label: 'Validation'),
            NavigationDestination(icon: Icon(Icons.warning_amber), label: 'Alertes'),
            NavigationDestination(icon: Icon(Icons.verified_user), label: 'Activations'),
            NavigationDestination(icon: Icon(Icons.devices), label: 'Appareils'),
            NavigationDestination(icon: Icon(Icons.qr_code_scanner), label: 'Scanner'),
          ],
        ),
      ),
    );
  }
}
