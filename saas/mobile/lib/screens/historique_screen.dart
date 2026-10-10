import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../models/presence.dart';
import '../services/presence_repository.dart';
import '../theme.dart';
import '../utils/date_format_utils.dart';

/// Périodes proposées en filtre rapide. « Mois précis » ouvre un sélecteur,
/// les autres se calculent par rapport à aujourd'hui.
enum _Periode { tout, cetteSemaine, semaineDerniere, ceMois, moisPrecis }

/// Historique personnel (§4.1) : consultation de ses propres présences et
/// retards, avec recherche libre et regroupement par période.
///
/// Le filtrage est local : le dépôt ramène déjà l'historique complet depuis le
/// cache hors ligne, et un enseignant a au plus quelques dizaines d'entrées par
/// mois. Un aller-retour serveur par changement de filtre casserait le mode
/// hors ligne pour rien.
class HistoriqueScreen extends StatefulWidget {
  const HistoriqueScreen({super.key});

  @override
  State<HistoriqueScreen> createState() => _HistoriqueScreenState();
}

class _HistoriqueScreenState extends State<HistoriqueScreen> {
  final _repository = PresenceRepository();
  final _rechercheController = TextEditingController();

  late Future<List<PresenceEntry>> _future;
  _Periode _periode = _Periode.ceMois;
  DateTime? _moisChoisi;
  String _recherche = '';

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

  /// Fenêtre rapatriée depuis l'API. Les filtres de période travaillent
  /// ensuite dessus en local, donc elle doit couvrir le plus ancien mois
  /// qu'un filtre peut atteindre.
  static const _moisDisponibles = 12;

