import 'package:flutter/material.dart';

import '../../services/admin_api_client.dart';
import '../../services/offline/offline_cache.dart';
import '../../theme.dart';
import '../../widgets/admin/admin_field_spec.dart';
import '../../widgets/admin/admin_select_field.dart';

/// Cahier de texte (§admin-mobile) — équivalent mobile lecture seule de
/// `CahierTextePage.jsx` : la saisie se fait désormais depuis l'app mobile
/// par l'enseignant lui-même sur son propre créneau, l'admin ne fait que
/// consulter l'historique (aucune création/édition/suppression ici).
class AdminCahierTexteScreen extends StatefulWidget {
  const AdminCahierTexteScreen({super.key});

  @override
  State<AdminCahierTexteScreen> createState() => _AdminCahierTexteScreenState();
}

class _AdminCahierTexteScreenState extends State<AdminCahierTexteScreen> {
  static final _enseignantField = AdminFieldSpec(
    key: 'enseignant_id',
    label: 'Enseignant',
    type: AdminFieldType.select,
    optionsEndpoint: '/personnel?per_page=500',
  );

  dynamic _enseignantId;
  Future<List<dynamic>>? _future;

  void _onEnseignantChanged(dynamic value) {
    setState(() {
      _enseignantId = value;
      _future = value == null ? null : _load(value);
    });
  }

  Future<List<dynamic>> _load(dynamic enseignantId) async {
    final data = await OfflineCache.instance.readThrough(
      'admin_cahier_texte_$enseignantId',
      () => AdminApiClient.instance.get('/cahier-texte/$enseignantId'),
    );
    if (data is Map && data['data'] is List) return data['data'] as List<dynamic>;
    if (data is List) return data;
    return const [];
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Cahier de texte', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 4),
              Text(
                'Consultation seule — les entrées sont saisies par les enseignants depuis l\'application mobile.',
                style: TextStyle(fontSize: 13, color: AuditronColors.ink500),
              ),
              const SizedBox(height: 16),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 320),
                child: AdminSelectField(
                  field: _enseignantField,
                  value: _enseignantId,
                  onChanged: _onEnseignantChanged,
                ),
              ),
            ],
          ),
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_enseignantId == null || _future == null) {
      return Center(
        child: Text(
          'Sélectionnez un enseignant pour consulter son cahier de texte.',
          style: TextStyle(color: AuditronColors.ink500),
          textAlign: TextAlign.center,
        ),
      );
    }

    return FutureBuilder<List<dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Text('Erreur : ${snapshot.error}'));
        }

        final entrees = snapshot.data ?? [];
        if (entrees.isEmpty) {
          return Center(
            child: Text('Aucune entrée pour cet enseignant.', style: TextStyle(color: AuditronColors.ink500)),
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          itemCount: entrees.length,
          separatorBuilder: (_, _) => const SizedBox(height: 8),
          itemBuilder: (context, i) {
            final entree = entrees[i] as Map<String, dynamic>;
            final emploi = entree['emploi_du_temps'] as Map<String, dynamic>?;
            final classe = emploi?['classe'] as Map<String, dynamic>?;
            final discipline = emploi?['discipline'] as Map<String, dynamic>?;
            final reference = entree['reference_programme']?.toString();

            return Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          entree['date']?.toString() ?? '—',
                          style: TextStyle(fontSize: 12, color: AuditronColors.ink500),
                        ),
                        const SizedBox(width: 8),
                        // Expanded + ellipsis (remplace spaceBetween) : un nom
                        // de classe/discipline long ferait déborder ce Row sur
                        // un écran étroit sans widget flexible pour tronquer.
                        Expanded(
                          child: Text(
                            '${classe?['nom'] ?? '—'} — ${discipline?['nom'] ?? '—'}',
                            textAlign: TextAlign.right,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(fontSize: 12, color: AuditronColors.ink500),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(entree['contenu']?.toString() ?? '', style: Theme.of(context).textTheme.bodyMedium),
                    if (reference != null && reference.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(
                        'Réf. programme : $reference',
                        style: TextStyle(fontSize: 12, color: AuditronColors.ink500),
                      ),
                    ],
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
