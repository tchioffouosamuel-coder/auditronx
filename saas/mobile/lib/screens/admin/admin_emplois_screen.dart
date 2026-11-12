import 'package:flutter/material.dart';

import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/spreadsheet_actions.dart';
import '../../theme.dart';

/// « 08:10:00 » (colonne SQL `time`) -> « 08:10 » attendu par l'API.
String heureMinute(dynamic valeur) {
  final texte = valeur?.toString() ?? '';
  return texte.length >= 5 ? texte.substring(0, 5) : texte;
}

/// « 08:10:00 » -> « 08h10 », pour l'affichage.
String heureAffichee(dynamic valeur) =>
    heureMinute(valeur).replaceFirst(':', 'h');

String plageAffichee(dynamic debut, dynamic fin) =>
    '${heureAffichee(debut)}–${heureAffichee(fin)}';

/// Durée d'une période, en minutes, tant que la grille de l'établissement
/// n'a pas été lue : même convention que le calcul des périodes à rattraper
/// côté API (GrilleHoraire, BilanIndividuel) et que `grilleHoraire.js` côté
/// web. La valeur réelle se configure dans Configuration et arrive par
/// `GET /parametres/grille-horaire` (voir [GrillePauses.dureePeriode]).
const int dureePeriodeParDefaut = 40;

/// Durée d'une période de cet établissement, en minutes.
int get dureePeriodeMinutes => GrillePauses.dureePeriode;

/// « 08:10 » -> 490 minutes depuis minuit ; `null` si l'heure n'en est pas
/// une (champ vide, valeur abîmée) — une heure illisible prise pour minuit
/// transformerait une pause en plage géante.
int? _enMinutesOuNull(dynamic heure) {
  final parts = heureMinute(heure).split(':');
  if (parts.length < 2) return null;
  final h = int.tryParse(parts[0]);
  final m = int.tryParse(parts[1]);
  if (h == null || m == null || h > 23 || m > 59) return null;
  return h * 60 + m;
}

int _enMinutes(dynamic heure) => _enMinutesOuNull(heure) ?? 0;

/// 490 -> « 08:10 ».
String _enHeure(int minutes) {
  final h = (minutes ~/ 60) % 24;
  final m = minutes % 60;
  return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}';
}

/// Pauses de la journée (récréation, pause de midi), telles que
/// l'établissement les a saisies dans Configuration. Une pause n'est pas une
/// période : elle allonge un créneau sans le faire compter davantage, et
/// c'est le serveur qui applique la même règle aux périodes à rattraper
/// (`App\Services\GrilleHoraire`).
///
/// La grille est chargée une fois par session (et relue du cache hors ligne)
/// : les calculs de formulaire sont synchrones, ils ne peuvent pas attendre
/// une requête à chaque frappe.
class GrillePauses {
  GrillePauses._();

  /// Couples [début, fin] en minutes depuis minuit, triés.
  static List<List<int>> _plages = const [];

  static List<List<int>> get plages => _plages;

  /// Durée d'une période telle que l'établissement l'a configurée.
  static int _dureePeriode = dureePeriodeParDefaut;

  static int get dureePeriode => _dureePeriode;

  /// Une durée nulle ou négative diviserait par zéro et ferait boucler le
  /// calcul d'une heure de fin : on ignore ce que le serveur enverrait de tel.
  static void definirDureePeriode(dynamic minutes) {
    final valeur = minutes is num
        ? minutes.toInt()
        : int.tryParse(minutes?.toString() ?? '');
    if (valeur != null && valeur > 0) _dureePeriode = valeur;
  }

  static void definir(List<dynamic> items) {
    final plages = <List<int>>[];
    for (final raw in items) {
      if (raw is! Map) continue;
      final debut = _enMinutesOuNull(raw['heure_debut']);
      final fin = _enMinutesOuNull(raw['heure_fin']);
      if (debut != null && fin != null && fin > debut) plages.add([debut, fin]);
    }
    plages.sort((a, b) => a.first.compareTo(b.first));
    _plages = plages;
  }

