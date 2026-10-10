import 'dart:io';

import 'package:flutter/material.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';

/// Extrait `data` d'une réponse API : tableau brut (`/assiduite/*`) ou
/// enveloppe `{data: [...]}` (convention des autres endpoints admin).
List<dynamic> _asList(dynamic data) {
  if (data is List) return data;
  if (data is Map && data['data'] is List) return data['data'] as List<dynamic>;
  return const [];
}

/// Sans présence (§admin-mobile) — enseignants du périmètre n'ayant jamais
/// pointé : aucune présence enregistrée dans le système, toutes périodes
/// confondues. Lecture seule, avec recherche locale et export PDF.
class AdminSansPresenceScreen extends StatefulWidget {
  const AdminSansPresenceScreen({super.key});

  @override
  State<AdminSansPresenceScreen> createState() =>
      _AdminSansPresenceScreenState();
}

class _AdminSansPresenceScreenState extends State<AdminSansPresenceScreen> {
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
      'admin_assiduite_sans_presence',
      () => AdminApiClient.instance.get('/assiduite/sans-presence'),
    );
    return _asList(data);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  /// Le PDF contient toute la liste (la recherche locale ne s'y applique pas).
  Future<void> _downloadPdf() async {
    setState(() => _downloadingPdf = true);
    try {
      final bytes = await AdminApiClient.instance.getBytes(
        '/assiduite/sans-presence/pdf',
      );
      final directory = await getTemporaryDirectory();
      final date = DateTime.now().toIso8601String().substring(0, 10);
      final file = File('${directory.path}/sans-presence-$date.pdf');
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

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchController,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: 'Rechercher un enseignant',
                    hintText: 'Nom, matricule ou section',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searchController.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.clear),
                            tooltip: 'Effacer la recherche',
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                            },
                          ),
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Télécharger la liste en PDF',
                onPressed: _downloadingPdf ? null : _downloadPdf,
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
                  return _message('Erreur : ${snapshot.error}');
                }
                final tous = snapshot.data ?? [];
                final query = _searchController.text.trim().toLowerCase();
                final lignes = query.isEmpty
                    ? tous
                    : tous.where((item) {
                        if (item is! Map) return false;
                        return ['nom', 'matricule', 'section'].any(
                          (key) => '${item[key] ?? ''}'.toLowerCase().contains(
                            query,
                          ),
                        );
                      }).toList();
                if (lignes.isEmpty) {
                  return _message(
                    query.isEmpty
                        ? 'Tous les enseignants ont au moins une présence enregistrée.'
                        : 'Aucun enseignant trouvé pour « ${_searchController.text.trim()} ».',
                  );
                }
                return ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  // +1 : ligne de compteur en tête de liste.
                  itemCount: lignes.length + 1,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, i) {
                    if (i == 0) {
                      return Text(
                        query.isEmpty
                            ? '${tous.length} enseignant(s) sans aucune présence'
                            : '${lignes.length} sur ${tous.length} enseignant(s) sans aucune présence',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          color: AuditronColors.ink700,
                        ),
                      );
                    }
                    final l = lignes[i - 1] as Map<String, dynamic>;
                    final tel = '${l['tel'] ?? ''}'.trim();
                    return Card(
                      child: ListTile(
                        leading: const Icon(
                          Icons.person_off_outlined,
                          color: AuditronColors.gold600,
                        ),
                        title: Text('${l['nom'] ?? '—'}'),
                        subtitle: Text(
                          '${l['matricule'] ?? '—'} · ${l['section'] ?? '—'}',
                        ),
                        trailing: tel.isEmpty
                            ? null
                            : Text(
                                tel,
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

  /// ListView (et non Center) pour garder le tirer-pour-rafraîchir actif.
  Widget _message(String message) {
    return ListView(
      children: [
        Padding(padding: const EdgeInsets.all(24), child: Text(message)),
      ],
    );
  }
}
