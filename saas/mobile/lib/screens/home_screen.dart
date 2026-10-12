import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl/intl.dart';
import '../services/ble_service.dart';
import '../services/session.dart';
import '../widgets/auditron_app_bar.dart';
import '../widgets/sync_status_banner.dart';
import 'change_password_screen.dart';
import 'historique_screen.dart';
import 'notifications_screen.dart';
import 'procuration_screen.dart';
import 'scan_screen.dart';
import 'cours_du_jour_screen.dart';
import 'emploi_du_temps_screen.dart';
import 'teacher_day_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _tab = 0;

  Future<void> _openSelfScan() async {
    final me = context.read<Session>().me;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ScanScreen(
          title: 'Pointer ma présence',
          type: 'scan',
          teacherLabel: BleService.formatTeacherLabel(
            me?['nom'] as String?,
            me?['matricule'] as String?,
          ),
        ),
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
      TeacherDayScreen(
        onScan: _openSelfScan,
        onHistory: () => setState(() => _tab = 1),
        onCourses: () => setState(() => _tab = 2),
        onSchedule: () => _openSchedule(session.me?['id']),
      ),
      const HistoriqueScreen(),
      const CoursDuJourScreen(),
      if (isAdmin) const ProcurationScreen(),
      const NotificationsScreen(),
    ];

    final titles = [
      'Ma journée',
      'Mes présences',
      'Mes cours du jour',
      if (isAdmin) 'Procuration',
      'Notifications',
    ];

    if (_tab >= pages.length) _tab = 0;

    return Scaffold(
      appBar: buildAuditronAppBar(
        context,
        title: titles[_tab],
        subtitle: [
          if (session.nom.isNotEmpty) session.nom,
          toBeginningOfSentenceCase(
            DateFormat('EEEE d MMMM', 'fr').format(DateTime.now()),
          ),
        ].join(' · '),
        automaticallyImplyLeading: false,
        actions: [
          PopupMenuButton<String>(
            tooltip: 'Mon compte',
            onSelected: (value) {
              switch (value) {
                case 'schedule':
                  _openSchedule(session.me?['id']);
                case 'password':
                  _openChangePassword();
              }
            },
            itemBuilder: (_) => const [
              PopupMenuItem(
                value: 'schedule',
                child: ListTile(
                  leading: Icon(Icons.calendar_month_outlined),
                  title: Text('Mon emploi du temps'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
              PopupMenuItem(
                value: 'password',
                child: ListTile(
                  leading: Icon(Icons.lock_outline),
                  title: Text('Modifier le mot de passe'),
                  contentPadding: EdgeInsets.zero,
                ),
              ),
            ],
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
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Accueil',
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