  /// Relit la grille des pauses : au cache hors ligne d'abord, au serveur
  /// ensuite. Un échec laisse la liste en l'état — un formulaire sans pause
  /// connue reste utilisable, il calcule seulement sans les enjamber.
  static Future<void> charger() async {
    try {
      final data = await OfflineCache.instance.readThrough(
        cacheKey,
        () => AdminApiClient.instance.getAllPages(endpoint),
      );
      if (data is Map && data['data'] is List) {
        definir(data['data'] as List);
      } else if (data is List) {
        definir(data);
      }
    } catch (_) {
      // Hors ligne et sans cache : on garde la dernière grille connue.
    }

    try {
      final grille = await OfflineCache.instance.readThrough(
        cacheKeyDuree,
        () => AdminApiClient.instance.get(endpointDuree),
      );
      if (grille is Map) definirDureePeriode(grille['duree_periode_minutes']);
    } catch (_) {
      // Idem : la dernière durée connue (ou celle par défaut) fait l'affaire.
    }
  }

  static const String endpoint = '/periodes-cours?type=pause';
  static const String cacheKey = 'admin_pauses';
  static const String endpointDuree = '/parametres/grille-horaire';
  static const String cacheKeyDuree = 'admin_duree_periode';

  /// Après une modification des pauses (Configuration) : [charger] passe par
  /// le réseau en premier et réécrit le cache.
  static Future<void> rafraichir() => charger();
}

/// Minutes d'une plage, pauses déduites.
int minutesHorsPause(dynamic heureDebut, dynamic heureFin) {
  final debut = _enMinutes(heureDebut);
  final fin = _enMinutes(heureFin);
  if (fin <= debut) return 0;

  var pause = 0;
  for (final plage in GrillePauses.plages) {
    final chevauchement =
        (fin < plage[1] ? fin : plage[1]) -
        (debut > plage[0] ? debut : plage[0]);
    if (chevauchement > 0) pause += chevauchement;
  }
  final reste = fin - debut - pause;
  return reste < 0 ? 0 : reste;
}

/// Heure de fin à partir d'une heure de début (« HH:MM ») et d'un nombre de
/// périodes : évite de saisir une fin incohérente avec le début. Les pauses
/// rencontrées en chemin sont enjambées — elles repoussent la fin sans
/// compter pour une période.
String finApresPeriodes(String? heureDebut, String? nombrePeriodes) {
  final parts = heureMinute(heureDebut).split(':');
  final nb = int.tryParse(nombrePeriodes ?? '');
  if (parts.length < 2 || nb == null || nb < 1) return '';
  if (int.tryParse(parts[0]) == null || int.tryParse(parts[1]) == null) {
    return '';
  }

  var courant = _enMinutes(heureDebut);
  var restant = nb * dureePeriodeMinutes;
  final pauses = GrillePauses.plages;

  // Une pause par tour au plus : la boucle ne peut pas s'emballer sur une
  // grille mal saisie.
  for (var tour = 0; tour <= pauses.length; tour++) {
    List<int>? prochaine;
    for (final plage in pauses) {
      if (plage[1] > courant &&
          plage[0] >= courant &&
          plage[0] < courant + restant) {
        prochaine = plage;
        break;
      }
      // Plage commencée pendant une pause : elle reprend à la fin de celle-ci.
      if (plage[0] <= courant && plage[1] > courant) {
        prochaine = [courant, plage[1]];
        break;
      }
    }
    if (prochaine == null) break;
    restant -= prochaine[0] - courant;
    courant = prochaine[1];
  }

  return _enHeure(courant + (restant < 0 ? 0 : restant));
}

/// Nombre de périodes d'une plage (minimum 1), pauses déduites — pour
/// pré-remplir le formulaire en édition.
String nombrePeriodesEntre(dynamic heureDebut, dynamic heureFin) {
  final minutes = minutesHorsPause(heureDebut, heureFin);
  if (minutes <= 0) return '1';
  final nb = (minutes / dureePeriodeMinutes).ceil();
  return (nb < 1 ? 1 : nb).toString();
}

