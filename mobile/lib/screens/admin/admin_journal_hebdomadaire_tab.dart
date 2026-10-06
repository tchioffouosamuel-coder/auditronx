import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/admin_api_client.dart';
import '../../theme.dart';

/// Journal hebdomadaire des présences (§4.2 — admin-mobile), équivalent mobile
/// de JournalHebdomadaire.jsx.
///
/// Complète le journal quotidien plutôt qu'il ne le remplace : le quotidien ne
/// liste que les présences enregistrées, un absent n'y figure donc pas du tout.
/// Cette vue part du personnel attendu, ce qui rend les absences visibles.
///
/// Pas de cache hors ligne : la grille se recalcule pour chaque semaine
/// demandée et sert surtout à produire le PDF, qui exige le réseau de toute
/// façon.
class AdminJournalHebdomadaireTab extends StatefulWidget {
  const AdminJournalHebdomadaireTab({super.key});

  @override
  State<AdminJournalHebdomadaireTab> createState() =>
      _AdminJournalHebdomadaireTabState();
}

class _AdminJournalHebdomadaireTabState
    extends State<AdminJournalHebdomadaireTab> {
  final _rechercheController = TextEditingController();
  final _isoFormat = DateFormat('yyyy-MM-dd');

  late Future<Map<String, dynamic>> _future;
  late DateTime _lundi;
  String _recherche = '';
  bool _telechargement = false;

  static DateTime _lundiDe(DateTime date) {
    final jour = DateTime(date.year, date.month, date.day);
    return jour.subtract(Duration(days: jour.weekday - 1));
  }

  @override
  void initState() {
    super.initState();
    _lundi = _lundiDe(DateTime.now());
    _future = _load();
  }

  @override
  void dispose() {
    _rechercheController.dispose();
    super.dispose();
  }

  Future<Map<String, dynamic>> _load() async {
    final data = await AdminApiClient.instance.get(
      '/assiduite/journal-hebdomadaire',
      query: {'semaine': _isoFormat.format(_lundi)},
    );

    return Map<String, dynamic>.from(data as Map);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  void _decaler(int semaines) {
    setState(() {
      _lundi = _lundi.add(Duration(days: semaines * 7));
      _future = _load();
    });
  }

  Future<void> _choisirSemaine() async {
    final choix = await showDatePicker(
      context: context,
      initialDate: _lundi,
      firstDate: DateTime(2020),
      lastDate: DateTime(DateTime.now().year + 1),
      helpText: 'Choisir une semaine',
    );

    if (choix == null) return;

    setState(() {
      _lundi = _lundiDe(choix);
      _future = _load();
    });
  }

  Future<void> _telechargerPdf() async {
    setState(() => _telechargement = true);
    try {
      final semaine = _isoFormat.format(_lundi);
      final bytes = await AdminApiClient.instance.getBytes(
        '/assiduite/journal-hebdomadaire/pdf',
        query: {'semaine': semaine},
      );
      final directory = await getTemporaryDirectory();
      final fichier = File(
        '${directory.path}/journal-hebdomadaire-$semaine.pdf',
      );
      await fichier.writeAsBytes(bytes, flush: true);
      await Share.shareXFiles([XFile(fichier.path)]);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _telechargement = false);
    }
  }

  List<Map<String, dynamic>> _filtrer(List<dynamic> lignes) {
    final terme = _recherche.trim().toLowerCase();

    return lignes
        .whereType<Map>()
        .map((ligne) => Map<String, dynamic>.from(ligne))
        .where(
          (ligne) =>
              terme.isEmpty ||
              [ligne['nom'], ligne['matricule'], ligne['section']].any(
                (valeur) =>
                    '${valeur ?? ''}'.toLowerCase().contains(terme),
              ),
        )
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final libelle = DateFormat('d MMM', 'fr');

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 8, 4),
          child: Row(
            children: [
              IconButton(
                tooltip: 'Semaine précédente',
                onPressed: () => _decaler(-1),
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: TextButton.icon(
                  onPressed: _choisirSemaine,
                  icon: const Icon(Icons.date_range, size: 18),
                  label: Text(
                    '${libelle.format(_lundi)} – '
                    '${libelle.format(_lundi.add(const Duration(days: 5)))}',
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Semaine suivante',
                onPressed: () => _decaler(1),
                icon: const Icon(Icons.chevron_right),
              ),
              IconButton(
                tooltip: 'Télécharger la semaine en PDF',
                onPressed: _telechargement ? null : _telechargerPdf,
                icon: _telechargement
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.picture_as_pdf_outlined),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
          child: TextField(
            controller: _rechercheController,
            onChanged: (valeur) => setState(() => _recherche = valeur),
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.search),
              hintText: 'Nom, matricule ou section',
              isDense: true,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<Map<String, dynamic>>(
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
                            Text(
                              '${snapshot.error}',
                              textAlign: TextAlign.center,
                            ),
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

                final donnees = snapshot.data ?? const {};
                final jours = (donnees['jours'] as List? ?? const [])
                    .whereType<Map>()
                    .map((j) => Map<String, dynamic>.from(j))
                    .toList();
                final lignes = _filtrer(donnees['lignes'] as List? ?? const []);

                if (lignes.isEmpty) {
                  return ListView(
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          _recherche.isEmpty
                              ? 'Aucun membre du personnel dans votre périmètre.'
                              : 'Aucun résultat pour cette recherche.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AuditronColors.ink500,
                          ),
                        ),
                      ),
                    ],
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                  itemCount: lignes.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) =>
                      _LigneSemaine(ligne: lignes[i], jours: jours),
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

/// Une ligne = un membre du personnel. Les six jours sont présentés en pastilles
/// plutôt qu'en tableau : un tableau de six colonnes n'est pas lisible sur un
/// téléphone, et le PDF couvre déjà le besoin de grille imprimable.
class _LigneSemaine extends StatelessWidget {
  final Map<String, dynamic> ligne;
  final List<Map<String, dynamic>> jours;

  const _LigneSemaine({required this.ligne, required this.jours});

  @override
  Widget build(BuildContext context) {
    final cellules = (ligne['jours'] as List? ?? const [])
        .whereType<Map>()
        .map((c) => Map<String, dynamic>.from(c))
        .toList();
    final taux = ligne['taux_assiduite'];

    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${ligne['nom'] ?? '—'}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AuditronColors.ink900,
                        ),
                      ),
                      Text(
                        '${ligne['section'] ?? 'Section non renseignée'}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AuditronColors.ink500,
                        ),
                      ),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      taux == null ? '—' : '$taux %',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        color: _couleurTaux(taux),
                      ),
                    ),
                    Text(
                      '${ligne['jours_presents']} / ${ligne['jours_attendus']}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: AuditronColors.ink500,
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                for (var i = 0; i < cellules.length; i++)
                  Expanded(
                    child: _Pastille(
                      libelle: i < jours.length
                          ? '${jours[i]['libelle'] ?? ''}'
                          : '',
                      cellule: cellules[i],
                    ),
                  ),
              ],
            ),
            if (taux == null) ...[
              const SizedBox(height: 8),
              const Text(
                'Aucun jour attendu cette semaine : emploi du temps '
                'probablement absent.',
                style: TextStyle(fontSize: 11, color: AuditronColors.ink500),
              ),
            ],
          ],
        ),
      ),
    );
  }

  static Color _couleurTaux(dynamic taux) {
    if (taux == null) return AuditronColors.ink500;
    final valeur = (taux as num).toDouble();
    if (valeur >= 75) return AuditronColors.brand700;
    if (valeur >= 50) return AuditronColors.gold600;
    return Colors.red.shade700;
  }
}