  Future<List<PresenceEntry>> _load() {
    final maintenant = DateTime.now();

    return _repository.load(
      debut: DateTime(maintenant.year, maintenant.month - _moisDisponibles, 1),
      fin: DateTime(maintenant.year, maintenant.month + 1, 0),
    );
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  /// Lundi de la semaine contenant [date], à minuit.
  static DateTime _debutSemaine(DateTime date) {
    final jour = DateTime(date.year, date.month, date.day);
    return jour.subtract(Duration(days: jour.weekday - 1));
  }

  /// Bornes de la période sélectionnée, ou null pour « tout l'historique ».
  DateTimeRange? _bornes() {
    final maintenant = DateTime.now();
    final lundi = _debutSemaine(maintenant);

    switch (_periode) {
      case _Periode.tout:
        return null;
      case _Periode.cetteSemaine:
        return DateTimeRange(start: lundi, end: lundi.add(const Duration(days: 7)));
      case _Periode.semaineDerniere:
        final lundiDernier = lundi.subtract(const Duration(days: 7));
        return DateTimeRange(start: lundiDernier, end: lundi);
      case _Periode.ceMois:
        return DateTimeRange(
          start: DateTime(maintenant.year, maintenant.month, 1),
          end: DateTime(maintenant.year, maintenant.month + 1, 1),
        );
      case _Periode.moisPrecis:
        final mois = _moisChoisi ?? maintenant;
        return DateTimeRange(
          start: DateTime(mois.year, mois.month, 1),
          end: DateTime(mois.year, mois.month + 1, 1),
        );
    }
  }

  String _libelleMoisPrecis() {
    if (_moisChoisi == null) return 'Mois de…';
    return DateFormat('MMMM yyyy', 'fr').format(_moisChoisi!);
  }

  Future<void> _choisirMois() async {
    final maintenant = DateTime.now();
    final choix = await showDatePicker(
      context: context,
      initialDate: _moisChoisi ?? DateTime(maintenant.year, maintenant.month, 1),
      firstDate: DateTime(
        maintenant.year,
        maintenant.month - _moisDisponibles,
        1,
      ),
      lastDate: maintenant,
      locale: const Locale('fr'),
      helpText: 'Choisir un mois',
      initialDatePickerMode: DatePickerMode.year,
    );

    if (choix != null) {
      setState(() {
        _moisChoisi = DateTime(choix.year, choix.month, 1);
        _periode = _Periode.moisPrecis;
      });
    }
  }

  List<PresenceEntry> _filtrer(List<PresenceEntry> entrees) {
    final bornes = _bornes();
    final terme = _recherche.trim().toLowerCase();

    final filtrees = entrees.where((entree) {
      final jour = DateTime.tryParse(entree.date);

      if (bornes != null) {
        if (jour == null) return false;
        if (jour.isBefore(bornes.start) || !jour.isBefore(bornes.end)) {
          return false;
        }
      }

      if (terme.isEmpty) return true;

      final champs = [
        entree.date,
        _dateLisible(entree.date),
        formatTime(entree.heureArrivee),
        formatTime(entree.heureDepart),
        entree.source,
        if (entree.enRetard) 'retard ${entree.minutesRetard} min',
      ];

      return champs.any((valeur) => valeur.toLowerCase().contains(terme));
    }).toList();

    filtrees.sort((a, b) => b.date.compareTo(a.date));

    return filtrees;
  }

  /// Regroupe par mois, pour que l'historique reste lisible sur une longue
  /// période sans obliger à lire chaque date.
  Map<String, List<PresenceEntry>> _grouper(List<PresenceEntry> entrees) {
    final groupes = <String, List<PresenceEntry>>{};

    for (final entree in entrees) {
      final jour = DateTime.tryParse(entree.date);
      final cle = jour == null
          ? 'Date inconnue'
          : toBeginningOfSentenceCase(
              DateFormat('MMMM yyyy', 'fr').format(jour),
            );
      groupes.putIfAbsent(cle, () => []).add(entree);
    }

    return groupes;
  }

  static String _dateLisible(String iso) {
    final jour = DateTime.tryParse(iso);
    if (jour == null) return iso;
    return toBeginningOfSentenceCase(
      DateFormat('EEEE d MMMM', 'fr').format(jour),
    );
  }

  @override
  Widget build(BuildContext context) {
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
                  hintText: 'Date, heure, retard…',
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
                    _chip('Cette semaine', _Periode.cetteSemaine),
                    _chip('La semaine dernière', _Periode.semaineDerniere),
                    _chip('Ce mois-ci', _Periode.ceMois),
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: FilterChip(
                        avatar: const Icon(Icons.calendar_month, size: 18),
                        label: Text(_libelleMoisPrecis()),
                        selected: _periode == _Periode.moisPrecis,
                        onSelected: (_) => _choisirMois(),
                      ),
                    ),
                    _chip('Tout', _Periode.tout),
                  ],
                ),
              ),
            ],
          ),
        ),
        Expanded(child: _liste()),
      ],
    );
  }

  Widget _chip(String libelle, _Periode periode) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        label: Text(libelle),
        selected: _periode == periode,
        onSelected: (_) => setState(() => _periode = periode),
      ),
    );
  }

  Widget _liste() {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<List<PresenceEntry>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return ListView(
              children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      Text('${snapshot.error}', textAlign: TextAlign.center),
                      const SizedBox(height: 12),
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

          final toutes = snapshot.data ?? const <PresenceEntry>[];
          final entrees = _filtrer(toutes);

          if (entrees.isEmpty) {
            return ListView(
              children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    toutes.isEmpty
                        ? 'Aucune présence enregistrée.'
                        : _recherche.isNotEmpty
                        ? 'Aucun résultat pour cette recherche.'
                        : 'Aucune présence sur cette période.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AuditronColors.ink500),
                  ),
                ),
              ],
            );
          }

          final groupes = _grouper(entrees);

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Text(
                  '${entrees.length} journée${entrees.length > 1 ? 's' : ''}',
                  style: const TextStyle(
                    color: AuditronColors.ink500,
                    fontSize: 12,
                  ),
                ),
              ),
              for (final groupe in groupes.entries) ...[
                Padding(
                  padding: const EdgeInsets.fromLTRB(4, 8, 4, 6),
                  child: Text(
                    groupe.key,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AuditronColors.brand700,
                    ),
                  ),
                ),
                for (final entree in groupe.value)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Card(
                      margin: EdgeInsets.zero,
                      child: ListTile(
                        title: Text(_dateLisible(entree.date)),
                        subtitle: Text(
                          'Arrivée : ${formatTime(entree.heureArrivee)}   '
                          'Départ : ${formatTime(entree.heureDepart)}',
                        ),
                        trailing: entree.enRetard
                            ? Chip(
                                label: Text('Retard ${entree.minutesRetard} min'),
                                backgroundColor: Colors.orange.shade100,
                              )
                            : const Icon(
                                Icons.check_circle,
                                color: Colors.green,
                              ),
                      ),
                    ),
                  ),
              ],
            ],
          );
        },
      ),
    );
  }
}
