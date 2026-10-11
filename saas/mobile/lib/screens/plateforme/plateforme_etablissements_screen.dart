import 'package:flutter/material.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

const _tenantStatuses = ['en_attente', 'actif', 'suspendu', 'archive'];

class PlateformeEtablissementsScreen extends StatefulWidget {
  const PlateformeEtablissementsScreen({
    super.key,
    required this.estSuperAdmin,
  });

  final bool estSuperAdmin;

  @override
  State<PlateformeEtablissementsScreen> createState() =>
      _PlateformeEtablissementsScreenState();
}

class _PlateformeEtablissementsScreenState
    extends State<PlateformeEtablissementsScreen> {
  List<Map<String, dynamic>> _tenants = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;
  String _filter = '';

  Future<void> _load() async {
    try {
      final response =
          await PlateformeApiClient.instance.get('/etablissements')
              as Map<String, dynamic>;
      final data = (response['data'] as List? ?? const [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList();
      if (mounted) {
        setState(() {
          _tenants = data;
          _loading = false;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted)
        setState(() {
          _error = error.message;
          _loading = false;
        });
    } catch (error) {
      if (mounted)
        setState(() {
          _error = 'Chargement impossible : $error';
          _loading = false;
        });
    }
  }

  Future<void> _create() async {
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _TenantFormDialog(),
    );
    if (values == null) return;
    await _perform(() async {
      final response =
          await PlateformeApiClient.instance.post('/etablissements', values)
              as Map<String, dynamic>;
      await _load();
      final credentials = response['identifiants_direction'];
      if (credentials is Map) {
        await _showCredentials(
          'Identifiants initiaux de la direction',
          credentials,
          response['data']?['code']?.toString() ?? values['code'].toString(),
        );
      }
    });
  }

  Future<void> _showDetails(Map<String, dynamic> tenant) async {
    await _perform(() async {
      final response =
          await PlateformeApiClient.instance.get(
                '/etablissements/${tenant['id']}',
              )
              as Map<String, dynamic>;
      if (!mounted) return;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (_) => _TenantDetailsSheet(
          detail: response,
          estSuperAdmin: widget.estSuperAdmin,
          onEdit: (data) => _edit(data),
          onProvision: (data) => _provision(data),
          onMigrate: (data) => _migrate(data),
        ),
      );
    });
  }

  Future<void> _edit(Map<String, dynamic> tenant) async {
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _TenantFormDialog(tenant: tenant),
    );
    if (values == null) return;
    await _perform(() async {
      await PlateformeApiClient.instance.put(
        '/etablissements/${tenant['id']}',
        values,
      );
      await _load();
    });
  }

  Future<void> _changeStatus(Map<String, dynamic> tenant, String status) async {
    final confirmed = await _confirm(
      'Changer le statut',
      'Passer ${tenant['nom']} au statut « $status » ?',
    );
    if (!confirmed) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post(
        '/etablissements/${tenant['id']}/statut',
        {'statut': status},
      );
      await _load();
    });
  }

  Future<void> _provision(Map<String, dynamic> tenant) async {
    await _perform(() async {
      final response =
          await PlateformeApiClient.instance.post(
                '/etablissements/${tenant['id']}/provision',
              )
              as Map<String, dynamic>;
      await _load();
      final credentials = response['identifiants_direction'];
      if (credentials is Map) {
        await _showCredentials(
          'Identifiants initiaux de la direction',
          credentials,
          response['data']?['code']?.toString() ?? tenant['code'].toString(),
        );
      }
    });
  }

  Future<void> _migrate(Map<String, dynamic> tenant) async {
    final confirmed = await _confirm(
      'Exécuter les migrations',
      'Appliquer les migrations de la base de ${tenant['nom']} ?',
    );
    if (!confirmed) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post(
        '/etablissements/${tenant['id']}/migrate',
      );
      _message('Migrations appliquées.');
    });
  }

  Future<void> _delete(Map<String, dynamic> tenant) async {
    final confirmation = await showDialog<String>(
      context: context,
      builder: (_) => _DeleteTenantDialog(code: tenant['code'].toString()),
    );
    if (confirmation == null) return;
    await _perform(() async {
      await PlateformeApiClient.instance.delete(
        '/etablissements/${tenant['id']}',
        data: {'confirmation': confirmation},
      );
      await _load();
    });
  }

  Future<bool> _confirm(String title, String message) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(title),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Annuler'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Confirmer'),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _perform(Future<void> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on ApiException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } catch (error) {
      if (mounted) setState(() => _error = 'Opération impossible : $error');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showCredentials(
    String title,
    Map credentials,
    String code,
  ) async {
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(title),
        content: SelectableText(
          'Établissement : $code\n'
          'E-mail : ${credentials['email'] ?? '—'}\n'
          'Mot de passe : ${credentials['password'] ?? '—'}\n\n'
          'Conservez ces identifiants : le mot de passe initial ne pourra pas être affiché à nouveau.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Fermer'),
          ),
        ],
      ),
    );
  }

  void _message(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    final filtered = _tenants.where((tenant) {
      final query = _filter.trim().toLowerCase();
      return query.isEmpty ||
          '${tenant['nom']} ${tenant['code']} ${tenant['ville'] ?? ''}'
              .toLowerCase()
              .contains(query);
    }).toList();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_error != null) _ErrorBanner(message: _error!),
          Row(
            children: [
              Expanded(
                child: Text(
                  '${_tenants.length} établissement(s)',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              if (widget.estSuperAdmin)
                FilledButton.icon(
                  onPressed: _busy ? null : _create,
                  icon: const Icon(Icons.add),
                  label: const Text('Ajouter'),
                ),
            ],
          ),
          const SizedBox(height: 12),
          TextField(
            onChanged: (value) => setState(() => _filter = value),
            decoration: const InputDecoration(
              prefixIcon: Icon(Icons.search),
              hintText: 'Rechercher par nom, code ou ville',
            ),
          ),
          const SizedBox(height: 8),
          if (_busy) const LinearProgressIndicator(),
          if (filtered.isEmpty)
            const Padding(
              padding: EdgeInsets.all(24),
              child: Text('Aucun établissement trouvé.'),
            ),
          for (final tenant in filtered)
            Card(
              child: ListTile(
                onTap: () => _showDetails(tenant),
                leading: const CircleAvatar(
                  backgroundColor: AuditronColors.brand100,
                  child: Icon(Icons.apartment, color: AuditronColors.brand700),
                ),
                title: Text(
                  tenant['nom']?.toString() ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                subtitle: Text(
                  '${tenant['code']} · ${tenant['ville'] ?? 'Ville non renseignée'}\n'
                  '${tenant['abonnement_actif']?['plan']?['nom'] ?? 'Sans plan'}'
                  '${tenant['abonnement_actif']?['fin_le'] == null ? '' : ' · échéance ${tenant['abonnement_actif']['fin_le'].toString().substring(0, 10)}'}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                isThreeLine: true,
                trailing: widget.estSuperAdmin
                    ? PopupMenuButton<String>(
                        onSelected: (value) {
                          if (_tenantStatuses.contains(value)) {
                            _changeStatus(tenant, value);
                          } else if (value == 'provisionner') {
                            _provision(tenant);
                          } else if (value == 'migrer') {
                            _migrate(tenant);
                          } else if (value == 'supprimer') {
                            _delete(tenant);
                          }
                        },
                        itemBuilder: (context) => [
                          for (final status in _tenantStatuses)
                            PopupMenuItem(
                              value: status,
                              child: Text('Statut : $status'),
                            ),
                          if (tenant['provisionne_le'] == null)
                            const PopupMenuItem(
                              value: 'provisionner',
                              child: Text('Provisionner'),
                            ),
                          if (tenant['provisionne_le'] != null)
                            const PopupMenuItem(
                              value: 'migrer',
                              child: Text('Exécuter les migrations'),
                            ),
                          const PopupMenuItem(
                            value: 'supprimer',
                            child: Text(
                              'Supprimer définitivement',
                              style: TextStyle(color: Colors.red),
                            ),
                          ),
                        ],
                      )
                    : const Icon(Icons.chevron_right),
              ),
            ),
        ],
      ),
    );
  }
}

