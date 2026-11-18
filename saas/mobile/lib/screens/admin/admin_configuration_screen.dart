import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../services/offline/pending_action.dart';
import '../../services/offline/pending_actions_queue.dart';
import '../../services/offline/sync_engine.dart';
import '../../theme.dart';
import '../../widgets/admin/admin_crud_screen.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/admin_select_field.dart';
import 'admin_emplois_screen.dart'
    show
        GrillePauses,
        finApresPeriodes,
        heureAffichee,
        nombrePeriodesEntre,
        plageAffichee;

const List<AdminFieldOption> _jours = [
  AdminFieldOption(1, 'Lundi'),
  AdminFieldOption(2, 'Mardi'),
  AdminFieldOption(3, 'Mercredi'),
  AdminFieldOption(4, 'Jeudi'),
  AdminFieldOption(5, 'Vendredi'),
  AdminFieldOption(6, 'Samedi'),
  AdminFieldOption(7, 'Dimanche'),
];

String _nomJour(dynamic jour) =>
    _jours
        .where((option) => option.value == (jour as num?)?.toInt())
        .map((option) => option.label)
        .firstOrNull ??
    'Jour inconnu';

/// Configuration de l'établissement (§4.2) — équivalent mobile de
/// `ConfigurationPage.jsx`. Deux réglages qui décident de ce que l'application
/// propose à la saisie et de ce qu'elle attend d'une personne :
///
/// - les **périodes de cours**, qui donnent la grille horaire ;
/// - les **pauses** de la journée (récréation, pause de midi) : elles ne sont
///   pas des périodes — elles ne se comptent pas comme des périodes à
///   rattraper et un créneau les enjambe sans les facturer ;
/// - les **emplois du temps spéciaux**, pour un personnel dont l'activité est
///   générale et ne se découpe pas en cours ;
/// - l'**en-tête officiel** repris en haut des documents exportés.
class AdminConfigurationScreen extends StatefulWidget {
  const AdminConfigurationScreen({super.key});

  @override
  State<AdminConfigurationScreen> createState() =>
      _AdminConfigurationScreenState();
}

