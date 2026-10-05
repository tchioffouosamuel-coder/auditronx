import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/admin_api_client.dart';
import '../../services/api_client.dart';
import '../../theme.dart';
import '../../utils/date_format_utils.dart';

/// Journal d'audit (§4.2 — admin-mobile), équivalent mobile de
/// JournalAuditPage.jsx. Réservé côté API aux accréditations à accès total :
/// l'écran n'est proposé dans le menu que pour ces comptes, mais il gère quand
/// même le 403 au cas où l'accréditation changerait entre deux sessions.
class AdminJournalAuditScreen extends StatefulWidget {
  const AdminJournalAuditScreen({super.key});

  @override
  State<AdminJournalAuditScreen> createState() =>
      _AdminJournalAuditScreenState();
}

/// Libellés lisibles. Les identifiants restent techniques en base — stables et
/// filtrables — seule leur présentation est traduite ici.
const _verbes = {
  'cree': 'Création',
  'modifie': 'Modification',
  'supprime': 'Suppression',
  'supprime_definitivement': 'Suppression définitive',
  'restaure': 'Restauration',
  'refuse': 'Tentative refusée',
  'post': 'Action',
  'put': 'Action',
  'patch': 'Action',
  'delete': 'Suppression',
  'get': 'Consultation',
};

const _sujets = {
  'enseignant': 'Personnel',
  'user': 'Compte',
  'accreditation': 'Accréditation',
  'classe': 'Classe',
  'discipline': 'Discipline',
  'emploi_du_temps': 'Emploi du temps',
  'ferie': 'Jour férié',
  'signalement': 'Signalement',
  'presence': 'Présence',
  'cours_validation': 'Validation de cours',
  'device': 'Appareil',
  'access_point': "Point d'accès",
  'qr_point': 'Point QR',
  'firmware': 'Firmware',
  'parametre': 'Paramètre',
  'programme': 'Programme',
  'cahier_texte_entree': 'Cahier de texte',
  'login': 'Connexion',
  'logout': 'Déconnexion',
  'spreadsheet': 'Export / import',
  'personnel': 'Personnel',
};

String _verbeDe(String action) {
  final morceaux = action.split('.');
  return _verbes[morceaux.last] ?? morceaux.last;
}

String _libelleAction(String action) {
  final morceaux = action.split('.');
  final sujet = _sujets[morceaux.first] ?? morceaux.first;
  return '${_verbeDe(action)} — $sujet';
}

({Color fond, Color texte, IconData icone}) _styleDe(String action) {
  switch (_verbeDe(action)) {
    case 'Suppression':
    case 'Suppression définitive':
      return (
        fond: Colors.red.shade50,
        texte: Colors.red.shade800,
        icone: Icons.delete_outline,
      );
    case 'Tentative refusée':
      return (
        fond: Colors.amber.shade50,
        texte: Colors.amber.shade900,
        icone: Icons.block,
      );
    case 'Création':
    case 'Restauration':
      return (
        fond: AuditronColors.brand100,
        texte: AuditronColors.brand700,
        icone: Icons.add_circle_outline,
      );
    case 'Modification':
      return (
        fond: Colors.blue.shade50,
        texte: Colors.blue.shade800,
        icone: Icons.edit_outlined,
      );
    default:
      return (
        fond: AuditronColors.ink100,
        texte: AuditronColors.ink700,
        icone: Icons.bolt_outlined,
      );
  }
}

class _AdminJournalAuditScreenState extends State<AdminJournalAuditScreen> {
  final _rechercheController = TextEditingController();
  final _formatMois = DateFormat('yyyy-MM-dd');

  late Future<List<Map<String, dynamic>>> _future;
  DateTimeRange _periode = _moisEnCours();
  String _recherche = '';
  String? _verbeFiltre;