class _Pastille extends StatelessWidget {
  final String libelle;
  final Map<String, dynamic> cellule;

  const _Pastille({required this.libelle, required this.cellule});

  @override
  Widget build(BuildContext context) {
    final attendu = cellule['attendu'] == true;
    final present = cellule['present'] == true;
    // Un jour férié rend `attendu` faux côté API : sans traitement distinct, il
    // s'afficherait comme un jour sans cours, alors que c'est la raison même
    // pour laquelle personne n'est porté absent.
    final ferie = (cellule['ferie'] as String?)?.trim();
    final estFerie = ferie != null && ferie.isNotEmpty;

    final (fond, texte, contenu) = estFerie
        ? (AuditronColors.gold100, AuditronColors.gold600, 'FÉR')
        : !attendu
        ? (AuditronColors.ink50, AuditronColors.ink500, '·')
        : present
        ? (
            AuditronColors.brand100,
            AuditronColors.brand700,
            '${cellule['heure_arrivee'] ?? '✓'}',
          )
        : (Colors.red.shade50, Colors.red.shade700, 'ABS');

    return Tooltip(
      message: estFerie
          ? 'Jour férié : $ferie'
          : !attendu
          ? 'Non attendu ce jour-là'
          : present
          ? 'Arrivée ${cellule['heure_arrivee'] ?? '—'} · '
                'Départ ${cellule['heure_depart'] ?? '—'}'
          : 'Attendu, aucun pointage enregistré',
      child: Padding(
        padding: const EdgeInsets.only(right: 4),
        child: Column(
          children: [
            Text(
              libelle,
              style: const TextStyle(
                fontSize: 10,
                color: AuditronColors.ink500,
              ),
            ),
            const SizedBox(height: 3),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 6),
              decoration: BoxDecoration(
                color: fond,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                contenu,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: texte,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
