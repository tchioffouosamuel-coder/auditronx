import 'package:flutter/material.dart';

import '../../services/api_client.dart';
import '../../services/borne_config_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

/// Lecture et écriture de la mémoire d'une borne, par son point d'accès WiFi.
///
/// Deux niveaux de droits :
///
///  - un compte d'établissement ne change que le réseau WiFi du site. C'est
///    le besoin courant : le lycée change de box ou de mot de passe, et la
///    borne devient muette.
///  - un compte Auditron peut en plus changer l'établissement et le token,
///    avec une confirmation explicite pour chacun.
///
/// Ce partage est une convention de cette application, **pas une barrière de
/// sécurité** : la borne est hors ligne sur son propre réseau et ne peut
/// vérifier aucun compte. Quiconque est à portée avec le mot de passe du
/// point d'accès peut écrire n'importe quel champ. La seule protection réelle
/// est la fenêtre d'ouverture du point d'accès, qui se referme quelques
/// minutes après la mise sous tension.
class AdminBorneConfigScreen extends StatefulWidget {
  const AdminBorneConfigScreen({super.key});

  @override
  State<AdminBorneConfigScreen> createState() => _AdminBorneConfigScreenState();
}

class _AdminBorneConfigScreenState extends State<AdminBorneConfigScreen> {
  final _ssid = TextEditingController();
  final _motDePasse = TextEditingController();
  final _tenant = TextEditingController();
  final _token = TextEditingController();
  final _label = TextEditingController();

  BorneEtat? _etat;
  bool _niveauAuditron = false;
  bool _chargement = false;
  bool _envoi = false;
  String? _erreur;

  @override
  void initState() {
    super.initState();
    _determinerNiveau();
  }

  Future<void> _determinerNiveau() async {
    final auditron = await PlateformeApiClient.instance.isLoggedIn;
    if (mounted) setState(() => _niveauAuditron = auditron);
  }

