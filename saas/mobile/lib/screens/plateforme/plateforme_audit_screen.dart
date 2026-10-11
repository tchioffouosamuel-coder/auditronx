import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

class PlateformeAuditScreen extends StatefulWidget {
  const PlateformeAuditScreen({super.key});

  @override
  State<PlateformeAuditScreen> createState() => _PlateformeAuditScreenState();
}

class _PlateformeAuditScreenState extends State<PlateformeAuditScreen> {
  List<Map<String, dynamic>> _entries = [];
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final now = DateTime.now();
      final entries = <Map<String, dynamic>>[];
      final query = {
        'debut': DateFormat('yyyy-MM-dd').format(DateTime(now.year, now.month)),
        'fin': DateFormat('yyyy-MM-dd').format(now),
        'per_page': '200',
      };
      var page = 1;
      var lastPage = 1;
      do {
        final response =
            await PlateformeApiClient.instance.get(
                  '/audit-logs',
                  query: {...query, 'page': '$page'},
                )
                as Map<String, dynamic>;
        entries.addAll(
          (response['data'] as List? ?? const []).whereType<Map>().map(
            (entry) => Map<String, dynamic>.from(entry),
          ),
        );
        lastPage = (response['last_page'] as num?)?.toInt() ?? page;
        page++;
      } while (page <= lastPage && page <= 40);

      if (mounted) {
        setState(() {
          _entries = entries;
          _loading = false;
        });
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.message;
          _loading = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = 'Chargement impossible : $error';
          _loading = false;
        });
      }
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Journal d’audit Auditron',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          const Text(
            'Actions du portail central. Les mots de passe et jetons sont masqués.',
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.red)),
          ],
          if (_loading)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_entries.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text('Aucune action enregistrée ce mois-ci.'),
            )
          else ...[
            const SizedBox(height: 12),
            for (final entry in _entries)
              Card(
                child: ExpansionTile(
                  leading: const Icon(
                    Icons.history,
                    color: AuditronColors.brand700,
                  ),
                  title: Text(
                    entry['action']?.toString() ?? 'Action',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text(
                    '${entry['auteur_nom'] ?? 'Inconnu'} · ${_date(entry['created_at'])}',
                  ),
                  childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                  children: [
                    _detail('Rôle', entry['auteur_accreditation']),
                    _detail('Cible', entry['sujet_libelle']),
                    _detail(
                      'Requête',
                      '${entry['methode'] ?? '—'} ${entry['route'] ?? entry['url'] ?? ''} · ${entry['statut'] ?? '—'}',
                    ),
                    _detail('Adresse IP', entry['ip']),
                    if (entry['changements'] != null)
                      _detail('Changements', entry['changements']),
                    if (entry['contexte'] != null)
                      _detail('Contexte', entry['contexte']),
                  ],
                ),
              ),
          ],
          if (_loading && _entries.isNotEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }

  static String _date(dynamic value) {
    final date = DateTime.tryParse(value?.toString() ?? '');
    return date == null
        ? '—'
        : DateFormat('dd/MM/yyyy HH:mm:ss').format(date.toLocal());
  }

  static Widget _detail(String title, dynamic value) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Align(
      alignment: Alignment.centerLeft,
      child: Text(
        '$title : ${value is Map || value is List ? value.toString() : value ?? '—'}',
        style: const TextStyle(color: AuditronColors.ink700),
      ),
    ),
  );
}