class _AdminConfigurationScreenState extends State<AdminConfigurationScreen> {
  @override
  void dispose() {
    // Les options de sélection sont mémorisées par endpoint : sans cette
    // purge, l'écran des emplois du temps proposerait encore l'ancienne
    // grille horaire jusqu'au prochain démarrage de l'application.
    AdminSelectField.invalidateCache('/periodes-cours');
    // Les pauses décident de l'heure de fin déduite d'un nombre de périodes :
    // la grille rechargée ici est celle qu'utiliseront les formulaires
    // d'emploi du temps.
    GrillePauses.rafraichir();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 5,
      child: Column(
        children: [
          TabBar(
            labelColor: AuditronColors.brand700,
            indicatorColor: AuditronColors.brand700,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: const [
              Tab(
                icon: Icon(Icons.schedule_outlined, size: 20),
                text: 'Périodes de cours',
              ),
              Tab(
                icon: Icon(Icons.free_breakfast_outlined, size: 20),
                text: 'Pauses',
              ),
              Tab(
                icon: Icon(Icons.work_outline, size: 20),
                text: 'Emplois du temps spéciaux',
              ),
              Tab(
                icon: Icon(Icons.badge_outlined, size: 20),
                text: 'Horaires administratifs',
              ),
              Tab(
                icon: Icon(Icons.description_outlined, size: 20),
                text: 'En-tête de l’établissement',
              ),
            ],
          ),
          const Expanded(
            child: TabBarView(
              children: [
                _PeriodesTab(),
                _PausesTab(),
                _EmploisSpeciauxTab(),
                _HorairesAdministratifsTab(),
                _EnteteTab(),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Grille horaire de l'établissement. Chaque période est un couple début/fin :
/// les deux listes d'heures indépendantes d'avant laissaient composer des
/// créneaux impossibles (début 15h30, fin 08h10).
class _PeriodesTab extends StatelessWidget {
  const _PeriodesTab();

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'ordre',
      label: 'Ordre',
      type: AdminFieldType.number,
      required: true,
      helperText: 'Position de la période dans la journée (1, 2, 3…).',
    ),
    const AdminFieldSpec(
      key: 'libelle',
      label: 'Libellé',
      helperText: 'Facultatif : P1, P2… (les pauses ont leur propre onglet).',
    ),
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Heure de début',
      type: AdminFieldType.time,
      required: true,
    ),
    AdminFieldSpec(
      key: 'nombre_periodes',
      label: 'Nombre de périodes',
      type: AdminFieldType.number,
      required: true,
      defaultValue: 1,
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
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const _DureePeriodeCard(),
        const SizedBox(height: 4),
        Expanded(
          child: AdminCrudScreen(
            title: 'Périodes de cours',
            resourcePath: '/periodes-cours',
            cacheKey: 'admin_periodes_cours',
            icon: Icons.schedule_outlined,
            fields: _fields,
            itemTitle: (item) =>
                item['libelle_complet']?.toString() ??
                plageAffichee(item['heure_debut'], item['heure_fin']),
            itemSubtitle: (item) => 'Période ${item['ordre']}',
            searchFields: const ['libelle'],
            searchHint: 'Rechercher une période…',
          ),
        ),
      ],
    );
  }
}

/// Durée d'une période, en minutes (§4.2 — Configuration) — équivalent
/// mobile de `DureePeriode` (`ConfigurationPage.jsx`). Elle décide de l'heure
/// de fin déduite d'un nombre de périodes à la saisie d'un créneau (web comme
/// mobile), et du nombre de périodes d'une plage dans les bilans d'assiduité.
/// La changer ne retouche pas les créneaux déjà saisis : c'est leur décompte
/// en périodes qui suit la nouvelle valeur.
class _DureePeriodeCard extends StatefulWidget {
  const _DureePeriodeCard();

  @override
  State<_DureePeriodeCard> createState() => _DureePeriodeCardState();
}

class _DureePeriodeCardState extends State<_DureePeriodeCard> {
  final _controller = TextEditingController();
  int _min = 5;
  int _max = 240;
  bool _saving = false;
  late Future<void> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final data = await OfflineCache.instance.readThrough(
      GrillePauses.cacheKeyDuree,
      () => AdminApiClient.instance.get(GrillePauses.endpointDuree),
    );
    if (data is Map) {
      _controller.text =
          '${data['duree_periode_minutes'] ?? GrillePauses.dureePeriode}';
      _min = (data['duree_periode_min'] as num?)?.toInt() ?? _min;
      _max = (data['duree_periode_max'] as num?)?.toInt() ?? _max;
    }
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _enregistrer() async {
    final minutes = int.tryParse(_controller.text.trim());
    if (minutes == null || minutes < _min || minutes > _max) {
      _showMessage('Entrez une durée entre $_min et $_max minutes.');
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() => _saving = true);
    try {
      final data = await AdminApiClient.instance.put(GrillePauses.endpointDuree, {
        'duree_periode_minutes': minutes,
      });
      // Les formulaires d'emploi du temps déjà ouverts suivent la nouvelle
      // valeur sans attendre un redémarrage de l'application.
      GrillePauses.definirDureePeriode(minutes);
      await OfflineCache.instance.overwrite(GrillePauses.cacheKeyDuree, data);
      _showMessage('Durée d’une période enregistrée.');
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        GrillePauses.definirDureePeriode(minutes);
        await OfflineCache.instance.overwrite(GrillePauses.cacheKeyDuree, {
          'duree_periode_minutes': minutes,
          'duree_periode_min': _min,
          'duree_periode_max': _max,
        });
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          method: 'PUT',
          path: GrillePauses.endpointDuree,
          body: {'duree_periode_minutes': minutes},
          label: 'Mettre à jour la durée d’une période',
        );
        await SyncEngine.instance.notifyEnqueued();
        _showMessage('Durée enregistrée hors ligne.');
      } else {
        _showMessage(e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Padding(
            padding: EdgeInsets.all(16),
            child: Center(child: CircularProgressIndicator()),
          );
        }
        return Card(
          margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Durée d’une période',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 4),
                const Text(
                  'En minutes. Elle décide de l’heure de fin déduite d’un créneau de '
                  'son heure de début et de son nombre de périodes, et du décompte des '
                  'périodes dans les bilans d’assiduité. La changer ne modifie aucun '
                  'créneau déjà saisi.',
                  style: TextStyle(color: AuditronColors.ink500, fontSize: 12),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    SizedBox(
                      width: 90,
                      child: TextField(
                        controller: _controller,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Minutes',
                          isDense: true,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    FilledButton(
                      onPressed: _saving ? null : _enregistrer,
                      child: Text(_saving ? 'Enregistrement…' : 'Enregistrer'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

/// Pauses de la journée : récréation, pause de midi. Ce ne sont pas des
/// périodes de cours — elles ne se proposent pas à la saisie d'un créneau, un
/// créneau les enjambe sans qu'elles comptent pour une période, et une
/// absence ne les fait pas rattraper.
class _PausesTab extends StatelessWidget {
  const _PausesTab();

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'libelle',
      label: 'Libellé',
      helperText: 'Récréation, Pause de midi…',
    ),
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Début de la pause',
      type: AdminFieldType.time,
      required: true,
    ),
    const AdminFieldSpec(
      key: 'heure_fin',
      label: 'Fin de la pause',
      type: AdminFieldType.time,
      required: true,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Pauses',
      // Les écritures passent par `/periodes-cours` — seul chemin qui accepte
      // un identifiant ; `est_pause` range la plage du bon côté de la grille.
      resourcePath: '/periodes-cours',
      listPath: '/periodes-cours?type=pause',
      extraBody: const {'est_pause': true},
      cacheKey: GrillePauses.cacheKey,
      icon: Icons.free_breakfast_outlined,
      fields: _fields,
      itemTitle: (item) =>
          item['libelle']?.toString() ??
          plageAffichee(item['heure_debut'], item['heure_fin']),
      itemSubtitle: (item) =>
          'Pause · ${plageAffichee(item['heure_debut'], item['heure_fin'])}',
      searchFields: const ['libelle'],
      searchHint: 'Rechercher une pause…',
    );
  }
}

/// Un emploi du temps spécial est attribué nominativement : l'avoir suffit à
/// y être soumis, et il prime alors sur les cours comme sur l'horaire
/// administratif. La fiche d'assiduité de son titulaire ne porte plus aucune
/// période de cours à rattraper.
class _EmploisSpeciauxTab extends StatelessWidget {
  const _EmploisSpeciauxTab();

  static final List<AdminFieldSpec> _fields = [
    const AdminFieldSpec(
      key: 'enseignant_id',
      label: 'Membre du personnel',
      type: AdminFieldType.select,
      required: true,
      optionsEndpoint: '/personnel?per_page=500',
    ),
    const AdminFieldSpec(
      key: 'jour',
      label: 'Jour',
      type: AdminFieldType.select,
      required: true,
      options: _jours,
      helperText: 'Une seule plage par jour et par personne.',
    ),
    const AdminFieldSpec(
      key: 'heure_debut',
      label: 'Heure de début',
      type: AdminFieldType.time,
      required: true,
    ),
    AdminFieldSpec(
      key: 'nombre_periodes',
      label: 'Nombre de périodes',
      type: AdminFieldType.number,
      required: true,
      defaultValue: 1,
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
  ];

  @override
  Widget build(BuildContext context) {
    return AdminCrudScreen(
      title: 'Emplois du temps spéciaux',
      resourcePath: '/emplois-speciaux',
      cacheKey: 'admin_emplois_speciaux',
      icon: Icons.work_outline,
      fields: _fields,
      itemTitle: (item) =>
          item['enseignant']?['nom']?.toString() ??
          'Personnel #${item['enseignant_id']}',
      itemSubtitle: (item) =>
          '${_nomJour(item['jour'])} · '
          '${heureAffichee(item['heure_debut'])}–'
          '${heureAffichee(item['heure_fin'])}',
      searchHint: 'Rechercher un membre du personnel…',
    );
  }
}

/// Horaires du personnel administratif (§4.2 — Configuration) — équivalent
/// mobile de `HorairesAdministratifs` (`ConfigurationPage.jsx`). Le personnel
/// de la section « Administration » n'est pas évalué selon un emploi du
/// temps : il est attendu chaque jour coché, sur la plage horaire indiquée.
/// Un emploi du temps spécial, s'il existe, prime sur ces horaires.
class _HorairesAdministratifsTab extends StatefulWidget {
  const _HorairesAdministratifsTab();

  @override
  State<_HorairesAdministratifsTab> createState() =>
      _HorairesAdministratifsTabState();
}

class _HorairesAdministratifsTabState
    extends State<_HorairesAdministratifsTab> {
  static const _cacheKey = 'admin_horaires_administratifs';
  static const _path = '/parametres/horaires-administratifs';

  Set<int> _joursSelectionnes = {1, 2, 3, 4, 5};
  final _debutController = TextEditingController(text: '07:30');
  final _finController = TextEditingController(text: '15:30');
  bool _saving = false;
  late Future<void> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _debutController.dispose();
    _finController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final data = await OfflineCache.instance.readThrough(
      _cacheKey,
      () => AdminApiClient.instance.get(_path),
    );
    if (data is! Map) return;
    final jours = data['jours'];
    if (jours is List) {
      _joursSelectionnes = jours.map((j) => (j as num).toInt()).toSet();
    }
    _debutController.text = '${data['heure_debut'] ?? _debutController.text}';
    _finController.text = '${data['heure_fin'] ?? _finController.text}';
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _pickTime(TextEditingController controller) async {
    final parts = controller.text.split(':');
    final courant = parts.length >= 2
        ? TimeOfDay(
            hour: int.tryParse(parts[0]) ?? 8,
            minute: int.tryParse(parts[1]) ?? 0,
          )
        : const TimeOfDay(hour: 8, minute: 0);
    final picked = await showTimePicker(context: context, initialTime: courant);
    if (picked != null) {
      setState(() {
        controller.text =
            '${picked.hour.toString().padLeft(2, '0')}:'
            '${picked.minute.toString().padLeft(2, '0')}';
      });
    }
  }

  void _toggleJour(int jour) {
    setState(() {
      if (_joursSelectionnes.contains(jour)) {
        _joursSelectionnes.remove(jour);
      } else {
        _joursSelectionnes.add(jour);
      }
    });
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _save() async {
    if (_joursSelectionnes.isEmpty) {
      _showMessage('Cochez au moins un jour.');
      return;
    }
    final body = {
      'jours': _joursSelectionnes.toList()..sort(),
      'heure_debut': _debutController.text,
      'heure_fin': _finController.text,
    };

    FocusScope.of(context).unfocus();
    setState(() => _saving = true);
    try {
      final data = await AdminApiClient.instance.put(_path, body);
      await OfflineCache.instance.overwrite(_cacheKey, data);
      _showMessage('Horaires du personnel administratif enregistrés.');
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        await OfflineCache.instance.overwrite(_cacheKey, body);
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          method: 'PUT',
          path: _path,
          body: body,
          label: 'Mettre à jour les horaires du personnel administratif',
        );
        await SyncEngine.instance.notifyEnqueued();
        _showMessage('Horaires enregistrés hors ligne.');
      } else {
        _showMessage(e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              Text(
                'Horaires du personnel administratif',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 4),
              const Text(
                'Le personnel de la section « Administration » n’est pas évalué '
                'selon un emploi du temps : il est attendu chaque jour coché '
                'ci-dessous, sur la plage horaire indiquée. Un emploi du temps '
                'spécial, s’il existe, prime sur ces horaires.',
                style: TextStyle(color: AuditronColors.ink500),
              ),
              const SizedBox(height: 16),
              const _TitreBloc('Jours de présence'),
              Wrap(
                spacing: 8,
                runSpacing: 4,
                children: [
                  for (final option in _jours)
                    FilterChip(
                      label: Text(option.label),
                      selected: _joursSelectionnes.contains(option.value),
                      onSelected: (_) => _toggleJour(option.value as int),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              const _TitreBloc('Plage horaire'),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _debutController,
                      readOnly: true,
                      onTap: () => _pickTime(_debutController),
                      decoration: const InputDecoration(
                        labelText: 'Début de journée',
                        suffixIcon: Icon(Icons.schedule, size: 18),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: _finController,
                      readOnly: true,
                      onTap: () => _pickTime(_finController),
                      decoration: const InputDecoration(
                        labelText: 'Fin de journée',
                        suffixIcon: Icon(Icons.schedule, size: 18),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: _saving || _joursSelectionnes.isEmpty ? null : _save,
                icon: _saving
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(_saving ? 'Enregistrement…' : 'Enregistrer'),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// En-tête officiel imprimé en haut des documents produits par
/// l'application (journaux, bilans, fiches). Il était écrit en dur dans les
/// vues du serveur — le nom d'un établissement s'imprimait donc sur les
/// documents de tous les autres ; c'est désormais à l'administration de le
/// renseigner.
///
/// La mise en page reprend celle d'un en-tête administratif camerounais :
/// la tutelle française à gauche, son équivalent anglais à droite. Toute
/// ligne laissée vide disparaît du document, délégation comprise.
class _EnteteTab extends StatefulWidget {
  const _EnteteTab();

  @override
  State<_EnteteTab> createState() => _EnteteTabState();
}

/// Une ligne de l'en-tête : son texte, et la clé du champ nommé dont elle
/// vient (République, Ministère…) quand c'en est un. Les lignes ajoutées par
/// l'administration n'ont pas de clé — elles n'existent que dans la liste.
class _LigneEntete {
  _LigneEntete({required this.cle, required String texte})
    : controller = TextEditingController(text: texte);

  final String? cle;
  final TextEditingController controller;

  Map<String, dynamic> get json => {'cle': cle, 'texte': controller.text.trim()};
}

class _EnteteTabState extends State<_EnteteTab> {
  /// Intitulés des champs nommés, pour repérer une ligne dans la liste.
  static const _libellesLigne = {
    'republique': 'République',
    'devise': 'Devise',
    'ministere': 'Ministère de tutelle',
    'delegation': 'Délégation',
    'etablissement': 'Établissement',
    'republique_en': 'Republic',
    'devise_en': 'Motto',
    'ministere_en': 'Ministry',
    'delegation_en': 'Delegation',
    'etablissement_en': 'School',
  };

  static const _colonnes = [
    ('gauche', 'Colonne française'),
    ('droite', 'Colonne anglaise'),
  ];

  static const _cacheKey = 'admin_entete_etablissement';
  static const _path = '/parametres/entete-etablissement';

  final _controllers = <String, TextEditingController>{};

  /// Lignes des deux colonnes, dans l'ordre d'impression : c'est cette liste
  /// qui décide de ce qui est imprimé, et dans quel ordre.
  final _lignes = <String, List<_LigneEntete>>{'gauche': [], 'droite': []};

  late Future<void> _future;
  bool _saving = false;

  /// Logo imprimé au centre du bandeau. Il ne se saisit pas comme une ligne :
  /// il s'envoie seul (multipart) et prend effet sans attendre
  /// l'enregistrement du reste de l'en-tête.
  String? _logoUrl;
  bool _logoEnCours = false;

  @override
  void initState() {
    super.initState();
    _controllers['contact'] = TextEditingController();
    _future = _load();
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    for (final colonne in _lignes.values) {
      for (final ligne in colonne) {
        ligne.controller.dispose();
      }
    }
    super.dispose();
  }

  Future<void> _load() async {
    final data = await OfflineCache.instance.readThrough(
      _cacheKey,
      () => AdminApiClient.instance.get(_path),
    );
    if (data is! Map) return;
    for (final entry in _controllers.entries) {
      entry.value.text = '${data[entry.key] ?? ''}';
    }
    _logoUrl = data['logo_url'] as String?;

    final lignes = data['lignes'];
    for (final (colonne, _) in _colonnes) {
      for (final ligne in _lignes[colonne]!) {
        ligne.controller.dispose();
      }
      _lignes[colonne] = [
        for (final brut in (lignes is Map ? lignes[colonne] : null) as List? ?? const [])
          if (brut is Map)
            _LigneEntete(
              cle: brut['cle'] as String?,
              texte: '${brut['texte'] ?? ''}',
            ),
      ];
    }
  }

  void _ajouterLigne(String colonne) {
    setState(() => _lignes[colonne]!.add(_LigneEntete(cle: null, texte: '')));
  }

  void _retirerLigne(String colonne, int index) {
    setState(() => _lignes[colonne]!.removeAt(index).controller.dispose());
  }

  /// Déplace une ligne d'un cran : c'est l'ordre de la liste qui est imprimé.
  void _deplacerLigne(String colonne, int index, int pas) {
    final cible = index + pas;
    final liste = _lignes[colonne]!;
    if (cible < 0 || cible >= liste.length) return;
    setState(() => liste.insert(cible, liste.removeAt(index)));
  }

  Future<void> _choisirLogo() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final picked = result?.files.single;
    if (picked?.bytes == null) return;

    setState(() => _logoEnCours = true);
    try {
      final data = await AdminApiClient.instance.sendMultipart(
        '$_path/logo',
        fileField: 'logo',
        fileBytes: picked!.bytes!,
        filename: picked.name,
      );
      if (!mounted) return;
      setState(() => _logoUrl = data is Map ? data['logo_url'] as String? : null);
      _showMessage('Logo enregistré.');
      // Le cache de l'en-tête porte l'ancienne URL : sans cette relecture,
      // rouvrir l'écran hors ligne réafficherait le logo précédent.
      await _refresh();
    } on ApiException catch (e) {
      _showMessage(e.statusCode == 0
          ? 'Le logo ne peut pas être envoyé hors ligne.'
          : e.message);
    } finally {
      if (mounted) setState(() => _logoEnCours = false);
    }
  }

  Future<void> _retirerLogo() async {
    final confirme = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Retirer le logo des documents ?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Annuler'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Retirer'),
          ),
        ],
      ),
    );
    if (confirme != true) return;

    setState(() => _logoEnCours = true);
    try {
      await AdminApiClient.instance.delete('$_path/logo');
      if (!mounted) return;
      setState(() => _logoUrl = null);
      await _refresh();
    } on ApiException catch (e) {
      _showMessage(e.statusCode == 0
          ? 'Le logo ne peut pas être retiré hors ligne.'
          : e.message);
    } finally {
      if (mounted) setState(() => _logoEnCours = false);
    }
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() => _future = _load());
    await _future;
  }

  Map<String, dynamic> get _valeurs => {
    for (final entry in _controllers.entries) entry.key: entry.value.text.trim(),
    'lignes': {
      for (final (colonne, _) in _colonnes)
        colonne: [
          for (final ligne in _lignes[colonne]!)
            if (ligne.controller.text.trim().isNotEmpty) ligne.json,
        ],
    },
  };

  /// Textes d'une colonne, pour l'aperçu.
  List<String> _textes(String colonne) => [
    for (final ligne in _lignes[colonne]!)
      if (ligne.controller.text.trim().isNotEmpty) ligne.controller.text.trim(),
  ];

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _save() async {
    final body = _valeurs;
    if (_textes('gauche').isEmpty && _textes('droite').isEmpty) {
      _showMessage('Renseignez au moins une ligne d’en-tête.');
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() => _saving = true);
    try {
      final data = await AdminApiClient.instance.put(_path, body);
      await OfflineCache.instance.overwrite(_cacheKey, data);
      _showMessage('En-tête enregistré.');
    } on ApiException catch (e) {
      if (e.statusCode == 0) {
        // Hors ligne : l'en-tête part à la prochaine synchronisation, et
        // l'écran affiche dès maintenant ce qui a été saisi.
        await OfflineCache.instance.overwrite(_cacheKey, body);
        await PendingActionsQueue.instance.enqueue(
          authMode: AuthMode.admin,
          method: 'PUT',
          path: _path,
          body: body,
          label: 'Mettre à jour l’en-tête de l’établissement',
        );
        await SyncEngine.instance.notifyEnqueued();
        _showMessage('En-tête enregistré hors ligne.');
      } else {
        _showMessage(e.message);
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<void>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Erreur : ${snapshot.error}'),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
            children: [
              Text(
                'En-tête des documents',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 4),
              const Text(
                'Ces lignes coiffent les journaux, bilans et fiches exportés en '
                'PDF ou en Word. Ajoutez-en autant que nécessaire et rangez-les '
                'dans l’ordre d’impression ; une ligne vide n’est pas imprimée.',
                style: TextStyle(color: AuditronColors.ink500),
              ),
              const SizedBox(height: 16),
              _Apercu(
                gauche: _textes('gauche'),
                droite: _textes('droite'),
                contact: _controllers['contact']!.text.trim(),
                logoUrl: _logoUrl,
              ),
              const SizedBox(height: 20),
              for (final (colonne, titre) in _colonnes) ...[
                _TitreBloc(titre),
                if (_lignes[colonne]!.isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(bottom: 8),
                    child: Text(
                      'Aucune ligne : cette colonne restera vide.',
                      style: TextStyle(color: AuditronColors.ink500),
                    ),
                  ),
                for (var index = 0; index < _lignes[colonne]!.length; index++)
                  _ligneEditable(colonne, index),
                Align(
                  alignment: Alignment.centerLeft,
                  child: OutlinedButton.icon(
                    onPressed: () => _ajouterLigne(colonne),
                    icon: const Icon(Icons.add, size: 18),
                    label: const Text('Ajouter une ligne'),
                  ),
                ),
                const SizedBox(height: 16),
              ],
              const _TitreBloc('Logo'),
              const Text(
                'Imprimé au centre de l’en-tête, entre les deux colonnes. '
                'PNG, JPG ou WebP, 2 Mo au plus.',
                style: TextStyle(color: AuditronColors.ink500),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  if (_logoUrl != null) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.network(
                        _logoUrl!,
                        height: 56,
                        errorBuilder: (_, _, _) => const Icon(
                          Icons.broken_image_outlined,
                          color: AuditronColors.ink500,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                  ],
                  OutlinedButton.icon(
                    onPressed: _logoEnCours ? null : _choisirLogo,
                    icon: const Icon(Icons.image_outlined),
                    label: Text(_logoUrl == null ? 'Choisir un logo' : 'Remplacer'),
                  ),
                  if (_logoUrl != null) ...[
                    const SizedBox(width: 8),
                    TextButton(
                      onPressed: _logoEnCours ? null : _retirerLogo,
                      child: const Text('Retirer'),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 16),
              const _TitreBloc('Coordonnées'),
              _champ(
                'contact',
                'Ligne de contact',
                helperText: 'Exemple : BP 42 Ngaoundal — Tél. 699 00 00 00',
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: _saving ? null : _save,
                icon: _saving
                    ? const SizedBox(
                        height: 18,
                        width: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(_saving ? 'Enregistrement…' : 'Enregistrer'),
              ),
            ],
          ),
        );
      },
    );
  }

  /// Une ligne : son texte, et les boutons qui la déplacent ou la retirent.
  Widget _ligneEditable(String colonne, int index) {
    final ligne = _lignes[colonne]![index];
    final libelle = ligne.cle == null ? null : _libellesLigne[ligne.cle];

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: TextField(
              controller: ligne.controller,
              textCapitalization: TextCapitalization.characters,
              maxLength: 150,
              // L'aperçu se redessine à la frappe : l'administration voit la
              // ligne disparaître quand elle la vide.
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                labelText: libelle ?? 'Ligne ${index + 1}',
                counterText: '',
                isDense: true,
              ),
            ),
          ),
          IconButton(
            tooltip: 'Monter',
            onPressed: index == 0 ? null : () => _deplacerLigne(colonne, index, -1),
            icon: const Icon(Icons.arrow_upward, size: 20),
          ),
          IconButton(
            tooltip: 'Descendre',
            onPressed: index == _lignes[colonne]!.length - 1
                ? null
                : () => _deplacerLigne(colonne, index, 1),
            icon: const Icon(Icons.arrow_downward, size: 20),
          ),
          IconButton(
            tooltip: 'Retirer la ligne',
            onPressed: () => _retirerLigne(colonne, index),
            icon: const Icon(Icons.close, size: 20, color: Colors.redAccent),
          ),
        ],
      ),
    );
  }

  Widget _champ(String cle, String libelle, {String? helperText}) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextField(
      controller: _controllers[cle],
      textCapitalization: TextCapitalization.characters,
      maxLength: 150,
      // L'aperçu se redessine à la frappe : l'administration voit la ligne
      // disparaître quand elle la vide.
      onChanged: (_) => setState(() {}),
      decoration: InputDecoration(
        labelText: libelle,
        helperText: helperText,
        counterText: '',
      ),
    ),
  );
}

class _TitreBloc extends StatelessWidget {
  const _TitreBloc(this.texte);

  final String texte;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Text(
      texte.toUpperCase(),
      style: const TextStyle(
        color: AuditronColors.brand700,
        fontWeight: FontWeight.w700,
        fontSize: 12,
        letterSpacing: 0.6,
      ),
    ),
  );
}

/// Aperçu du bandeau tel qu'il sera imprimé — deux colonnes centrées, lignes
/// vides retirées. Il évite d'avoir à exporter un document pour vérifier une
/// saisie.
class _Apercu extends StatelessWidget {
  const _Apercu({
    required this.gauche,
    required this.droite,
    required this.contact,
    this.logoUrl,
  });

  final List<String> gauche;
  final List<String> droite;
  final String contact;
  final String? logoUrl;

  @override
  Widget build(BuildContext context) {
    if (gauche.isEmpty && droite.isEmpty && logoUrl == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text(
            'Aucun en-tête : les documents commenceront directement par leur titre.',
            style: TextStyle(color: AuditronColors.ink500),
          ),
        ),
      );
    }

    Widget colonne(List<String> lignes) => Expanded(
      child: Column(
        children: [
          for (final ligne in lignes)
            Text(
              ligne,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 10,
                color: AuditronColors.ink900,
              ),
            ),
        ],
      ),
    );

    return Card(
      color: AuditronColors.ink50,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                colonne(gauche),
                const SizedBox(width: 12),
                if (logoUrl != null) ...[
                  Image.network(
                    logoUrl!,
                    height: 44,
                    errorBuilder: (_, _, _) => const SizedBox.shrink(),
                  ),
                  const SizedBox(width: 12),
                ],
                colonne(droite),
              ],
            ),
            if (contact.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                contact,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 9,
                  color: AuditronColors.ink500,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