  Future<void> _lire() async {
    setState(() {
      _chargement = true;
      _erreur = null;
    });

    try {
      final etat = await BorneConfigClient.instance.lireEtat();
      if (!mounted) return;
      setState(() {
        _etat = etat;
        // Pré-remplissage du libellé seulement : réécrire le SSID à
        // l'identique ne sert à rien, et pré-remplir un mot de passe qu'on n'a
        // pas lu serait un mensonge.
        _label.text = etat.label;
        _tenant.text = etat.tenant;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e.message);
    } finally {
      if (mounted) setState(() => _chargement = false);
    }
  }

  /// Confirmation séparée pour l'établissement et le token : ni l'un ni
  /// l'autre ne se corrige tout seul.
  Future<bool> _confirmerSensible() async {
    final tenantChange =
        _tenant.text.trim().isNotEmpty &&
        _tenant.text.trim() != (_etat?.tenant ?? '');
    final tokenChange = _token.text.trim().isNotEmpty;

    if (!tenantChange && !tokenChange) return true;

    final file = _etat?.fileEnAttente ?? 0;

    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Confirmer la modification'),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              if (tenantChange) ...[
                Text(
                  'Établissement : ${_etat?.tenant ?? '—'} → ${_tenant.text.trim()}',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Les pointages seront désormais écrits dans la base de ce '
                  'nouvel établissement.',
                ),
                if (file > 0) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$file pointage(s) attendent encore d’être envoyés. '
                      'Ils partiraient vers la nouvelle base, y seraient '
                      'refusés, puis supprimés définitivement. Laissez '
                      'd’abord la borne se synchroniser.',
                      style: TextStyle(
                        color: Colors.red.shade700,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ],
              if (tokenChange) ...[
                if (tenantChange) const SizedBox(height: 12),
                const Text(
                  'Token',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Un token erroné rend la borne muette : elle n’est plus '
                  'acceptée par l’API et il faut revenir sur site.',
                ),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Confirmer'),
          ),
        ],
      ),
    );

    return ok ?? false;
  }

  Future<void> _envoyer() async {
    final ssid = _ssid.text.trim();
    final motDePasse = _motDePasse.text;

    if (ssid.isEmpty != motDePasse.isEmpty) {
      setState(
        () => _erreur =
            'Renseignez le réseau et le mot de passe ensemble : changer l’un '
            'sans l’autre laisse la borne incapable de se connecter.',
      );
      return;
    }

    final tenant = _niveauAuditron ? _tenant.text.trim() : '';
    final token = _niveauAuditron ? _token.text.trim() : '';
    final tenantChange = tenant.isNotEmpty && tenant != (_etat?.tenant ?? '');

    if (ssid.isEmpty &&
        !tenantChange &&
        token.isEmpty &&
        _label.text.trim().isEmpty) {
      setState(() => _erreur = 'Aucune modification à envoyer.');
      return;
    }

    if (!await _confirmerSensible()) return;

    setState(() {
      _envoi = true;
      _erreur = null;
    });

    try {
      final champs = await BorneConfigClient.instance.ecrire(
        ssid: ssid.isEmpty ? null : ssid,
        motDePasseWifi: motDePasse.isEmpty ? null : motDePasse,
        tenant: tenantChange ? tenant : null,
        token: token.isEmpty ? null : token,
        label: _label.text.trim().isEmpty ? null : _label.text.trim(),
        confirmeSensible: tenantChange || token.isNotEmpty,
      );

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${champs.join(', ')} enregistré(s). La borne redémarre : son '
            'point d’accès va disparaître.',
          ),
          duration: const Duration(seconds: 6),
        ),
      );

      setState(() {
        _etat = null;
        _token.clear();
        _motDePasse.clear();
        _ssid.clear();
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _erreur = e.message);
    } finally {
      if (mounted) setState(() => _envoi = false);
    }
  }

  @override
  void dispose() {
    _ssid.dispose();
    _motDePasse.dispose();
    _tenant.dispose();
    _token.dispose();
    _label.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Configurer une borne')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _Encart(
            icone: Icons.wifi_tethering,
            titre: 'Connectez le téléphone à la borne',
            children: [
              Text(
                'Dans les réglages WiFi du téléphone, rejoignez le réseau '
                '« ${BorneConfigClient.ssidAp} ». Il n’est ouvert que quelques '
                'minutes après la mise sous tension de la borne — si vous ne le '
                'voyez pas, coupez puis rétablissez son alimentation.',
              ),
              const SizedBox(height: 6),
              const Text(
                'Ce réseau n’a pas d’internet : c’est normal, le téléphone '
                'parle directement à la borne.',
                style: TextStyle(color: AuditronColors.ink500, fontSize: 12),
              ),
            ],
          ),
          const SizedBox(height: 12),

          FilledButton.icon(
            onPressed: _chargement ? null : _lire,
            icon: const Icon(Icons.link),
            label: Text(_chargement ? 'Lecture…' : 'Lire la borne'),
          ),

          if (_erreur != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                _erreur!,
                style: TextStyle(
                  color: Colors.red.shade700,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],

          if (_etat != null) ...[
            const SizedBox(height: 16),
            _FicheEtat(etat: _etat!),
            const SizedBox(height: 16),

            const Text(
              'Réseau WiFi du site',
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _ssid,
              decoration: const InputDecoration(
                labelText: 'Nouveau réseau',
                hintText: 'laisser vide = inchangé',
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _motDePasse,
              decoration: const InputDecoration(
                labelText: 'Mot de passe',
                hintText: 'laisser vide = inchangé',
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _label,
              decoration: const InputDecoration(labelText: 'Emplacement'),
            ),

            if (_niveauAuditron) ...[
              const SizedBox(height: 20),
              Row(
                children: [
                  const Icon(
                    Icons.admin_panel_settings,
                    size: 18,
                    color: AuditronColors.gold600,
                  ),
                  const SizedBox(width: 6),
                  const Expanded(
                    child: Text(
                      'Réservé à Auditron',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _tenant,
                textCapitalization: TextCapitalization.characters,
                decoration: const InputDecoration(
                  labelText: 'Code de l’établissement',
                  helperText: 'Désigne la base où vont les pointages',
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _token,
                decoration: const InputDecoration(
                  labelText: 'Nouveau token',
                  hintText: 'laisser vide = inchangé',
                  helperText: 'Émis par « Déclarer une borne » au backoffice',
                ),
              ),
            ] else ...[
              const SizedBox(height: 16),
              const Text(
                'L’établissement et le token ne sont modifiables qu’avec un '
                'compte Auditron.',
                style: TextStyle(color: AuditronColors.ink500, fontSize: 12),
              ),
            ],

            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _envoi ? null : _envoyer,
              icon: const Icon(Icons.save),
              label: Text(_envoi ? 'Envoi…' : 'Écrire dans la borne'),
            ),
            const SizedBox(height: 8),
            const Text(
              'La borne redémarre après l’écriture : son point d’accès se '
              'referme et la liste ci-dessus disparaît.',
              style: TextStyle(color: AuditronColors.ink500, fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }
}

class _FicheEtat extends StatelessWidget {
  final BorneEtat etat;

  const _FicheEtat({required this.etat});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  etat.provisionne ? Icons.check_circle : Icons.error_outline,
                  size: 20,
                  color: etat.provisionne
                      ? AuditronColors.brand600
                      : AuditronColors.gold600,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    etat.provisionne
                        ? 'Borne en service'
                        : 'Borne non configurée',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
            const Divider(height: 18),
            _Ligne('Établissement', etat.tenant.isEmpty ? '—' : etat.tenant),
            _Ligne('Token', etat.tokenMasque),
            _Ligne('Réseau WiFi', etat.ssid.isEmpty ? '—' : etat.ssid),
            _Ligne('Libellé', etat.label),
            _Ligne('Adresse Bluetooth', etat.adresseBle),
            _Ligne('Firmware', etat.firmware),
            _Ligne('Stockage', etat.stockage),
            // La file commande la procédure de changement d'établissement.
            _Ligne(
              'Pointages en attente',
              '${etat.fileEnAttente}',
              alerte: etat.fileEnAttente > 0,
            ),
          ],
        ),
      ),
    );
  }
}

class _Ligne extends StatelessWidget {
  final String libelle;
  final String valeur;
  final bool alerte;

  const _Ligne(this.libelle, this.valeur, {this.alerte = false});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 128,
            child: Text(
              libelle,
              style: const TextStyle(
                color: AuditronColors.ink500,
                fontSize: 12,
              ),
            ),
          ),
          Expanded(
            child: Text(
              valeur,
              style: TextStyle(
                fontWeight: FontWeight.w600,
                color: alerte ? Colors.red.shade700 : AuditronColors.ink900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Encart extends StatelessWidget {
  final IconData icone;
  final String titre;
  final List<Widget> children;

  const _Encart({
    required this.icone,
    required this.titre,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AuditronColors.brand50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AuditronColors.brand100),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icone, size: 20, color: AuditronColors.brand700),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  titre,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ...children,
        ],
      ),
    );
  }
}
