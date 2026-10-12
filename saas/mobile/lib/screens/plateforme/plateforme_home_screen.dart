import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../services/plateforme_session.dart';
import '../../theme.dart';
import 'plateforme_audit_screen.dart';
import 'plateforme_dashboard_screen.dart';
import 'plateforme_etablissements_screen.dart';
import 'plateforme_plans_screen.dart';
import 'plateforme_presences_screen.dart';
import 'plateforme_utilisateurs_screen.dart';

class PlateformeHomeScreen extends StatefulWidget {
  const PlateformeHomeScreen({super.key});

  @override
  State<PlateformeHomeScreen> createState() => _PlateformeHomeScreenState();
}

class _PlateformeHomeScreenState extends State<PlateformeHomeScreen> {
  int _index = 0;

  Future<void> _showMore(bool estSuperAdmin) async {
    final pages = [
      (3, 'Offres et abonnements', Icons.sell_outlined),
      if (estSuperAdmin) (4, 'Équipe Auditron', Icons.groups_outlined),
      if (estSuperAdmin) (5, 'Journal d’audit Auditron', Icons.history),
    ];
    final selected = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final page in pages)
              ListTile(
                leading: Icon(page.$3, color: AuditronColors.brand700),
                title: Text(page.$2),
                onTap: () => Navigator.pop(context, page.$1),
              ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
    if (selected != null && mounted) setState(() => _index = selected);
  }

  Future<void> _logout() async {
    await context.read<PlateformeSession>().logout();
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<PlateformeSession>();
    final pages = <Widget>[
      const PlateformeDashboardScreen(),
      PlateformeEtablissementsScreen(estSuperAdmin: session.estSuperAdmin),
      const PlateformePresencesScreen(),
      PlateformePlansScreen(estSuperAdmin: session.estSuperAdmin),
      if (session.estSuperAdmin) const PlateformeUtilisateursScreen(),
      if (session.estSuperAdmin) const PlateformeAuditScreen(),
    ];
    final titles = [
      'Portail Auditron',
      'Établissements',
      'Présences du parc',
      'Offres et abonnements',
      if (session.estSuperAdmin) 'Équipe Auditron',
      if (session.estSuperAdmin) 'Journal d’audit Auditron',
    ];
    if (_index >= pages.length) _index = 0;

    return PopScope(
      canPop: _index == 0,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) setState(() => _index = 0);
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(titles[_index]),
          actions: [
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: Center(
                child: Text(
                  session.nom,
                  style: const TextStyle(fontSize: 11, color: Colors.white70),
                ),
              ),
            ),
            IconButton(
              onPressed: _logout,
              tooltip: 'Déconnexion Auditron',
              icon: const Icon(Icons.logout),
            ),
          ],
        ),
        body: IndexedStack(index: _index, children: pages),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _index < 3 ? _index : 3,
          onDestinationSelected: (index) {
            if (index == 3) {
              _showMore(session.estSuperAdmin);
            } else {
              setState(() => _index = index);
            }
          },
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.dashboard_outlined),
              selectedIcon: Icon(Icons.dashboard),
              label: 'Accueil',
            ),
            NavigationDestination(
              icon: Icon(Icons.apartment_outlined),
              label: 'Parc',
            ),
            NavigationDestination(
              icon: Icon(Icons.fact_check_outlined),
              label: 'Présences',
            ),
            NavigationDestination(
              icon: Icon(Icons.grid_view_rounded),
              label: 'Plus',
            ),
          ],
        ),
      ),
    );
  }
}
