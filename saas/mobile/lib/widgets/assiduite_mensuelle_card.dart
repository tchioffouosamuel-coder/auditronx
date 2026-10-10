import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_client.dart';
import '../services/offline/offline_cache.dart';
import '../theme.dart';

/// Assiduité du mois en cours, affichée sous le bouton de scan (§4.1).
///
/// Un taux nul a deux causes très différentes : l'enseignant n'est jamais
/// venu, ou bien aucun jour n'était attendu parce que son emploi du temps n'a
/// pas encore été chargé. L'API distingue les deux (`emploi_du_temps_charge`,
/// `jours_attendus`) et la carte le dit explicitement, sinon un enseignant
/// assidu lit « 0 % » et croit à une erreur de pointage.
class AssiduiteMensuelleCard extends StatefulWidget {
  const AssiduiteMensuelleCard({super.key});

  @override
  State<AssiduiteMensuelleCard> createState() => _AssiduiteMensuelleCardState();
}

class _AssiduiteMensuelleCardState extends State<AssiduiteMensuelleCard> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final data = await OfflineCache.instance.readThrough(
      'teacher_assiduite_mensuelle',
      () => ApiClient.instance.get('/mon-assiduite'),
    );

    return Map<String, dynamic>.from(data as Map);
  }

  void _refresh() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const _Coque(
            child: Center(
              child: SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(strokeWidth: 2.5),
              ),
            ),
          );
        }

        if (snapshot.hasError || snapshot.data == null) {
          return _Coque(
            child: Row(
              children: [
                const Icon(Icons.cloud_off, color: AuditronColors.ink500),
                const SizedBox(width: 10),
                const Expanded(
                  child: Text(
                    'Assiduité indisponible hors ligne.',
                    style: TextStyle(color: AuditronColors.ink500),
                  ),
                ),
                TextButton(
                  onPressed: _refresh,
                  child: const Text('Réessayer'),
                ),
              ],
            ),
          );
        }

        return _Contenu(donnees: snapshot.data!, onRefresh: _refresh);
      },
    );
  }
}

class _Contenu extends StatelessWidget {
  final Map<String, dynamic> donnees;
  final VoidCallback onRefresh;

  const _Contenu({required this.donnees, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    final taux = (donnees['taux_assiduite'] as num?)?.toDouble() ?? 0;
    final joursAttendus = (donnees['jours_attendus'] as num?)?.toInt() ?? 0;
    final joursPresents = (donnees['jours_presents'] as num?)?.toInt() ?? 0;
    final joursScannes = (donnees['jours_scannes'] as num?)?.toInt() ?? 0;
    final emploiCharge = donnees['emploi_du_temps_charge'] == true;
    final horaireFixe = donnees['horaire_fixe'] == true;

    final mois = DateFormat('MMMM yyyy', 'fr').format(
      DateTime.tryParse('${donnees['mois']}') ?? DateTime.now(),
    );

    // Aucun jour attendu : le pourcentage ne veut rien dire, on explique au
    // lieu d'afficher un 0 % trompeur.
    final sansReference = joursAttendus == 0;

    return _Coque(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Assiduité · $mois',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: AuditronColors.ink900,
                  ),
                ),
              ),
              IconButton(
                onPressed: onRefresh,
                icon: const Icon(Icons.refresh, size: 20),
                color: AuditronColors.ink500,
                tooltip: 'Actualiser',
              ),
            ],
          ),
          const SizedBox(height: 6),
          if (sansReference)
            const Text(
              '—',
              style: TextStyle(
                fontSize: 34,
                fontWeight: FontWeight.w800,
                color: AuditronColors.ink500,
              ),
            )
          else ...[
            Row(
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                Text(
                  '${taux.toStringAsFixed(taux % 1 == 0 ? 0 : 1)} %',
                  style: TextStyle(
                    fontSize: 34,
                    fontWeight: FontWeight.w800,
                    color: _couleurTaux(taux),
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  '$joursPresents / $joursAttendus jours',
                  style: const TextStyle(color: AuditronColors.ink500),
                ),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(6),
              child: LinearProgressIndicator(
                value: (taux / 100).clamp(0, 1),
                minHeight: 8,
                backgroundColor: AuditronColors.ink100,
                valueColor: AlwaysStoppedAnimation(_couleurTaux(taux)),
              ),
            ),
          ],
          if (sansReference || taux == 0) ...[
            const SizedBox(height: 10),
            _Explication(
              emploiCharge: emploiCharge,
              horaireFixe: horaireFixe,
              joursAttendus: joursAttendus,
              joursScannes: joursScannes,
            ),
          ],
        ],
      ),
    );
  }

  static Color _couleurTaux(double taux) {
    if (taux >= 75) return AuditronColors.brand700;
    if (taux >= 50) return AuditronColors.gold600;
    return Colors.red.shade700;
  }
}

class _Explication extends StatelessWidget {
  final bool emploiCharge;
  final bool horaireFixe;
  final int joursAttendus;
  final int joursScannes;

  const _Explication({
    required this.emploiCharge,
    required this.horaireFixe,
    required this.joursAttendus,
    required this.joursScannes,
  });

  @override
  Widget build(BuildContext context) {
    final (texte, icone, couleur) = _message();

    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: couleur.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icone, size: 18, color: couleur),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              texte,
              style: const TextStyle(
                fontSize: 13,
                height: 1.35,
                color: AuditronColors.ink700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  (String, IconData, Color) _message() {
    if (!emploiCharge && !horaireFixe) {
      return (
        'Aucun cours n’est enregistré à votre nom : votre emploi du temps n’a '
            'probablement pas encore été chargé. Tant qu’il manque, aucun jour '
            'de présence ne peut être attendu et le taux reste indisponible. '
            'Signalez-le à l’administration.',
        Icons.event_busy_outlined,
        AuditronColors.gold600,
      );
    }

    if (joursAttendus == 0) {
      return (
        'Aucun jour de présence n’est attendu ce mois-ci d’après votre emploi '
            'du temps. Vérifiez auprès de l’administration qu’il est à jour.',
        Icons.help_outline,
        AuditronColors.gold600,
      );
    }

    if (joursScannes > 0) {
      return (
        'Vous avez pointé $joursScannes jour${joursScannes > 1 ? 's' : ''} ce '
            'mois-ci, mais aucun ne tombe sur un jour où vous êtes attendu. '
            'Votre emploi du temps est peut-être incomplet.',
        Icons.info_outline,
        AuditronColors.gold600,
      );
    }

    return (
      'Aucun pointage enregistré ce mois-ci. Scannez le QR du point de '
          'contrôle à votre arrivée pour alimenter votre assiduité.',
      Icons.qr_code_scanner,
      AuditronColors.brand700,
    );
  }
}

class _Coque extends StatelessWidget {
  final Widget child;

  const _Coque({required this.child});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 12, 10, 14),
        child: child,
      ),
    );
  }
}
