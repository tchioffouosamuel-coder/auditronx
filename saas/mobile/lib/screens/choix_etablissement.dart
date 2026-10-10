import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../services/etablissement_service.dart';
import '../theme.dart';

/// Première étape de l'activation : choisir son établissement.
///
/// L'application est unique pour tous les abonnés ; ce choix, fait une seule
/// fois, détermine la base de données interrogée pour toute la vie de
/// l'installation. Trois chemins, par ordre de confort : la liste des
/// établissements, le scan du QR d'enrôlement affiché par l'administration, et
/// la saisie du code. Un quatrième, de dépannage, retrouve l'établissement à
/// partir du numéro de téléphone de l'enseignant.
class ChoixEtablissement extends StatefulWidget {
  const ChoixEtablissement({super.key, required this.onChoisi});

  /// Appelé avec le branding de l'établissement retenu, une fois vérifié
  /// auprès de l'API.
  final void Function(Map<String, dynamic>? branding) onChoisi;

  @override
  State<ChoixEtablissement> createState() => _ChoixEtablissementState();
}

class _ChoixEtablissementState extends State<ChoixEtablissement> {
  final _codeController = TextEditingController();
  final _telController = TextEditingController();

  List<Map<String, dynamic>> _etablissements = [];
  bool _chargement = true;
  bool _validation = false;
  String? _erreur;
  String? _info;

  @override
  void initState() {
    super.initState();
    _chargerCatalogue();
  }

  @override
  void dispose() {
    _codeController.dispose();
    _telController.dispose();
    super.dispose();
  }

  Future<void> _chargerCatalogue() async {
    try {
      final liste = await EtablissementService.instance.catalogue();
      if (mounted) setState(() => _etablissements = liste);
    } on ApiException catch (e) {
      // Pas bloquant : la saisie du code reste disponible.
      debugPrint('Catalogue indisponible : ${e.message}');
    } finally {
      if (mounted) setState(() => _chargement = false);
    }
  }

  Future<void> _retenir(String code) async {
    setState(() {
      _validation = true;
      _erreur = null;
      _info = null;
    });

    try {
      final branding = await EtablissementService.instance.verifier(code);

      if (branding == null) {
        setState(() => _erreur = 'Code d’établissement invalide.');
        return;
      }

      widget.onChoisi(branding);
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e.message);
    } finally {
      if (mounted) setState(() => _validation = false);
    }
  }

  Future<void> _chercherParTelephone() async {
    final tel = _telController.text.trim();

    if (tel.length < 6) return;

    setState(() {
      _validation = true;
      _erreur = null;
      _info = null;
    });

    try {
      final trouves = await EtablissementService.instance.parTelephone(tel);

      if (!mounted) return;

      if (trouves.isEmpty) {
        setState(
          () => _info =
              'Aucun établissement trouvé pour ce numéro. Demandez le code à votre administration.',
        );
        return;
      }

      // Un seul résultat : on enchaîne directement, c'est le cas courant.
      if (trouves.length == 1) {
        await _retenir(trouves.first['code'] as String);
        return;
      }

      setState(() {
        _etablissements = trouves;
        _info = 'Plusieurs établissements correspondent : choisissez le vôtre.';
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e.message);
    } finally {
      if (mounted) setState(() => _validation = false);
    }
  }

  Future<void> _scanner() async {
    final code = await Navigator.of(
      context,
    ).push<String>(MaterialPageRoute(builder: (_) => const _ScanEnrolement()));

    if (code != null && code.isNotEmpty) await _retenir(code);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Sélectionnez votre établissement. Ce choix n’est demandé qu’une fois.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AuditronColors.ink700),
        ),
        const SizedBox(height: 16),

        if (_erreur != null) ...[
          Text(
            _erreur!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.red),
          ),
          const SizedBox(height: 12),
        ],

        if (_info != null) ...[
          Text(
            _info!,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AuditronColors.ink700),
          ),
          const SizedBox(height: 12),
        ],

        if (_chargement)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
          )
        else if (_etablissements.isNotEmpty)
          ConstrainedBox(
            constraints: const BoxConstraints(maxHeight: 220),
            child: ListView.separated(
              shrinkWrap: true,
              itemCount: _etablissements.length,
              separatorBuilder: (_, _) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final etablissement = _etablissements[index];

                return ListTile(
                  dense: true,
                  leading: etablissement['logo_url'] != null
                      ? Image.network(
                          etablissement['logo_url'] as String,
                          width: 32,
                          height: 32,
                          errorBuilder: (_, _, _) =>
                              const Icon(Icons.school_outlined),
                        )
                      : const Icon(Icons.school_outlined),
                  title: Text(etablissement['nom'] as String? ?? ''),
                  subtitle: Text(
                    [
                      etablissement['ville'],
                      etablissement['code'],
                    ].where((v) => v != null).join(' · '),
                  ),
                  onTap: _validation
                      ? null
                      : () => _retenir(etablissement['code'] as String),
                );
              },
            ),
          ),

        const SizedBox(height: 16),
        TextField(
          controller: _codeController,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Code de l’établissement',
            hintText: 'ex. LTM',
          ),
          onSubmitted: (v) => _retenir(v),
        ),
        const SizedBox(height: 12),
        FilledButton(
          onPressed: _validation ? null : () => _retenir(_codeController.text),
          child: _validation
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Text('Continuer'),
        ),
        const SizedBox(height: 4),
        OutlinedButton.icon(
          onPressed: _validation ? null : _scanner,
          icon: const Icon(Icons.qr_code_scanner),
          label: const Text('Scanner le QR de mon établissement'),
        ),

        const Divider(height: 28),
        TextField(
          controller: _telController,
          keyboardType: TextInputType.phone,
          decoration: const InputDecoration(
            labelText: 'Code oublié ? Votre numéro de téléphone',
          ),
          onSubmitted: (_) => _chercherParTelephone(),
        ),
        const SizedBox(height: 8),
        TextButton(
          onPressed: _validation ? null : _chercherParTelephone,
          child: const Text('Retrouver mon établissement'),
        ),
      ],
    );
  }
}

/// Scan du QR d'enrôlement : il encode le seul code de l'établissement.
class _ScanEnrolement extends StatefulWidget {
  const _ScanEnrolement();

  @override
  State<_ScanEnrolement> createState() => _ScanEnrolementState();
}

class _ScanEnrolementState extends State<_ScanEnrolement> {
  bool _rendu = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('QR de l’établissement')),
      body: MobileScanner(
        onDetect: (capture) {
          if (_rendu) return;

          final valeur = capture.barcodes
              .map((b) => b.rawValue)
              .firstWhere((v) => v != null && v.isNotEmpty, orElse: () => null);

          if (valeur == null) return;

          _rendu = true;
          // Le QR peut contenir une URL d'enrôlement : on n'en garde que le
          // code, pour accepter les deux formes sans casser l'autre.
          Navigator.of(context).pop(_codeDepuis(valeur));
        },
      ),
    );
  }

  String _codeDepuis(String valeur) {
    final brut = valeur.contains('=')
        ? valeur.split('=').last
        : valeur.split('/').last;

    return ApiClient.normaliserCodeEtablissement(brut);
  }
}
