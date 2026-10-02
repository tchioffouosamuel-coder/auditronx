import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';
import '../../utils/date_format_utils.dart';

/// Assiduité & rapports (§admin-mobile) — équivalent mobile d'AssiduitePage.jsx.
/// Lecture seule, 3 onglets : Statistiques / Journal des présences /
/// Personnel inactif. L'export ZIP global (bouton web) n'a pas d'équivalent
/// mobile pour l'instant (pas de FS accessible équivalent) — chaque onglet
/// reste consultable normalement.
class AdminAssiduiteScreen extends StatelessWidget {
  const AdminAssiduiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Column(
        children: [
          Material(
            color: Colors.white,
            child: TabBar(
              labelColor: AuditronColors.brand800,
              unselectedLabelColor: AuditronColors.ink500,
              indicatorColor: AuditronColors.brand700,
              isScrollable: true,
              tabs: const [
                Tab(text: 'Statistiques'),
                Tab(text: 'Journal'),
                Tab(text: 'Personnel inactif'),
              ],
            ),
          ),
          const Expanded(
            child: TabBarView(
              children: [_StatsTab(), _JournalTab(), _PersonnelInactifTab()],
            ),
          ),
        ],
      ),
    );
  }
}

/// Extrait `data` d'une réponse API qui peut être soit un tableau brut
/// (`/assiduite/*`, comme côté web), soit enveloppée en `{data: [...]}`
/// (convention des autres endpoints admin) — on gère les deux par sécurité.
List<dynamic> _asList(dynamic data) {
  if (data is List) return data;
  if (data is Map && data['data'] is List) return data['data'] as List<dynamic>;
  return const [];
}

DateTime _startOfMonth() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, 1);
}

DateTime _endOfMonth() {
  final now = DateTime.now();
  return DateTime(now.year, now.month + 1, 0);
}

String _isoDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

class _StatsTab extends StatefulWidget {
  const _StatsTab();

  @override
  State<_StatsTab> createState() => _StatsTabState();
}