/// Gestion des emplois du temps (§4.2 — admin-mobile) — équivalent mobile de
/// `EmploisPage.jsx`. Trois lectures du même jeu de créneaux : par classe, par
/// enseignant, et les chevauchements restants.
///
/// Le serveur rejette à la saisie les créneaux qui se chevauchent pour un même
/// enseignant/classe un même jour (422 sur `heure_debut`, message nommant la
/// séance en cause, affiché par [AdminCrudScreen] via un SnackBar). L'onglet
/// « Chevauchements » montre ceux qui sont déjà en base — un import Excel ou
/// des données antérieures à ce contrôle en laissent passer.
class AdminEmploisScreen extends StatefulWidget {
  const AdminEmploisScreen({super.key});

  @override
  State<AdminEmploisScreen> createState() => _AdminEmploisScreenState();
}

class _AdminEmploisScreenState extends State<AdminEmploisScreen> {
  @override
  void initState() {
    super.initState();
    // La grille des pauses décide de l'heure de fin déduite d'un nombre de
    // périodes : on la relit à l'ouverture de l'écran, pour que le
    // formulaire suive une reconfiguration faite entre-temps.
    GrillePauses.charger();
  }

  static const List<AdminFieldOption> _jours = [
    AdminFieldOption(1, 'Lundi'),
    AdminFieldOption(2, 'Mardi'),
    AdminFieldOption(3, 'Mercredi'),
    AdminFieldOption(4, 'Jeudi'),
    AdminFieldOption(5, 'Vendredi'),
    AdminFieldOption(6, 'Samedi'),
    AdminFieldOption(7, 'Dimanche'),
  ];

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'enseignant_id',
      label: 'Enseignant',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/personnel?per_page=500',
    ),
    const AdminFieldSpec(
      key: 'classe_id',
      label: 'Classe',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/classes',
    ),
    const AdminFieldSpec(
      key: 'discipline_id',
      label: 'Discipline',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/disciplines',
    ),
    const AdminFieldSpec(
      key: 'jour',
      label: 'Jour',
      type: AdminFieldType.select,
      required: true,
      options: _jours,
    ),
    // Un créneau se saisit comme il se pense : à quelle heure il commence
    // et combien de périodes il dure. L'heure de fin s'en déduit — la
    // choisir à part laissait composer des créneaux impossibles (début
    // 15h30, fin 08h10) — et les pauses de la journée sont enjambées : une
    // récréation repousse la fin sans compter pour une période.
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Heure de début',
      type: AdminFieldType.time,
      required: true,
      helperText: 'Début de la première période du créneau.',
    ),
    AdminFieldSpec(
      key: 'nombre_periodes',
      label: 'Nombre de périodes',
      type: AdminFieldType.number,
      required: true,
      defaultValue: 1,
      helperText:
          'La durée d’une période est celle configurée pour l’établissement ; '
          'les pauses ne comptent pas comme des périodes.',
      valueFrom: (item) =>
          nombrePeriodesEntre(item['heure_debut'], item['heure_fin']),
    ),
    AdminFieldSpec(
      key: 'heure_fin',
      label: 'Heure de fin',
      type: AdminFieldType.time,
      required: true,
      recomputeFrom: const ['heure_debut', 'nombre_periodes'],
      compute: (valeurs) =>
          finApresPeriodes(valeurs['heure_debut'], valeurs['nombre_periodes']),
    ),
    const AdminFieldSpec(key: 'salle', label: 'Salle'),
    const AdminFieldSpec(key: 'type_cours', label: 'Type de cours'),
  ];

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Emplois du temps',
      resourcePath: '/emplois',
      cacheKey: 'admin_emplois',
      icon: Icons.schedule,
      fields: _fields,
      itemTitle: (item) =>
          '${item['classe']?['nom'] ?? item['classe_id']} — ${item['discipline']?['nom'] ?? item['discipline_id']}',
      itemSubtitle: (item) =>
          '${item['enseignant']?['nom'] ?? ''} · ${_DaysSchedule._dayName((item['jour'] as num?)?.toInt() ?? 0)}'
          ' · ${plageAffichee(item['heure_debut'], item['heure_fin'])}'
          '${item['salle'] != null ? ' · ${item['salle']}' : ''}',
      headerActions: (context, refresh) => [
        SpreadsheetActionsBar(entity: 'emplois', onImported: refresh),
      ],
      customListBuilder: (context, items, onEdit, onDelete) =>
          _EmploisTabs(items: items, onEdit: onEdit, onDelete: onDelete),
    );
  }
}