  static DateTimeRange _moisEnCours() {
    final maintenant = DateTime.now();
    return DateTimeRange(
      start: DateTime(maintenant.year, maintenant.month, 1),
      end: maintenant,
    );
  }

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _rechercheController.dispose();
    super.dispose();
  }

  Future<List<Map<String, dynamic>>> _load() async {
    // Pas de cache hors-ligne ici, contrairement aux autres écrans admin : un
    // journal d'audit consulté pour établir des responsabilités doit refléter
    // le serveur, pas une copie locale potentiellement périmée.
    final entrees = await AdminApiClient.instance.getAllPages(
      '/audit-logs',
      query: {
        'debut': _formatMois.format(_periode.start),
        'fin': _formatMois.format(_periode.end),
        'per_page': '200',
      },
    );

    if (entrees is! List) return const [];

    return entrees
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _choisirPeriode() async {
    final choix = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2025),
      lastDate: DateTime.now(),
      initialDateRange: _periode,
      locale: const Locale('fr'),
    );

    if (choix != null) {
      setState(() {
        _periode = choix;
        _future = _load();
      });
    }
  }

  List<Map<String, dynamic>> _filtrer(List<Map<String, dynamic>> entrees) {
    final terme = _recherche.trim().toLowerCase();

    return entrees.where((entree) {
      final action = entree['action'] as String? ?? '';

      if (_verbeFiltre != null && _verbeDe(action) != _verbeFiltre) {
        return false;
      }

      if (terme.isEmpty) return true;

      final champs = [
        entree['auteur_nom'],
        entree['auteur_email'],
        entree['auteur_accreditation'],
        entree['sujet_libelle'],
        entree['ip'],
        action,
        _libelleAction(action),
      ];

      return champs.any(
        (valeur) => (valeur?.toString().toLowerCase() ?? '').contains(terme),
      );
    }).toList();
  }

  void _ouvrirDetail(Map<String, dynamic> entree) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: AuditronColors.ink50,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (context) => _DetailEntree(entree: entree),
    );
  }

  @override
  Widget build(BuildContext context) {
    final libelleMois = DateFormat('d MMM', 'fr');

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Column(
            children: [
              TextField(
                controller: _rechercheController,
                onChanged: (valeur) => setState(() => _recherche = valeur),
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.search),
                  hintText: 'Nom, action, fiche, adresse IP…',
                  isDense: true,
                  suffixIcon: _recherche.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.close),
                          onPressed: () {
                            _rechercheController.clear();
                            setState(() => _recherche = '');
                          },
                        ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    ActionChip(
                      avatar: const Icon(Icons.date_range, size: 18),
                      label: Text(
                        '${libelleMois.format(_periode.start)} – ${libelleMois.format(_periode.end)}',
                      ),
                      onPressed: _choisirPeriode,
                    ),
                    const SizedBox(width: 8),
                    for (final verbe in const [
                      'Suppression',
                      'Modification',
                      'Création',
                      'Tentative refusée',
                    ])
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: FilterChip(
                          label: Text(verbe),
                          selected: _verbeFiltre == verbe,
                          onSelected: (actif) => setState(
                            () => _verbeFiltre = actif ? verbe : null,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<List<Map<String, dynamic>>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError) {
                  final erreur = snapshot.error;
                  final interdit =
                      erreur is ApiException && erreur.statusCode == 403;

                  return ListView(
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          children: [
                            Text(
                              interdit
                                  ? "Le journal d'audit est réservé aux accréditations à accès total."
                                  : '$erreur',
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 12),
                            if (!interdit)
                              OutlinedButton(
                                onPressed: _refresh,
                                child: const Text('Réessayer'),
                              ),
                          ],
                        ),
                      ),
                    ],
                  );
                }

                final entrees = _filtrer(snapshot.data ?? const []);

                if (entrees.isEmpty) {
                  return ListView(
                    children: const [
                      Padding(
                        padding: EdgeInsets.all(24),
                        child: Text(
                          'Aucune action sur cette période.',
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ],
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  itemCount: entrees.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final entree = entrees[i];
                    final action = entree['action'] as String? ?? '';
                    final style = _styleDe(action);

                    return Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: style.fond,
                          child: Icon(style.icone, color: style.texte),
                        ),
                        title: Text(
                          _libelleAction(action),
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: style.texte,
                          ),
                        ),
                        subtitle: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (entree['sujet_libelle'] != null)
                              Text('${entree['sujet_libelle']}'),
                            Text(
                              '${entree['auteur_nom'] ?? 'Auteur inconnu'} · '
                              '${formatDateTime(entree['created_at'] as String?)}',
                              style: const TextStyle(
                                color: AuditronColors.ink500,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                        isThreeLine: entree['sujet_libelle'] != null,
                        onTap: () => _ouvrirDetail(entree),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

class _DetailEntree extends StatelessWidget {
  final Map<String, dynamic> entree;

  const _DetailEntree({required this.entree});

  @override
  Widget build(BuildContext context) {
    final action = entree['action'] as String? ?? '';
    final changements = (entree['changements'] as Map?) ?? const {};

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _libelleAction(action),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  color: AuditronColors.ink900,
                ),
              ),
              const SizedBox(height: 14),
              _Ligne('Quand', formatDateTime(entree['created_at'] as String?)),
              _Ligne('Qui', '${entree['auteur_nom'] ?? 'Inconnu'}'),
              if (entree['auteur_email'] != null)
                _Ligne('Email', '${entree['auteur_email']}'),
              if (entree['auteur_accreditation'] != null)
                _Ligne('Accréditation', '${entree['auteur_accreditation']}'),
              _Ligne('Sur quoi', '${entree['sujet_libelle'] ?? '—'}'),
              _Ligne('Depuis', '${entree['ip'] ?? '—'}'),
              _Ligne(
                'Requête',
                '${entree['methode'] ?? ''} ${entree['route'] ?? ''}'
                        '${entree['statut'] != null ? ' · ${entree['statut']}' : ''}'
                    .trim(),
              ),
              _Ligne('Identifiant', action),
              if (changements.isNotEmpty) ...[
                const SizedBox(height: 14),
                const Text(
                  'Valeurs',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AuditronColors.ink900,
                  ),
                ),
                const SizedBox(height: 6),
                for (final entry in changements.entries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${entry.key}',
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            color: AuditronColors.ink700,
                          ),
                        ),
                        Text(
                          entry.value is Map
                              ? '${(entry.value as Map)['avant'] ?? '—'}  →  ${(entry.value as Map)['apres'] ?? '—'}'
                              : '${entry.value}',
                          style: const TextStyle(
                            color: AuditronColors.ink500,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  final String libelle;
  final String valeur;

  const _Ligne(this.libelle, this.valeur);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              libelle,
              style: const TextStyle(color: AuditronColors.ink500),
            ),
          ),
          Expanded(
            child: Text(
              valeur,
              style: const TextStyle(color: AuditronColors.ink900),
            ),
          ),
        ],
      ),
    );
  }
}
