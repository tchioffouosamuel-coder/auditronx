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
    show finApresPeriodes, heureAffichee, nombrePeriodesEntre, plageAffichee;

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
/// - les **périodes de cours**, seule source des listes déroulantes d'horaire
///   de l'emploi du temps ;
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
    super.dispose();
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
              Tab(
                icon: Icon(Icons.schedule_outlined, size: 20),
                text: 'Périodes de cours',
              ),
              Tab(
                icon: Icon(Icons.work_outline, size: 20),
                text: 'Emplois du temps spéciaux',
              ),
              Tab(
                icon: Icon(Icons.description_outlined, size: 20),
                text: 'En-tête de l’établissement',
              ),
            ],
          ),
          const Expanded(
            child: TabBarView(
              children: [_PeriodesTab(), _EmploisSpeciauxTab(), _EnteteTab()],
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
      helperText: 'Facultatif : P1, Récréation…',
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

class _EnteteTabState extends State<_EnteteTab> {
  /// Champs du formulaire, dans l'ordre de l'API et du document.
  static const _champsFrancais = [
    ('republique', 'République'),
    ('devise', 'Devise'),
    ('ministere', 'Ministère de tutelle'),
    ('delegation', 'Délégation'),
    ('etablissement', 'Établissement'),
  ];

  static const _champsAnglais = [
    ('republique_en', 'Republic'),
    ('devise_en', 'Motto'),
    ('ministere_en', 'Ministry'),
    ('delegation_en', 'Delegation'),
    ('etablissement_en', 'School'),
  ];

  static const _cacheKey = 'admin_entete_etablissement';
  static const _path = '/parametres/entete-etablissement';

  final _controllers = <String, TextEditingController>{};
  late Future<void> _future;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    for (final (cle, _) in [..._champsFrancais, ..._champsAnglais]) {
      _controllers[cle] = TextEditingController();
    }
    _controllers['contact'] = TextEditingController();
    _future = _load();
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
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
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() => _future = _load());
    await _future;
  }

  Map<String, dynamic> get _valeurs => {
    for (final entry in _controllers.entries) entry.key: entry.value.text.trim(),
  };

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _save() async {
    final body = _valeurs;
    if ((body['etablissement'] as String).isEmpty &&
        (body['etablissement_en'] as String).isEmpty) {
      _showMessage(
        'Renseignez au moins le nom de l’établissement, en français ou en anglais.',
      );
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
                'PDF ou en Word. Une ligne laissée vide n’est pas imprimée.',
                style: TextStyle(color: AuditronColors.ink500),
              ),
              const SizedBox(height: 16),
              _Apercu(valeurs: _valeurs),
              const SizedBox(height: 20),
              const _TitreBloc('Colonne française'),
              for (final (cle, libelle) in _champsFrancais)
                _champ(cle, libelle),
              const SizedBox(height: 12),
              const _TitreBloc('Colonne anglaise'),
              for (final (cle, libelle) in _champsAnglais) _champ(cle, libelle),
              const SizedBox(height: 12),
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
  const _Apercu({required this.valeurs});

  final Map<String, dynamic> valeurs;

  List<String> _colonne(List<String> cles) => cles
      .map((cle) => '${valeurs[cle] ?? ''}'.trim())
      .where((ligne) => ligne.isNotEmpty)
      .toList();

  @override
  Widget build(BuildContext context) {
    final gauche = _colonne(const [
      'republique',
      'devise',
      'ministere',
      'delegation',
      'etablissement',
    ]);
    final droite = _colonne(const [
      'republique_en',
      'devise_en',
      'ministere_en',
      'delegation_en',
      'etablissement_en',
    ]);
    final contact = '${valeurs['contact'] ?? ''}'.trim();

    if (gauche.isEmpty && droite.isEmpty) {
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
