import 'package:flutter/material.dart';
import '../services/api_client.dart';

class CoursDuJourScreen extends StatefulWidget {
  const CoursDuJourScreen({super.key});

  @override
  State<CoursDuJourScreen> createState() => _CoursDuJourScreenState();
}

class _CoursDuJourScreenState extends State<CoursDuJourScreen> {
  late Future<Map<String, dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, dynamic>> _load() async {
    return await ApiClient.instance.get('/mes-cours-du-jour')
        as Map<String, dynamic>;
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _toggle(
    Map<String, dynamic> cours,
    Map<String, dynamic> lecon,
  ) async {
    final faite = lecon['faite'] == true;
    setState(() => lecon['faite'] = !faite);

    try {
      await ApiClient.instance.post('/mes-cours-du-jour/lecon-toggle', {
        'emploi_du_temps_id': cours['emploi_du_temps_id'],
        'progression_lecon_id': lecon['id'],
        'date': DateTime.now().toIso8601String().substring(0, 10),
      });
    } catch (_) {
      if (mounted) setState(() => lecon['faite'] = faite);
      rethrow;
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: _refresh,
      child: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('${snapshot.error}'),
                ),
              ],
            );
          }

          final data = snapshot.data!;
          if (data['present'] != true) {
            return ListView(
              children: const [
                Padding(
                  padding: EdgeInsets.all(24),
                  child: Text(
                    'Pointez votre présence pour déclarer les leçons du jour.',
                  ),
                ),
              ],
            );
          }

          final cours = (data['cours'] as List<dynamic>?) ?? [];
          if (cours.isEmpty) {
            return ListView(
              children: const [
                Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('Aucun cours prévu aujourd’hui.'),
                ),
              ],
            );
          }

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: cours.length,
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              final coursDuJour = Map<String, dynamic>.from(
                cours[index] as Map<String, dynamic>,
              );
              final lecons = (coursDuJour['lecons'] as List<dynamic>?) ?? [];
              return Card(
                child: Column(
                  children: [
                    ListTile(
                      title: Text(
                        '${coursDuJour['discipline']} - ${coursDuJour['classe']}',
                      ),
                      subtitle: Text(
                        '${coursDuJour['heure_debut']} - ${coursDuJour['heure_fin']}',
                      ),
                    ),
                    ...lecons.map((item) {
                      final lecon = item as Map<String, dynamic>;
                      return CheckboxListTile(
                        value: lecon['faite'] == true,
                        title: Text('${lecon['ordre']}. ${lecon['titre']}'),
                        subtitle: lecon['unite_apprentissage'] == null
                            ? null
                            : Text('${lecon['unite_apprentissage']}'),
                        onChanged: (_) async {
                          try {
                            await _toggle(coursDuJour, lecon);
                            if (mounted) setState(() {});
                          } catch (error) {
                            if (!context.mounted) return;
                            ScaffoldMessenger.of(
                              context,
                            ).showSnackBar(SnackBar(content: Text('$error')));
                          }
                        },
                      );
                    }),
                    if (lecons.isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'Aucune progression importée pour cette classe et cette matière.',
                        ),
                      ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 0, 8, 8),
                      child: Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(
                          onPressed: () => _openCahierTexteForm(coursDuJour),
                          icon: const Icon(Icons.edit_note, size: 18),
                          label: const Text('Cahier de texte'),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _openCahierTexteForm(Map<String, dynamic> cours) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _CahierTexteForm(cours: cours),
    );
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Entrée enregistrée dans le cahier de texte.')),
      );
    }
  }
}

/// Saisie d'une entrée de cahier de texte par l'enseignant, pour son propre
/// créneau (§pédagogie) — l'admin n'a plus qu'un rôle de visualisation sur ce
/// module, la saisie se fait désormais ici, côté mobile.
class _CahierTexteForm extends StatefulWidget {
  final Map<String, dynamic> cours;

  const _CahierTexteForm({required this.cours});

  @override
  State<_CahierTexteForm> createState() => _CahierTexteFormState();
}

class _CahierTexteFormState extends State<_CahierTexteForm> {
  final _formKey = GlobalKey<FormState>();
  final _contenuCtrl = TextEditingController();
  final _referenceCtrl = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _contenuCtrl.dispose();
    _referenceCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _busy = true);
    try {
      await ApiClient.instance.post('/cahier-texte', {
        'emploi_du_temps_id': widget.cours['emploi_du_temps_id'],
        'date': DateTime.now().toIso8601String().substring(0, 10),
        'contenu': _contenuCtrl.text.trim(),
        if (_referenceCtrl.text.trim().isNotEmpty) 'reference_programme': _referenceCtrl.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 16,
        right: 16,
        top: 16,
        bottom: MediaQuery.of(context).viewInsets.bottom + 16,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Cahier de texte', style: Theme.of(context).textTheme.titleLarge),
              Text(
                '${widget.cours['discipline']} - ${widget.cours['classe']}',
                style: const TextStyle(color: Colors.black54),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _contenuCtrl,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Contenu de la séance *'),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Champ requis' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _referenceCtrl,
                decoration: const InputDecoration(labelText: 'Référence programme'),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _busy ? null : _submit,
                  child: _busy
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Enregistrer'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
