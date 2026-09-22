import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/admin_session.dart';
import '../../theme.dart';
import '../../widgets/sync_status_banner.dart';
import '../change_password_screen.dart';
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

  const _AdminMenuEntry({
    required this.title,
    required this.icon,
    required this.screen,
  });
}

class _AdminMenuGroup {
  final String title;
  final List<_AdminMenuEntry> entries;

  const _AdminMenuGroup({required this.title, required this.entries});
}

/// Coquille de l'espace admin (§admin-mobile) — navigation mobile par barre
/// basse pour les accès fréquents, avec une feuille groupée pour le reste.
class AdminHomeScreen extends StatefulWidget {
  const AdminHomeScreen({super.key});

  @override
  State<AdminHomeScreen> createState() => _AdminHomeScreenState();
}

class _AdminHomeScreenState extends State<AdminHomeScreen> {
  static final _groups = [
    _AdminMenuGroup(
      title: "Vue d'ensemble",
      entries: [
        _AdminMenuEntry(
          title: 'Tableau de bord',
          icon: Icons.dashboard,
          screen: const AdminDashboardScreen(),
        ),
        _AdminMenuEntry(
          title: 'Scanner',
          icon: Icons.qr_code_scanner,
          screen: const AdminScanScreen(),
        ),
      ],
    ),
    _AdminMenuGroup(
      title: 'Personnel & structure',
      entries: [
        _AdminMenuEntry(
          title: 'Personnel',
          icon: Icons.badge,
          screen: AdminPersonnelScreen(),
        ),
        _AdminMenuEntry(
          title: 'Classes',
          icon: Icons.school,
          screen: const AdminClassesScreen(),
        ),
        _AdminMenuEntry(
          title: 'Disciplines',
          icon: Icons.menu_book,
          screen: const AdminDisciplinesScreen(),
        ),
        _AdminMenuEntry(
          title: 'Emplois du temps',
          icon: Icons.schedule,
          screen: const AdminEmploisScreen(),
        ),
      ],
    ),
    _AdminMenuGroup(
      title: 'Présence',
      entries: [
        _AdminMenuEntry(
          title: 'Validation',
          icon: Icons.fact_check,
          screen: const AdminValidationScreen(),
        ),
        _AdminMenuEntry(
          title: 'Retards',
          icon: Icons.timer_outlined,
          screen: const AdminRetardsScreen(),
        ),
        _AdminMenuEntry(
          title: 'Assiduité',
          icon: Icons.insights,
          screen: const AdminAssiduiteScreen(),
        ),
        _AdminMenuEntry(
          title: 'Signalements',
          icon: Icons.flag,
          screen: const AdminSignalementsScreen(),
        ),
        _AdminMenuEntry(
          title: 'Fériés',
          icon: Icons.event_busy,
          screen: const AdminFeriesScreen(),
        ),
        _AdminMenuEntry(
          title: 'Alertes',
          icon: Icons.warning_amber,
          screen: const AdminAlertesScreen(),
        ),
      ],
    ),
    _AdminMenuGroup(
      title: 'Pédagogie',
      entries: [
        _AdminMenuEntry(
          title: 'Cahier de texte',
          icon: Icons.import_contacts,
          screen: const AdminCahierTexteScreen(),
        ),
        _AdminMenuEntry(
          title: 'Fiche de progression',
          icon: Icons.auto_stories,
          screen: const AdminFicheProgressionScreen(),
        ),
      ],
    ),
    _AdminMenuGroup(
      title: 'Administration',
      entries: [
        _AdminMenuEntry(
          title: 'Demandes d’activation',
          icon: Icons.verified,
          screen: const AdminActivationRequestsScreen(),
        ),
        _AdminMenuEntry(
          title: 'Appareils',
          icon: Icons.devices,
          screen: const AdminDevicesScreen(),
        ),
        _AdminMenuEntry(
          title: 'Bornes BLE',
          icon: Icons.bluetooth,
          screen: const AdminBornesScreen(),
        ),
        _AdminMenuEntry(
          title: 'Points QR',
          icon: Icons.qr_code,
          screen: const AdminQrPointsScreen(),
        ),
      ],
    ),
  ];

  late final List<_AdminMenuEntry> _flatEntries = [
    for (final g in _groups) ...g.entries,
  ];
  static const _primaryIndexes = [0, 1, 7, 2];

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
    setState(() => _index = index);
  }

  void _showMore() {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      backgroundColor: AuditronColors.ink50,
      builder: (context) => SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
          children: [
            Text(
              'Toutes les rubriques',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            for (final group in _groups)
              Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
                      child: Text(
                        group.title,
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: AuditronColors.brand700,
                        ),
                      ),
                    ),
                    for (final entry in group.entries)
                      ListTile(
                        leading: Icon(
                          entry.icon,
                          color: AuditronColors.brand700,
                        ),
                        title: Text(entry.title),
                        trailing: _flatEntries[_index] == entry
                            ? const Icon(
                                Icons.check,
                                color: AuditronColors.gold600,
                              )
                            : null,
                        onTap: () {
                          Navigator.pop(context);
                          _select(_flatEntries.indexOf(entry));
                        },
                      ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
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
          title: Text(
            _flatEntries[_index].title,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          leading: Padding(
            padding: const EdgeInsets.all(10),
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: AuditronColors.gold500,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Image.asset(
                'assets/logo.png',
                width: 24,
                height: 24,
                fit: BoxFit.contain,
              ),
            ),
          ),
          actions: [
            IconButton(
              onPressed: _openChangePassword,
              icon: const Icon(Icons.lock_outline),
              tooltip: 'Modifier le mot de passe',
            ),
            IconButton(
              onPressed: _logout,
              icon: const Icon(Icons.logout),
              tooltip: 'Déconnexion',
            ),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _primaryIndexes.contains(_index)
              ? _primaryIndexes.indexOf(_index)
              : 4,
          onDestinationSelected: (index) {
            if (index == 4) {
              _showMore();
            } else {
              _select(_primaryIndexes[index]);
            }
          },
          destinations: const [
            NavigationDestination(
              icon: Icon(Icons.dashboard_outlined),
              selectedIcon: Icon(Icons.dashboard),
              label: 'Accueil',
            ),
            NavigationDestination(
              icon: Icon(Icons.qr_code_scanner),
              label: 'Scanner',
            ),
            NavigationDestination(
              icon: Icon(Icons.timer_outlined),
              label: 'Retards',
            ),
            NavigationDestination(
              icon: Icon(Icons.badge_outlined),
              label: 'Personnel',
            ),
            NavigationDestination(
              icon: Icon(Icons.grid_view_rounded),
              label: 'Plus',
            ),
          ],
        ),
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