class _GroupedEmploisList extends StatelessWidget {
  final List<dynamic> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _GroupedEmploisList({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final raw in items) {
      final item = Map<String, dynamic>.from(raw as Map);
      final key =
          '${item['classe_id'] ?? item['classe']?['id'] ?? item['classe']?['nom'] ?? '—'}';
      groups.putIfAbsent(key, () => []).add(item);
    }
    final entries = groups.entries.toList()
      ..sort(
        (a, b) =>
            _className(a.value.first).compareTo(_className(b.value.first)),
      );
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      itemCount: entries.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final group = entries[index].value;
        return Card(
          child: ExpansionTile(
            leading: Icon(
              Icons.school_outlined,
              color: AuditronColors.brand700,
            ),
            title: Text(_className(group.first)),
            subtitle: Text('${group.length} cours'),
            children: [
              _DaysSchedule(items: group, onEdit: onEdit, onDelete: onDelete),
            ],
          ),
        );
      },
    );
  }

  static String _className(Map<String, dynamic> item) =>
      item['classe']?['nom']?.toString() ??
      item['classe_id']?.toString() ??
      'Classe inconnue';
}

class _DaysSchedule extends StatelessWidget {
  final List<Map<String, dynamic>> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _DaysSchedule({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final byDay = <int, List<Map<String, dynamic>>>{};
    for (final item in items) {
      byDay
          .putIfAbsent((item['jour'] as num?)?.toInt() ?? 0, () => [])
          .add(item);
    }
    final today = DateTime.now().weekday;
    final days = byDay.keys.toList()
      ..sort((a, b) => _dayOrder(a, today).compareTo(_dayOrder(b, today)));
    return Column(
      children: [
        for (final day in days)
          ExpansionTile(
            key: PageStorageKey('emploi-jour-$day'),
            initiallyExpanded: day == today,
            backgroundColor: day == today
                ? AuditronColors.brand700.withValues(alpha: 0.08)
                : null,
            title: Text(
              _dayName(day),
              style: TextStyle(
                fontWeight: day == today ? FontWeight.w700 : FontWeight.w400,
                color: day == today ? AuditronColors.brand700 : null,
              ),
            ),
            subtitle: day == today ? const Text("Aujourd'hui") : null,
            children: [
              for (final item
                  in (byDay[day]!..sort(
                    (a, b) =>
                        '${a['heure_debut']}'.compareTo('${b['heure_debut']}'),
                  )))
                _CourseTile(item: item, onEdit: onEdit, onDelete: onDelete),
            ],
          ),
      ],
    );
  }

  static int _dayOrder(int day, int today) => (day - today + 7) % 7;
  static String _dayName(int day) =>
      const {
        1: 'Lundi',
        2: 'Mardi',
        3: 'Mercredi',
        4: 'Jeudi',
        5: 'Vendredi',
        6: 'Samedi',
        7: 'Dimanche',
      }[day] ??
      'Jour inconnu';
}

class _CourseTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _CourseTile({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  Future<void> _showDetails(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CourseDetailsSheet(
        item: item,
        onEdit: () async {
          Navigator.pop(context);
          await onEdit(item);
        },
        onDelete: () async {
          Navigator.pop(context);
          await onDelete(item);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final discipline =
        item['discipline']?['nom']?.toString() ??
        item['discipline_id']?.toString() ??
        'Matière';
    final teacher =
        item['enseignant']?['nom']?.toString() ?? 'Enseignant inconnu';
    return ListTile(
      onTap: () => _showDetails(context),
      leading: Icon(Icons.menu_book_outlined, color: AuditronColors.brand700),
      title: Text(discipline, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        '$teacher · ${plageAffichee(item['heure_debut'], item['heure_fin'])}'
        '${item['salle'] != null ? ' · ${item['salle']}' : ''}',
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Modifier',
            icon: const Icon(Icons.edit, size: 19),
            onPressed: () => onEdit(item),
          ),
          IconButton(
            tooltip: 'Supprimer',
            icon: const Icon(Icons.delete_outline, size: 19),
            onPressed: () => onDelete(item),
          ),
        ],
      ),
    );
  }
}

class _CourseDetailsSheet extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function() onEdit;
  final Future<void> Function() onDelete;

  const _CourseDetailsSheet({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final fields =
        [
              ('Classe', item['classe']?['nom'] ?? item['classe_id']),
              ('Matière', item['discipline']?['nom'] ?? item['discipline_id']),
              (
                'Enseignant',
                item['enseignant']?['nom'] ?? item['enseignant_id'],
              ),
              (
                'Jour',
                _DaysSchedule._dayName((item['jour'] as num?)?.toInt() ?? 0),
              ),
              (
                'Horaire',
                plageAffichee(item['heure_debut'], item['heure_fin']),
              ),
              ('Salle', item['salle']),
              ('Type de cours', item['type_cours']),
            ]
            .where(
              (entry) => entry.$2 != null && entry.$2.toString().isNotEmpty,
            )
            .toList();
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.black26,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                'Détail du cours',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit),
                      label: const Text('Modifier'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: onDelete,
                      icon: const Icon(Icons.delete_outline),
                      label: const Text('Supprimer'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              ...fields.map(
                (field) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  title: Text(
                    field.$1,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AuditronColors.brand700,
                      letterSpacing: 0.3,
                    ),
                  ),
                  subtitle: Text(
                    field.$2.toString(),
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: AuditronColors.ink900,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Trois entrées de lecture sur le même jeu de créneaux : par classe (vue
/// historique), par enseignant — un administrateur cherche souvent l'emploi
/// du temps d'un enseignant précis pour corriger une séance — et les
/// chevauchements restants.
class _EmploisTabs extends StatefulWidget {
  final List<dynamic> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _EmploisTabs({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  State<_EmploisTabs> createState() => _EmploisTabsState();
}

class _EmploisTabsState extends State<_EmploisTabs> {
  late Future<List<Map<String, dynamic>>> _conflits;

  @override
  void initState() {
    super.initState();
    _conflits = _chargerConflits();
  }

  /// Les conflits sont recalculés par le serveur sur l'ensemble des créneaux :
  /// la liste rechargée après une édition reflète donc la résolution.
  @override
  void didUpdateWidget(covariant _EmploisTabs ancien) {
    super.didUpdateWidget(ancien);
    if (!identical(ancien.items, widget.items)) {
      setState(() => _conflits = _chargerConflits());
    }
  }

  Future<List<Map<String, dynamic>>> _chargerConflits() async {
    final data = await AdminApiClient.instance.get('/emplois/conflits');
    final liste = data is Map && data['data'] is List
        ? data['data'] as List
        : const [];
    return liste.cast<Map<String, dynamic>>();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Column(
        children: [
          TabBar(
            labelColor: AuditronColors.brand700,
            indicatorColor: AuditronColors.brand700,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: const [
              Tab(icon: Icon(Icons.school_outlined, size: 20), text: 'Classes'),
              Tab(
                icon: Icon(Icons.person_outline, size: 20),
                text: 'Enseignants',
              ),
              Tab(
                icon: Icon(Icons.warning_amber_outlined, size: 20),
                text: 'Chevauchements',
              ),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                _GroupedEmploisList(
                  items: widget.items,
                  onEdit: widget.onEdit,
                  onDelete: widget.onDelete,
                ),
                _TeachersList(
                  items: widget.items,
                  onEdit: widget.onEdit,
                  onDelete: widget.onDelete,
                ),
                _ConflitsList(
                  conflits: _conflits,
                  onEdit: widget.onEdit,
                  onDelete: widget.onDelete,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

String _libelleMotifs(List<dynamic> motifs) => motifs
    .map((motif) => motif == 'enseignant' ? 'même enseignant' : 'même classe')
    .join(' et ');

/// Désignation lisible d'une séance : ce que l'alerte doit nommer pour qu'on
/// sache quoi déplacer sans parcourir la grille à la main.
String _designation(Map<String, dynamic> creneau) {
  final discipline =
      creneau['discipline']?['nom']?.toString() ??
      'Matière #${creneau['discipline_id']}';
  final classe =
      creneau['classe']?['nom']?.toString() ??
      'Classe #${creneau['classe_id']}';
  final enseignant =
      creneau['enseignant']?['nom']?.toString() ??
      'Enseignant #${creneau['enseignant_id']}';
  return '$discipline · $classe · $enseignant';
}

/// Alertes de chevauchement : quel cours, quel enseignant, quelle classe, et
/// une action « Résoudre » qui ouvre le sheet d'arbitrage.
class _ConflitsList extends StatelessWidget {
  final Future<List<Map<String, dynamic>>> conflits;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _ConflitsList({
    required this.conflits,
    required this.onEdit,
    required this.onDelete,
  });

  Future<void> _resoudre(
    BuildContext context,
    Map<String, dynamic> conflit,
  ) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => _ResolutionSheet(
        conflit: conflit,
        // Le sheet se ferme avant l'action : le formulaire d'édition et la
        // confirmation de suppression sont eux aussi des sheets/dialogs, et la
        // liste affichée ici serait de toute façon périmée après coup.
        onEdit: (creneau) async {
          Navigator.pop(sheetContext);
          await onEdit(creneau);
        },
        onDelete: (creneau) async {
          Navigator.pop(sheetContext);
          await onDelete(creneau);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<Map<String, dynamic>>>(
      future: conflits,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return const Padding(
            padding: EdgeInsets.all(24),
            child: Text('Impossible de vérifier les chevauchements.'),
          );
        }

        final liste = snapshot.data ?? const <Map<String, dynamic>>[];

        if (liste.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                color: Colors.green.shade50,
                child: const ListTile(
                  leading: Icon(
                    Icons.check_circle_outline,
                    color: Colors.green,
                  ),
                  title: Text('Aucun chevauchement'),
                  subtitle: Text(
                    "Chaque enseignant et chaque classe n'a qu'une séance à la fois.",
                  ),
                ),
              ),
            ],
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: liste.length,
          separatorBuilder: (_, _) => const SizedBox(height: 8),
          itemBuilder: (context, index) {
            final conflit = liste[index];
            final jour = _DaysSchedule._dayName(
              (conflit['jour'] as num?)?.toInt() ?? 0,
            );
            return Card(
              color: Colors.amber.shade50,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$jour · '
                      '${plageAffichee(conflit['heure_debut'], conflit['heure_fin'])} · '
                      '${_libelleMotifs(conflit['motifs'] as List? ?? const [])}',
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 8),
                    for (final cle in const ['a', 'b'])
                      Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          '• ${_designation(conflit[cle] as Map<String, dynamic>)}'
                          ' (${plageAffichee((conflit[cle] as Map)['heure_debut'], (conflit[cle] as Map)['heure_fin'])})',
                          style: const TextStyle(fontSize: 13),
                        ),
                      ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: FilledButton.icon(
                        onPressed: () => _resoudre(context, conflit),
                        icon: const Icon(Icons.build_outlined, size: 18),
                        label: const Text('Résoudre'),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

/// Sheet de résolution : les deux séances en présence, et pour chacune
/// l'arbitrage possible — la déplacer (période, jour, salle) ou la supprimer.
class _ResolutionSheet extends StatelessWidget {
  final Map<String, dynamic> conflit;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _ResolutionSheet({
    required this.conflit,
    required this.onEdit,
    required this.onDelete,
  });

  Widget _carte(BuildContext context, String cle, String titre) {
    final creneau = conflit[cle] as Map<String, dynamic>;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              titre,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
                color: AuditronColors.brand700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              creneau['discipline']?['nom']?.toString() ?? 'Matière',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 6),
            Text(
              '${creneau['classe']?['nom'] ?? 'Classe'} · '
              '${creneau['enseignant']?['nom'] ?? 'Enseignant'}',
              style: const TextStyle(fontSize: 13),
            ),
            Text(
              '${plageAffichee(creneau['heure_debut'], creneau['heure_fin'])}'
              '${creneau['salle'] != null ? ' · ${creneau['salle']}' : ''}',
              style: const TextStyle(fontSize: 13),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => onEdit(creneau),
                    icon: const Icon(Icons.schedule, size: 18),
                    label: const Text('Déplacer'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => onDelete(creneau),
                    icon: const Icon(Icons.delete_outline, size: 18),
                    label: const Text('Supprimer'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final jour = _DaysSchedule._dayName(
      (conflit['jour'] as num?)?.toInt() ?? 0,
    );
    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.black26,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Résoudre le chevauchement',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 6),
              Text(
                '$jour de '
                '${plageAffichee(conflit['heure_debut'], conflit['heure_fin'])}, '
                'ces deux séances se superposent '
                '(${_libelleMotifs(conflit['motifs'] as List? ?? const [])}). '
                "Déplacez-en une sur une autre période, ou supprimez celle qui est en trop.",
                style: const TextStyle(fontSize: 13),
              ),
              const SizedBox(height: 14),
              _carte(context, 'a', 'SÉANCE 1'),
              _carte(context, 'b', 'SÉANCE 2'),
            ],
          ),
        ),
      ),
    );
  }
}

/// Onglet « Enseignants » : un enseignant par ligne (nom + volume de
/// séances), avec recherche. Un appui ouvre son emploi du temps dans un
/// sheet, où chaque séance peut être modifiée ou supprimée.
class _TeachersList extends StatefulWidget {
  final List<dynamic> items;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _TeachersList({
    required this.items,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  State<_TeachersList> createState() => _TeachersListState();
}

class _TeachersListState extends State<_TeachersList> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  /// Regroupe par `enseignant_id` (et non par nom) : après une édition,
  /// l'API renvoie le créneau sans ses relations, donc certains éléments
  /// n'ont plus que l'identifiant. Le nom est alors repris d'un autre
  /// créneau du même enseignant.
  List<_TeacherGroup> _groups() {
    final byTeacher = <String, List<Map<String, dynamic>>>{};
    final names = <String, String>{};
    for (final raw in widget.items) {
      final item = Map<String, dynamic>.from(raw as Map);
      final key =
          '${item['enseignant_id'] ?? item['enseignant']?['id'] ?? item['enseignant']?['nom'] ?? '—'}';
      byTeacher.putIfAbsent(key, () => []).add(item);
      final nom = item['enseignant']?['nom']?.toString();
      if (nom != null && nom.isNotEmpty) names[key] = nom;
    }
    final groups =
        byTeacher.entries
            .map(
              (entry) => _TeacherGroup(
                name: names[entry.key] ?? 'Enseignant #${entry.key}',
                items: entry.value,
              ),
            )
            .toList()
          ..sort(
            (a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()),
          );
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return groups;
    return groups
        .where((group) => group.name.toLowerCase().contains(query))
        .toList();
  }

  Future<void> _openSchedule(_TeacherGroup group) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => _TeacherScheduleSheet(
        group: group,
        // Le sheet est fermé avant l'action : le formulaire d'édition et la
        // confirmation de suppression sont eux aussi des sheets/dialogs, et
        // la liste affichée ici serait de toute façon périmée après coup.
        onEdit: (item) async {
          Navigator.pop(sheetContext);
          await widget.onEdit(item);
        },
        onDelete: (item) async {
          Navigator.pop(sheetContext);
          await widget.onDelete(item);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final groups = _groups();
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: _searchController,
            onChanged: (value) => setState(() => _query = value),
            decoration: InputDecoration(
              hintText: 'Rechercher un enseignant…',
              isDense: true,
              prefixIcon: const Icon(Icons.search, size: 20),
              suffixIcon: _query.isEmpty
                  ? null
                  : IconButton(
                      icon: const Icon(Icons.clear, size: 18),
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _query = '');
                      },
                    ),
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          ),
        ),
        Expanded(
          child: groups.isEmpty
              ? ListView(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        _query.trim().isEmpty
                            ? 'Aucun enseignant.'
                            : 'Aucun enseignant pour « ${_query.trim()} ».',
                      ),
                    ),
                  ],
                )
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  itemCount: groups.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final group = groups[index];
                    return Card(
                      child: ListTile(
                        onTap: () => _openSchedule(group),
                        leading: CircleAvatar(
                          backgroundColor: AuditronColors.brand700.withValues(
                            alpha: 0.12,
                          ),
                          child: Text(
                            group.initials,
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              color: AuditronColors.brand700,
                            ),
                          ),
                        ),
                        title: Text(
                          group.name,
                          overflow: TextOverflow.ellipsis,
                        ),
                        subtitle: Text(
                          group.summary,
                          overflow: TextOverflow.ellipsis,
                        ),
                        trailing: const Icon(Icons.chevron_right),
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

class _TeacherGroup {
  final String name;
  final List<Map<String, dynamic>> items;

  const _TeacherGroup({required this.name, required this.items});

  String get initials {
    final parts = name
        .split(RegExp(r'[\s-]+'))
        .where((part) => part.isNotEmpty)
        .toList();
    if (parts.isEmpty) return '?';
    return parts
        .take(2)
        .map((part) => part.substring(0, 1).toUpperCase())
        .join();
  }

  String get summary {
    final days = items
        .map((item) => (item['jour'] as num?)?.toInt() ?? 0)
        .toSet()
        .length;
    final seances = items.length == 1 ? '1 séance' : '${items.length} séances';
    return '$seances · ${days == 1 ? '1 jour' : '$days jours'}';
  }
}

/// Emploi du temps d'un enseignant, jour par jour, avec édition et
/// suppression de chaque séance.
class _TeacherScheduleSheet extends StatelessWidget {
  final _TeacherGroup group;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _TeacherScheduleSheet({
    required this.group,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final byDay = <int, List<Map<String, dynamic>>>{};
    for (final item in group.items) {
      byDay
          .putIfAbsent((item['jour'] as num?)?.toInt() ?? 0, () => [])
          .add(item);
    }
    final days = byDay.keys.toList()..sort();
    final today = DateTime.now().weekday;
    return SafeArea(
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 12),
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.black26,
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    group.name,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    group.summary,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AuditronColors.brand700,
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 16),
            Flexible(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(8, 0, 8, 16),
                children: [
                  for (final day in days) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
                      child: Text(
                        _DaysSchedule._dayName(day),
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: day == today ? AuditronColors.brand700 : null,
                        ),
                      ),
                    ),
                    for (final item
                        in (byDay[day]!..sort(
                          (a, b) => '${a['heure_debut']}'.compareTo(
                            '${b['heure_debut']}',
                          ),
                        )))
                      _TeacherCourseTile(
                        item: item,
                        onEdit: onEdit,
                        onDelete: onDelete,
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TeacherCourseTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onDelete;

  const _TeacherCourseTile({
    required this.item,
    required this.onEdit,
    required this.onDelete,
  });

  @override
  Widget build(BuildContext context) {
    final classe =
        item['classe']?['nom']?.toString() ??
        item['classe_id']?.toString() ??
        'Classe';
    final discipline =
        item['discipline']?['nom']?.toString() ??
        item['discipline_id']?.toString() ??
        'Matière';
    return ListTile(
      leading: Icon(Icons.access_time, color: AuditronColors.brand700),
      title: Text(
        '${plageAffichee(item['heure_debut'], item['heure_fin'])} · $classe',
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        '$discipline${item['salle'] != null ? ' · ${item['salle']}' : ''}'
        '${item['type_cours'] != null ? ' · ${item['type_cours']}' : ''}',
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            tooltip: 'Modifier',
            icon: const Icon(Icons.edit, size: 19),
            onPressed: () => onEdit(item),
          ),
          IconButton(
            tooltip: 'Supprimer',
            icon: const Icon(Icons.delete_outline, size: 19),
            onPressed: () => onDelete(item),
          ),
        ],
      ),
    );
  }
}
