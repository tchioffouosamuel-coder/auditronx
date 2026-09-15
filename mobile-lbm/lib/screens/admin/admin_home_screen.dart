import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/admin_session.dart';
import '../../widgets/sync_status_banner.dart';
import '../change_password_screen.dart';
import 'admin_accreditations_screen.dart';
import 'admin_activation_requests_screen.dart';
import 'admin_alertes_screen.dart';
import 'admin_assiduite_screen.dart';
import 'admin_bornes_screen.dart';
import 'admin_cahier_texte_screen.dart';
import 'admin_classes_screen.dart';
import 'admin_dashboard_screen.dart';
import 'admin_devices_screen.dart';
import 'admin_disciplines_screen.dart';
import 'admin_emplois_screen.dart';
import 'admin_feries_screen.dart';
import 'admin_fiche_progression_screen.dart';
import 'admin_personnel_screen.dart';
import 'admin_qr_points_screen.dart';
import 'admin_retards_screen.dart';
import 'admin_scan_screen.dart';
import 'admin_signalements_screen.dart';
import 'admin_validation_screen.dart';

class _AdminMenuEntry {
  final String title;
  final IconData icon;
  final Widget screen;

  const _AdminMenuEntry({required this.title, required this.icon, required this.screen});
}

class _AdminMenuGroup {
  final String title;
  final List<_AdminMenuEntry> entries;

  const _AdminMenuGroup({required this.title, required this.entries});
}

/// Coquille de l'espace admin (§admin-mobile) — parité complète avec le
/// backoffice web : navigation par tiroir groupé (au lieu d'une barre du bas
/// à plat, qui ne passe pas à l'échelle au-delà de 5 destinations).
class AdminHomeScreen extends StatefulWidget {
  const AdminHomeScreen({super.key});

  @override
  State<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends State<AdminHomeScreen> {
  static final _groups = [
    _AdminMenuGroup(title: "Vue d'ensemble", entries: [
      _AdminMenuEntry(title: 'Tableau de bord', icon: Icons.dashboard, screen: const AdminDashboardScreen()),
      _AdminMenuEntry(title: 'Scanner', icon: Icons.qr_code_scanner, screen: const AdminScanScreen()),
    ]),
    _AdminMenuGroup(title: 'Personnel & structure', entries: [
      _AdminMenuEntry(title: 'Personnel', icon: Icons.badge, screen: AdminPersonnelScreen()),
      _AdminMenuEntry(title: 'Classes', icon: Icons.school, screen: const AdminClassesScreen()),
      _AdminMenuEntry(title: 'Disciplines', icon: Icons.menu_book, screen: const AdminDisciplinesScreen()),
      _AdminMenuEntry(title: 'Emplois du temps', icon: Icons.schedule, screen: const AdminEmploisScreen()),
      _AdminMenuEntry(title: 'Accréditations', icon: Icons.verified_user, screen: const AdminAccreditationsScreen()),
    ]),
    _AdminMenuGroup(title: 'Présence', entries: [
      _AdminMenuEntry(title: 'Validation', icon: Icons.fact_check, screen: const AdminValidationScreen()),
      _AdminMenuEntry(title: 'Retards', icon: Icons.timer_outlined, screen: const AdminRetardsScreen()),
      _AdminMenuEntry(title: 'Assiduité', icon: Icons.insights, screen: const AdminAssiduiteScreen()),
      _AdminMenuEntry(title: 'Signalements', icon: Icons.flag, screen: const AdminSignalementsScreen()),
      _AdminMenuEntry(title: 'Fériés', icon: Icons.event_busy, screen: const AdminFeriesScreen()),
      _AdminMenuEntry(title: 'Alertes', icon: Icons.warning_amber, screen: const AdminAlertesScreen()),
    ]),
    _AdminMenuGroup(title: 'Pédagogie', entries: [
      _AdminMenuEntry(title: 'Cahier de texte', icon: Icons.import_contacts, screen: const AdminCahierTexteScreen()),
      _AdminMenuEntry(title: 'Fiche de progression', icon: Icons.auto_stories, screen: const AdminFicheProgressionScreen()),
    ]),
    _AdminMenuGroup(title: 'Administration', entries: [
      _AdminMenuEntry(title: 'Demandes d’activation', icon: Icons.verified, screen: const AdminActivationRequestsScreen()),
      _AdminMenuEntry(title: 'Appareils', icon: Icons.devices, screen: const AdminDevicesScreen()),
      _AdminMenuEntry(title: 'Bornes BLE', icon: Icons.bluetooth, screen: const AdminBornesScreen()),
      _AdminMenuEntry(title: 'Points QR', icon: Icons.qr_code, screen: const AdminQrPointsScreen()),
    ]),
  ];

  late final List<_AdminMenuEntry> _flatEntries = [for (final g in _groups) ...g.entries];

  int _index = 0;

  Future<void> _logout() async {
    await context.read<AdminSession>().logout();
  }

  void _openChangePassword() {
    final session = context.read<AdminSession>();
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ChangePasswordScreen(onSubmit: session.updatePassword),
      ),
    );
  }

  void _select(int index) {
    Navigator.pop(context);
    setState(() => _index = index);
  }

  @override
  Widget build(BuildContext context) {
    // Coquille "one page" : les destinations se pilotent par setState, pas par
    // le Navigator, donc rien à empiler pour le bouton retour système. Sans ce
    // PopScope, ce bouton fermerait directement l'app depuis n'importe quelle
    // destination au lieu de ramener au tableau de bord.
    return PopScope(
      canPop: _index == 0,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) setState(() => _index = 0);
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_flatEntries[_index].title),
          actions: [
            IconButton(onPressed: _openChangePassword, icon: const Icon(Icons.lock_outline), tooltip: 'Modifier le mot de passe'),
            IconButton(onPressed: _logout, icon: const Icon(Icons.logout), tooltip: 'Déconnexion'),
          ],
        ),
        drawer: _AdminDrawer(groups: _groups, flatEntries: _flatEntries, selectedIndex: _index, onSelect: _select),
        body: Column(
          children: [
            const SyncStatusBanner(),
            Expanded(child: _flatEntries[_index].screen),
          ],
        ),
      ),
    );
  }
}

class _AdminDrawer extends StatelessWidget {
  final List<_AdminMenuGroup> groups;
  final List<_AdminMenuEntry> flatEntries;
  final int selectedIndex;
  final ValueChanged<int> onSelect;

  const _AdminDrawer({required this.groups, required this.flatEntries, required this.selectedIndex, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    return Drawer(
      child: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            const DrawerHeader(
              child: Align(alignment: Alignment.bottomLeft, child: Text('Auditron X — Admin', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
            ),
            for (final group in groups) ...[
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                child: Text(group.title, style: Theme.of(context).textTheme.labelLarge),
              ),
              for (final entry in group.entries)
                ListTile(
                  leading: Icon(entry.icon),
                  title: Text(entry.title),
                  selected: flatEntries.indexOf(entry) == selectedIndex,
                  onTap: () => onSelect(flatEntries.indexOf(entry)),
                ),
            ],
          ],
        ),
      ),
    );
  }
}