class _StatsTabState extends State<_StatsTab> {
  DateTime _debut = _startOfMonth();
  DateTime _fin = _endOfMonth();
  final _searchController = TextEditingController();
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_assiduite_stats_${_isoDate(_debut)}_${_isoDate(_fin)}',
      () => AdminApiClient.instance.get(
        '/assiduite/stats',
        query: {'debut': _isoDate(_debut), 'fin': _isoDate(_fin)},
      ),
    );
    return _asList(data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _pickDate({required bool isDebut}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: isDebut ? _debut : _fin,
      firstDate: DateTime(2020),
      lastDate: DateTime(DateTime.now().year + 1),
    );
    if (picked == null) return;
    setState(() {
      if (isDebut) {
        _debut = picked;
      } else {
        _fin = picked;
      }
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Row(
            children: [
              Expanded(
                child: _DateFilterButton(
                  label: 'Début',
                  value: _debut,
                  onTap: () => _pickDate(isDebut: true),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _DateFilterButton(
                  label: 'Fin',
                  value: _fin,
                  onTap: () => _pickDate(isDebut: false),
                ),
              ),
            ],
          ),
        ),
        _SearchField(
          controller: _searchController,
          hintText: 'Nom ou section',
          onChanged: () => setState(() {}),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<List<dynamic>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return _errorList('${snapshot.error}');
                }
                final toutes = snapshot.data ?? [];
                if (toutes.isEmpty) {
                  return _emptyList('Aucune donnée pour cette période.');
                }
                final lignes = _filtrer(
                  toutes,
                  _searchController,
                  (l) => [l['nom'], l['section']],
                );
                if (lignes.isEmpty) {
                  return _emptyList(_aucunResultat(_searchController));
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  itemCount: lignes.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final l = lignes[i] as Map<String, dynamic>;
                    final taux = l['taux_assiduite'];
                    final tauxNum = taux is num
                        ? taux
                        : num.tryParse('$taux') ?? 0;
                    return Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.fact_check_outlined,
                          color: AuditronColors.brand700,
                        ),
                        title: Text('${l['nom'] ?? '—'}'),
                        subtitle: Text(
                          '${l['section'] ?? '—'} · ${l['jours_presents'] ?? 0}/${l['jours_attendus'] ?? 0} jours attendus',
                        ),
                        trailing: Text(
                          '$taux%',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            color: tauxNum >= 90
                                ? AuditronColors.brand600
                                : AuditronColors.ink900,
                          ),
                        ),
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

class _JournalTab extends StatefulWidget {
  const _JournalTab();

  @override
  State<_JournalTab> createState() => _JournalTabState();
}

class _JournalTabState extends State<_JournalTab> {
  DateTime _date = DateTime.now();
  final _searchController = TextEditingController();
  late Future<List<dynamic>> _future;
  bool _downloadingPdf = false;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_assiduite_journal_${_isoDate(_date)}',
      () => AdminApiClient.instance.get(
        '/assiduite/journal',
        query: {'date': _isoDate(_date)},
      ),
    );
    return _asList(data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _downloadJournalPdf() async {
    setState(() => _downloadingPdf = true);
    try {
      final date = _isoDate(_date);
      final bytes = await AdminApiClient.instance.getBytes(
        '/assiduite/journal/pdf',
        query: {'date': date},
      );
      final directory = await getTemporaryDirectory();
      final file = File('${directory.path}/journal-presences-$date.pdf');
      await file.writeAsBytes(bytes, flush: true);
      await Share.shareXFiles([XFile(file.path)]);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _downloadingPdf = false);
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime(DateTime.now().year + 1),
    );
    if (picked == null) return;
    setState(() {
      _date = picked;
      _future = _load();
    });
  }

  String? _formatHeure(dynamic iso) {
    if (iso == null) return null;
    return formatTime('$iso');
  }

  void _openPhoto(String url, String title) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        backgroundColor: Colors.black,
        insetPadding: const EdgeInsets.all(16),
        child: Stack(
          alignment: Alignment.topRight,
          children: [
            ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 200),
              child: InteractiveViewer(
                child: Image.network(
                  url,
                  fit: BoxFit.contain,
                  errorBuilder: (_, _, _) => const Padding(
                    padding: EdgeInsets.all(32),
                    child: Icon(
                      Icons.broken_image_outlined,
                      color: Colors.white54,
                      size: 48,
                    ),
                  ),
                ),
              ),
            ),
            IconButton(
              icon: const Icon(Icons.close, color: Colors.white),
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _photoThumb(dynamic url, String title) {
    if (url == null) return const SizedBox.shrink();
    final u = '$url';
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: GestureDetector(
        onTap: () => _openPhoto(u, title),
        child: CircleAvatar(radius: 16, backgroundImage: NetworkImage(u)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Align(
            alignment: Alignment.centerLeft,
            child: Row(
              children: [
                _DateFilterButton(
                  label: 'Date',
                  value: _date,
                  onTap: _pickDate,
                ),
                const Spacer(),
                IconButton(
                  tooltip: 'Télécharger le journal en PDF',
                  onPressed: _downloadingPdf ? null : _downloadJournalPdf,
                  icon: _downloadingPdf
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
        ),
        _SearchField(
          controller: _searchController,
          hintText: 'Nom, matricule ou section',
          onChanged: () => setState(() {}),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<List<dynamic>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return _errorList('${snapshot.error}');
                }
                final toutes = snapshot.data ?? [];
                if (toutes.isEmpty) {
                  return _emptyList('Aucune présence enregistrée ce jour.');
                }
                final presences = _filtrer(toutes, _searchController, (p) {
                  final enseignant = p['enseignant'];
                  if (enseignant is! Map) return const [];
                  return [
                    enseignant['nom'],
                    enseignant['matricule'],
                    enseignant['section'],
                  ];
                });
                if (presences.isEmpty) {
                  return _emptyList(_aucunResultat(_searchController));
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  itemCount: presences.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final p = presences[i] as Map<String, dynamic>;
                    final nom = (p['enseignant'] as Map?)?['nom'] ?? '—';
                    final arrivee = _formatHeure(p['heure_arrivee']);
                    final depart = _formatHeure(p['heure_depart']);
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 10,
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '$nom',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Arrivée : ${arrivee ?? '—'}   ·   Départ : ${depart ?? '—'}',
                                    style: const TextStyle(
                                      color: AuditronColors.ink500,
                                      fontSize: 13,
                                    ),
                                  ),
                                  if (p['source'] != null) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      'Source : ${p['source']}',
                                      style: const TextStyle(
                                        color: AuditronColors.ink500,
                                        fontSize: 12,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                            if (p['photo_url_arrivee'] != null ||
                                p['photo_url_depart'] != null)
                              Row(
                                children: [
                                  _photoThumb(
                                    p['photo_url_arrivee'],
                                    "Photo à l'arrivée",
                                  ),
                                  _photoThumb(
                                    p['photo_url_depart'],
                                    'Photo au départ',
                                  ),
                                ],
                              ),
                          ],
                        ),
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

class _PersonnelInactifTab extends StatefulWidget {
  const _PersonnelInactifTab();

  @override
  State<_PersonnelInactifTab> createState() => _PersonnelInactifTabState();
}

class _PersonnelInactifTabState extends State<_PersonnelInactifTab> {
  final _joursController = TextEditingController(text: '7');
  final _searchController = TextEditingController();
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _joursController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  int get _jours => int.tryParse(_joursController.text.trim()) ?? 7;

  Future<List<dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'admin_assiduite_personnel_inactif_$_jours',
      () => AdminApiClient.instance.get(
        '/assiduite/personnel-inactif',
        query: {'jours': '$_jours'},
      ),
    );
    return _asList(data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          // Wrap plutôt que Row+Spacer : sur un écran étroit, le texte + champ
          // + bouton "Appliquer" dépassent la largeur disponible — un Spacer
          // ne compresse pas les enfants non-flex qui l'entourent, il ne fait
          // que répartir l'espace restant (potentiellement négatif ici).
          child: Wrap(
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 8,
            runSpacing: 8,
            children: [
              const Text(
                'Inactifs depuis plus de',
                style: TextStyle(color: AuditronColors.ink700),
              ),
              SizedBox(
                width: 64,
                child: TextField(
                  controller: _joursController,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    isDense: true,
                    contentPadding: EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                  ),
                ),
              ),
              const Text(
                'jours',
                style: TextStyle(color: AuditronColors.ink700),
              ),
              FilledButton(onPressed: _refresh, child: const Text('Appliquer')),
            ],
          ),
        ),
        _SearchField(
          controller: _searchController,
          hintText: 'Nom ou section',
          onChanged: () => setState(() {}),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: FutureBuilder<List<dynamic>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return _errorList('${snapshot.error}');
                }
                final tous = snapshot.data ?? [];
                if (tous.isEmpty) {
                  return _emptyList(
                    'Aucun personnel inactif sur cette période.',
                  );
                }
                final inactifs = _filtrer(
                  tous,
                  _searchController,
                  (p) => [p['nom'], p['section']],
                );
                if (inactifs.isEmpty) {
                  return _emptyList(_aucunResultat(_searchController));
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  itemCount: inactifs.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    final p = inactifs[i] as Map<String, dynamic>;
                    return Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.person_off_outlined,
                          color: AuditronColors.gold600,
                        ),
                        title: Text('${p['nom'] ?? '—'}'),
                        subtitle: Text('${p['section'] ?? '—'}'),
                        trailing: Text(
                          'Dernière présence :\n${p['derniere_presence'] ?? 'Jamais'}',
                          textAlign: TextAlign.right,
                          style: const TextStyle(
                            fontSize: 12,
                            color: AuditronColors.ink500,
                          ),
                        ),
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

class _DateFilterButton extends StatelessWidget {
  final String label;
  final DateTime value;
  final VoidCallback onTap;

  const _DateFilterButton({
    required this.label,
    required this.value,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onTap,
      icon: const Icon(Icons.calendar_today, size: 16),
      label: Text('$label : ${_isoDate(value)}'),
      style: OutlinedButton.styleFrom(
        foregroundColor: AuditronColors.ink700,
        side: const BorderSide(color: AuditronColors.ink100),
        alignment: Alignment.centerLeft,
      ),
    );
  }
}

/// Barre de recherche locale commune aux trois onglets : filtre la liste déjà
/// chargée, sans appel réseau.
class _SearchField extends StatelessWidget {
  final TextEditingController controller;
  final String hintText;
  final VoidCallback onChanged;

  const _SearchField({
    required this.controller,
    required this.hintText,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
      child: TextField(
        controller: controller,
        onChanged: (_) => onChanged(),
        decoration: InputDecoration(
          labelText: 'Rechercher un enseignant',
          hintText: hintText,
          prefixIcon: const Icon(Icons.search),
          suffixIcon: controller.text.isEmpty
              ? null
              : IconButton(
                  icon: const Icon(Icons.clear),
                  tooltip: 'Effacer la recherche',
                  onPressed: () {
                    controller.clear();
                    onChanged();
                  },
                ),
        ),
      ),
    );
  }
}

/// Garde les lignes dont l'une des valeurs de [values] contient la recherche
/// saisie (insensible à la casse) ; liste inchangée si la recherche est vide.
List<dynamic> _filtrer(
  List<dynamic> lignes,
  TextEditingController controller,
  List<dynamic> Function(Map<dynamic, dynamic> ligne) values,
) {
  final query = controller.text.trim().toLowerCase();
  if (query.isEmpty) return lignes;
  return lignes.where((ligne) {
    if (ligne is! Map) return false;
    return values(
      ligne,
    ).any((value) => '${value ?? ''}'.toLowerCase().contains(query));
  }).toList();
}

String _aucunResultat(TextEditingController controller) =>
    'Aucun enseignant trouvé pour « ${controller.text.trim()} ».';

Widget _emptyList(String message) {
  return ListView(
    children: [
      Padding(padding: const EdgeInsets.all(24), child: Text(message)),
    ],
  );
}

Widget _errorList(String message) {
  return ListView(
    children: [
      Padding(
        padding: const EdgeInsets.all(24),
        child: Text('Erreur : $message'),
      ),
    ],
  );
}