class _TenantDetailsSheet extends StatelessWidget {
  const _TenantDetailsSheet({
    required this.detail,
    required this.estSuperAdmin,
    required this.onEdit,
    required this.onProvision,
    required this.onMigrate,
  });

  final Map<String, dynamic> detail;
  final bool estSuperAdmin;
  final Future<void> Function(Map<String, dynamic>) onEdit;
  final Future<void> Function(Map<String, dynamic>) onProvision;
  final Future<void> Function(Map<String, dynamic>) onMigrate;

  @override
  Widget build(BuildContext context) {
    final tenant = Map<String, dynamic>.from(detail['data'] as Map);
    final usage = detail['usage'] as Map? ?? const {};
    final subscription = tenant['abonnement_actif'] as Map? ?? const {};
    return SafeArea(
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: .75,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
          children: [
            Text(
              tenant['nom']?.toString() ?? '',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            Text('${tenant['code']} · ${tenant['statut']}'),
            const Divider(height: 28),
            _DetailRow(label: 'Ville', value: tenant['ville']),
            _DetailRow(label: 'Contact', value: tenant['contact_nom']),
            _DetailRow(label: 'E-mail', value: tenant['contact_email']),
            _DetailRow(label: 'Téléphone', value: tenant['contact_tel']),
            _DetailRow(
              label: 'Plan actif',
              value: subscription['plan']?['nom'],
            ),
            _DetailRow(label: 'Fin abonnement', value: subscription['fin_le']),
            const SizedBox(height: 10),
            Text('Usage', style: Theme.of(context).textTheme.titleMedium),
            _DetailRow(label: 'Personnel', value: usage['personnel']),
            _DetailRow(
              label: 'Appareils actifs',
              value: usage['devices_actifs'],
            ),
            _DetailRow(
              label: 'Pointages sur 30 jours',
              value: usage['presences_30j'],
            ),
            _DetailRow(
              label: 'Dernier pointage',
              value: usage['derniere_presence'],
            ),
            const SizedBox(height: 12),
            if (estSuperAdmin)
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  OutlinedButton.icon(
                    onPressed: () => onEdit(tenant),
                    icon: const Icon(Icons.edit_outlined),
                    label: const Text('Modifier la fiche'),
                  ),
                  if (tenant['provisionne_le'] == null)
                    OutlinedButton.icon(
                      onPressed: () => onProvision(tenant),
                      icon: const Icon(Icons.settings_outlined),
                      label: const Text('Provisionner'),
                    )
                  else
                    OutlinedButton.icon(
                      onPressed: () => onMigrate(tenant),
                      icon: const Icon(Icons.upgrade_outlined),
                      label: const Text('Migrer la base'),
                    ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}

class _TenantFormDialog extends StatefulWidget {
  const _TenantFormDialog({this.tenant});
  final Map<String, dynamic>? tenant;

  @override
  State<_TenantFormDialog> createState() => _TenantFormDialogState();
}

class _TenantFormDialogState extends State<_TenantFormDialog> {
  final _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _controllers;
  bool _provision = true;

  bool get _editing => widget.tenant != null;

  @override
  void initState() {
    super.initState();
    const keys = [
      'code',
      'nom',
      'nom_court',
      'ville',
      'pays',
      'contact_nom',
      'contact_email',
      'contact_tel',
      'direction_email',
      'db_username',
      'db_password',
      'couleur_primaire',
      'couleur_secondaire',
    ];
    _controllers = {
      for (final key in keys)
        key: TextEditingController(text: widget.tenant?[key]?.toString() ?? ''),
    };
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  String _label(String key) => switch (key) {
    'nom' => 'Nom complet',
    'code' => 'Code établissement',
    'nom_court' => 'Nom court',
    'ville' => 'Ville',
    'pays' => 'Pays',
    'contact_nom' => 'Contact — nom',
    'contact_email' => 'Contact — e-mail',
    'contact_tel' => 'Contact — téléphone',
    'direction_email' => 'E-mail du compte direction',
    'db_username' => 'Utilisateur de base de données',
    'db_password' => 'Nouveau mot de passe de base (facultatif)',
    'couleur_primaire' => 'Couleur principale',
    _ => 'Couleur secondaire',
  };

  @override
  Widget build(BuildContext context) {
    final keys = _editing
        ? [
            'nom',
            'nom_court',
            'ville',
            'pays',
            'contact_nom',
            'contact_email',
            'contact_tel',
            'db_username',
            'db_password',
            'couleur_primaire',
            'couleur_secondaire',
          ]
        : [
            'code',
            'nom',
            'nom_court',
            'ville',
            'pays',
            'contact_nom',
            'contact_email',
            'contact_tel',
            'direction_email',
            'couleur_primaire',
          ];

    return AlertDialog(
      title: Text(_editing ? 'Modifier la fiche' : 'Ouvrir un établissement'),
      content: SizedBox(
        width: 460,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                for (final key in keys)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: TextFormField(
                      controller: _controllers[key],
                      obscureText: key == 'db_password',
                      keyboardType: key.endsWith('email')
                          ? TextInputType.emailAddress
                          : TextInputType.text,
                      textCapitalization: key == 'code'
                          ? TextCapitalization.characters
                          : TextCapitalization.sentences,
                      validator: key == 'nom' || key == 'code'
                          ? (value) => value == null || value.trim().isEmpty
                                ? 'Champ obligatoire'
                                : null
                          : null,
                      decoration: InputDecoration(labelText: _label(key)),
                    ),
                  ),
                if (!_editing)
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Provisionner la base maintenant'),
                    subtitle: const Text(
                      'Créer les tables et le compte direction',
                    ),
                    value: _provision,
                    onChanged: (value) => setState(() => _provision = value),
                  ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Annuler'),
        ),
        FilledButton(
          onPressed: () {
            if (!_formKey.currentState!.validate()) return;
            final values = <String, dynamic>{};
            for (final key in keys) {
              final value = _controllers[key]!.text.trim();
              if (key == 'db_password') {
                if (value.isNotEmpty) values[key] = value;
              } else {
                values[key] = value.isEmpty ? null : value;
              }
            }
            if (!_editing) values['provisionner'] = _provision;
            Navigator.pop(context, values);
          },
          child: Text(_editing ? 'Enregistrer' : 'Créer'),
        ),
      ],
    );
  }
}

class _DeleteTenantDialog extends StatefulWidget {
  const _DeleteTenantDialog({required this.code});
  final String code;

  @override
  State<_DeleteTenantDialog> createState() => _DeleteTenantDialogState();
}

class _DeleteTenantDialogState extends State<_DeleteTenantDialog> {
  final _controller = TextEditingController();
  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Suppression définitive'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('La base et les fichiers de ${widget.code} seront supprimés.'),
        const SizedBox(height: 12),
        TextField(
          controller: _controller,
          decoration: InputDecoration(labelText: 'Retaper ${widget.code}'),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Annuler'),
      ),
      FilledButton(
        style: FilledButton.styleFrom(backgroundColor: Colors.red),
        onPressed: () => Navigator.pop(context, _controller.text.trim()),
        child: const Text('Supprimer'),
      ),
    ],
  );
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, this.value});
  final String label;
  final dynamic value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      children: [
        SizedBox(
          width: 140,
          child: Text(
            label,
            style: const TextStyle(color: AuditronColors.ink500),
          ),
        ),
        Expanded(child: Text(value?.toString() ?? '—')),
      ],
    ),
  );
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(message, style: const TextStyle(color: Colors.red)),
  );
}
