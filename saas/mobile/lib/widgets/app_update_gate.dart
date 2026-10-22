import 'package:flutter/material.dart';

import '../services/app_update_service.dart';
import '../theme.dart';

/// Interpose la modale de mise à jour au-dessus de l'application (§maj-app).
///
/// Placé autour de l'écran racine, ce widget interroge le Play Store au
/// démarrage puis à chaque retour au premier plan — un enseignant garde
/// l'application ouverte des semaines, le démarrage seul ne suffirait pas à
/// lui signaler une publication.
class AppUpdateGate extends StatefulWidget {
  const AppUpdateGate({super.key, required this.child});

  final Widget child;

  @override
  State<AppUpdateGate> createState() => _AppUpdateGateState();
}

class _AppUpdateGateState extends State<AppUpdateGate>
    with WidgetsBindingObserver {
  /// Garde-fou : la vérification est asynchrone et le retour au premier plan
  /// peut se produire pendant qu'une modale est déjà ouverte.
  bool _modaleOuverte = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _verifier());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _verifier();
  }

  Future<void> _verifier() async {
    if (_modaleOuverte) return;

    final maj = await AppUpdateService.instance.rechercher();
    if (maj == null || !mounted || _modaleOuverte) return;

    _modaleOuverte = true;
    try {
      await showAppUpdateDialog(context, maj);
    } finally {
      _modaleOuverte = false;
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

/// Affiche la modale « Nouvelle version disponible ».
///
/// Barrière et bouton retour sont neutralisés pour une mise à jour marquée
/// prioritaire : un correctif de pointage ne doit pas pouvoir être contourné
/// d'un tap à côté de la boîte.
Future<void> showAppUpdateDialog(
  BuildContext context,
  AppUpdateDisponible maj,
) {
  return showDialog<void>(
    context: context,
    barrierDismissible: !maj.bloquante,
    builder: (_) => _AppUpdateDialog(maj: maj),
  );
}

class _AppUpdateDialog extends StatefulWidget {
  const _AppUpdateDialog({required this.maj});

  final AppUpdateDisponible maj;

  @override
  State<_AppUpdateDialog> createState() => _AppUpdateDialogState();
}

class _AppUpdateDialogState extends State<_AppUpdateDialog> {
  bool _enCours = false;
  String? _erreur;

  Future<void> _installer() async {
    setState(() {
      _enCours = true;
      _erreur = null;
    });

    final engagee = await AppUpdateService.instance.installer(widget.maj);
    if (!mounted) return;

    if (engagee) {
      // Play prend la main (écran d'installation, puis redémarrage de l'app) :
      // la modale est refermée pour ne pas rester affichée au retour.
      Navigator.of(context).pop();
      return;
    }

    setState(() {
      _enCours = false;
      _erreur =
          "La mise à jour n'a pas pu démarrer. Ouvrez Play Store et "
          "recherchez « Auditron X » pour l'installer manuellement.";
    });
  }

  @override
  Widget build(BuildContext context) {
    final maj = widget.maj;

    return PopScope(
      canPop: !maj.bloquante && !_enCours,
      child: AlertDialog(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        icon: Container(
          padding: const EdgeInsets.all(12),
          decoration: const BoxDecoration(
            color: AuditronColors.brand50,
            shape: BoxShape.circle,
          ),
          child: const Icon(
            Icons.system_update,
            color: AuditronColors.brand700,
            size: 28,
          ),
        ),
        title: Text(
          maj.bloquante ? 'Mise à jour requise' : 'Nouvelle version disponible',
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontFamily: AuditronFonts.display,
            fontSize: 19,
            fontWeight: FontWeight.w700,
            color: AuditronColors.ink900,
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              maj.bloquante
                  ? "Cette version d'Auditron X n'est plus prise en charge. "
                        'Installez la mise à jour pour continuer à pointer vos '
                        'présences.'
                  : "Une version plus récente d'Auditron X est publiée sur le "
                        'Play Store. La mise à jour apporte les derniers '
                        'correctifs et améliorations.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                height: 1.45,
                color: AuditronColors.ink700,
              ),
            ),
            const SizedBox(height: 14),
            _LigneVersions(maj: maj),
            if (_erreur != null) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AuditronColors.orange100,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  _erreur!,
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.4,
                    color: AuditronColors.orange700,
                  ),
                ),
              ),
            ],
          ],
        ),
        actionsAlignment: MainAxisAlignment.center,
        actions: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              FilledButton.icon(
                onPressed: _enCours ? null : _installer,
                icon: _enCours
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.download_rounded, size: 18),
                label: Text(
                  _enCours ? 'Mise à jour en cours…' : 'Mettre à jour',
                ),
              ),
              if (!maj.bloquante)
                TextButton(
                  onPressed: _enCours ? null : () => Navigator.of(context).pop(),
                  child: const Text('Plus tard'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Rappelle la version installée et, quand Play la communique, celle qui
/// attend : l'utilisateur voit ce qu'il quitte et ce qu'il obtient.
class _LigneVersions extends StatelessWidget {
  const _LigneVersions({required this.maj});

  final AppUpdateDisponible maj;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<String>(
      future: AppUpdateService.instance.versionInstallee,
      builder: (context, snapshot) {
        final installee = snapshot.data ?? '';
        if (installee.isEmpty) return const SizedBox.shrink();

        final disponible = maj.versionDisponible;
        final texte = disponible == null
            ? 'Version installée : $installee'
            : 'Version installée : $installee → build $disponible';

        return Text(
          texte,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 12, color: AuditronColors.ink500),
        );
      },
    );
  }
}
