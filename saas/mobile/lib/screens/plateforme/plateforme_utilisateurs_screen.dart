import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../services/plateforme_session.dart';

const _platformRoles = ['super_admin', 'support', 'commercial'];

class PlateformeUtilisateursScreen extends StatefulWidget {
  const PlateformeUtilisateursScreen({super.key});

  @override
  State<PlateformeUtilisateursScreen> createState() =>
      _PlateformeUtilisateursScreenState();
}

class _PlateformeUtilisateursScreenState
    extends State<PlateformeUtilisateursScreen> {
  List<Map<String, dynamic>> _users = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  Future<void> _load() async {
    try {
      final response =
          await PlateformeApiClient.instance.get('/utilisateurs')
              as Map<String, dynamic>;
      final users = (response['data'] as List? ?? const [])
          .whereType<Map>()
          .map((user) => Map<String, dynamic>.from(user))
          .toList();
      if (mounted)
        setState(() {
          _users = users;
          _loading = false;
          _error = null;
        });
    } on ApiException catch (error) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = error.message;
        });
    } catch (error) {
      if (mounted)
        setState(() {
          _loading = false;
          _error = 'Chargement impossible : $error';
        });
    }
  }

  Future<void> _edit([Map<String, dynamic>? user]) async {
    final plateformeSession = context.read<PlateformeSession>();
    final currentId = plateformeSession.userId;
    final isCurrent = user != null && user['id'].toString() == currentId;
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _PlatformUserDialog(user: user, isCurrent: isCurrent),
    );
    if (!mounted || values == null) return;
    await _perform(() async {
      if (user == null) {
        final result =
            await PlateformeApiClient.instance.post('/utilisateurs', values)
                as Map<String, dynamic>;
        final created = result['data'] as Map? ?? const {};
        final password = result['password_initial']?.toString();
        await _load();
        if (password != null && mounted) {
          await _showInitialPassword(created, password);
        }
      } else {
        await PlateformeApiClient.instance.put(
          '/utilisateurs/${user['id']}',
          values,
        );
        if (isCurrent && values.containsKey('password')) {
          await plateformeSession.logout();
        } else {
          await _load();
        }
      }
    });
  }

  Future<void> _toggle(Map<String, dynamic> user) async {
    if (user['id'].toString() == context.read<PlateformeSession>().userId) {
      setState(() => _error = 'Vous ne pouvez pas désactiver votre compte.');
      return;
    }
    await _perform(() async {
      await PlateformeApiClient.instance.put('/utilisateurs/${user['id']}', {
        'actif': user['actif'] != true,
      });
      await _load();
    });
  }

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

  Future<void> _showInitialPassword(Map user, String password) =>
      showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Compte créé'),
          content: SelectableText(
            'Nom : ${user['name'] ?? '—'}\n'
            'E-mail : ${user['email'] ?? '—'}\n'
            'Rôle : ${_roleLabel(user['role'])}\n'
            'Mot de passe initial : $password\n\n'
            'Transmettez-le de manière sécurisée : il ne sera plus affiché.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Fermer'),
            ),
          ],
        ),
      );

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    final activeAdmins = _users
        .where((user) => user['actif'] == true && user['role'] == 'super_admin')
        .length;
    final currentId = context.watch<PlateformeSession>().userId;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          Row(
            children: [
              Expanded(
                child: Text(
                  'Comptes Auditron (${_users.length})',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              FilledButton.icon(
                onPressed: _busy ? null : () => _edit(),
                icon: const Icon(Icons.person_add_alt_1),
                label: const Text('Ajouter'),
              ),
            ],
          ),
          if (_busy) const LinearProgressIndicator(),
          for (final user in _users)
            Card(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 6, 4, 6),
                child: Row(
                  children: [
                    CircleAvatar(
                      child: Text(
                        (user['name']?.toString().trim().isNotEmpty ?? false)
                            ? user['name'].toString().trim()[0].toUpperCase()
                            : '?',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user['name']?.toString() ?? '',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          if (user['id'].toString() == currentId)
                            const Text(
                              'Votre compte',
                              style: TextStyle(
                                fontSize: 10,
                                color: Colors.blueGrey,
                              ),
                            ),
                          Text(
                            user['email']?.toString() ?? '',
                            style: const TextStyle(fontSize: 12),
                          ),
                          Text(
                            '${_roleLabel(user['role'])} · ${user['actif'] == true ? 'Actif' : 'Désactivé'}',
                            style: const TextStyle(fontSize: 11),
                          ),
                          Text(
                            user['tokens_max_last_used_at'] == null
                                ? 'Aucune activité récente'
                                : 'Dernière activité : ${_formatDate(user['tokens_max_last_used_at'])}',
                            style: const TextStyle(
                              fontSize: 10,
                              color: Colors.black54,
                            ),
                          ),
                        ],
                      ),
                    ),
                    PopupMenuButton<String>(
                      onSelected: (value) {
                        if (value == 'edit') _edit(user);
                        if (value == 'toggle') _toggle(user);
                      },
                      itemBuilder: (context) => [
                        const PopupMenuItem(
                          value: 'edit',
                          child: Text('Modifier'),
                        ),
                        PopupMenuItem(
                          value: 'toggle',
                          enabled:
                              user['id'].toString() != currentId &&
                              !(user['actif'] == true &&
                                  user['role'] == 'super_admin' &&
                                  activeAdmins <= 1),
                          child: Text(
                            user['actif'] == true ? 'Désactiver' : 'Réactiver',
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _PlatformUserDialog extends StatefulWidget {
  const _PlatformUserDialog({this.user, required this.isCurrent});
  final Map<String, dynamic>? user;
  final bool isCurrent;
  @override
  State<_PlatformUserDialog> createState() => _PlatformUserDialogState();
}

class _PlatformUserDialogState extends State<_PlatformUserDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _password;
  late String _role;
  late bool _active;
  bool get _editing => widget.user != null;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.user?['name']?.toString() ?? '');
    _email = TextEditingController(
      text: widget.user?['email']?.toString() ?? '',
    );
    _password = TextEditingController();
    _role = widget.user?['role']?.toString() ?? 'support';
    _active = widget.user?['actif'] as bool? ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(_editing ? 'Modifier un compte' : 'Ajouter à l’équipe'),
    content: Form(
      key: _formKey,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'Nom'),
              validator: (value) => value == null || value.trim().isEmpty
                  ? 'Champ obligatoire'
                  : null,
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'E-mail'),
              validator: (value) {
                final email = value?.trim() ?? '';
                return RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)
                    ? null
                    : 'Adresse e-mail invalide';
              },
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _role,
              onChanged: widget.isCurrent
                  ? null
                  : (value) => setState(() => _role = value ?? 'support'),
              decoration: const InputDecoration(labelText: 'Rôle'),
              items: [
                for (final role in _platformRoles)
                  DropdownMenuItem(value: role, child: Text(_roleLabel(role))),
              ],
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _password,
              obscureText: true,
              decoration: InputDecoration(
                labelText: _editing
                    ? 'Nouveau mot de passe (facultatif)'
                    : 'Mot de passe (vide = généré)',
                helperText: _editing ? '12 caractères minimum' : null,
              ),
              validator: (value) {
                if (value == null || value.isEmpty) return null;
                return value.length >= 12 ? null : '12 caractères minimum';
              },
            ),
            if (_editing)
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Compte actif'),
                value: _active,
                onChanged: widget.isCurrent
                    ? null
                    : (value) => setState(() => _active = value),
              ),
          ],
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
          Navigator.pop(context, {
            'name': _name.text.trim(),
            'email': _email.text.trim(),
            'role': _role,
            if (_password.text.isNotEmpty) 'password': _password.text,
            if (_editing) 'actif': _active,
          });
        },
        child: const Text('Enregistrer'),
      ),
    ],
  );
}

String _roleLabel(dynamic role) => switch (role) {
  'super_admin' => 'Super-administrateur',
  'commercial' => 'Commercial',
  _ => 'Support',
};

String _formatDate(dynamic value) {
  final parsed = DateTime.tryParse(value?.toString() ?? '');
  if (parsed == null) return value?.toString() ?? '—';
  return '${parsed.day.toString().padLeft(2, '0')}/'
      '${parsed.month.toString().padLeft(2, '0')}/'
      '${parsed.year}';
}
