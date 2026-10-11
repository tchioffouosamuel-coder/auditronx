import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

class PlateformePresencesScreen extends StatefulWidget {
  const PlateformePresencesScreen({super.key});

  @override
  State<PlateformePresencesScreen> createState() =>
      _PlateformePresencesScreenState();
}

class _PlateformePresencesScreenState extends State<PlateformePresencesScreen> {
  List<Map<String, dynamic>> _tenants = [];
  Map<String, dynamic>? _report;
  DateTime _start = DateTime.now();
  DateTime _end = DateTime.now();
  String? _tenant;
  bool _withLines = false;
  bool _loading = false;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final query = <String, String>{
        'date_debut': _isoDate(_start),
        'date_fin': _isoDate(_end),
        if (_withLines) 'avec_lignes': '1',
        if (_tenant != null) 'etablissement': _tenant!,
      };
      final results = await Future.wait([
        PlateformeApiClient.instance.get('/presences', query: query),
        if (_tenants.isEmpty)
          PlateformeApiClient.instance.get('/etablissements'),
      ]);
      final response = results.first as Map<String, dynamic>;
      if (mounted) {
        setState(() {
          _report = response;
          if (_tenants.isEmpty && results.length > 1) {
            final tenants = (results[1]['data'] as List? ?? const [])
                .whereType<Map>()
                .map((row) => Map<String, dynamic>.from(row))
                .toList();
            _tenants = tenants;
          }
          _loading = false;
        });
      }
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

  Future<void> _pickDate(bool isStart) async {
    final selected = await showDatePicker(
      context: context,
      initialDate: isStart ? _start : _end,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      locale: const Locale('fr'),
    );
    if (selected == null) return;
    setState(() {
      if (isStart) {
        _start = selected;
        if (_end.isBefore(_start)) _end = _start;
      } else {
        _end = selected;
        if (_start.isAfter(_end)) _start = _end;
      }
    });
    _load();
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final establishments = (_report?['etablissements'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    final totals = _report?['totaux'] as Map? ?? const {};
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
          Text(
            'Relevé consolidé des pointages',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _loading ? null : () => _pickDate(true),
                  icon: const Icon(Icons.calendar_today_outlined, size: 16),
                  label: Text('Du ${_date(_start)}'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _loading ? null : () => _pickDate(false),
                  icon: const Icon(Icons.event_outlined, size: 16),
                  label: Text('Au ${_date(_end)}'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<String?>(
            initialValue: _tenant,
            decoration: const InputDecoration(
              labelText: 'Tous les établissements',
            ),
            items: [
              const DropdownMenuItem<String?>(
                value: null,
                child: Text('Tous les établissements'),
              ),
              for (final tenant in _tenants)
                DropdownMenuItem(
                  value: tenant['code'].toString(),
                  child: Text(
                    '${tenant['nom']} (${tenant['code']})',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
            ],
            onChanged: (value) {
              setState(() => _tenant = value);
              _load();
            },
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Afficher le détail nominatif'),
            subtitle: const Text('Jusqu’à 500 pointages par établissement'),
            value: _withLines,
            onChanged: _loading
                ? null
                : (value) {
                    setState(() => _withLines = value);
                    _load();
                  },
          ),
          if (_loading) const LinearProgressIndicator(),
          if (_report != null) ...[
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _Metric(
                  label: 'Établissements',
                  value: totals['etablissements'],
                ),
                _Metric(label: 'Effectif', value: totals['effectif']),
                _Metric(label: 'Pointages', value: totals['pointages']),
                _Metric(label: 'Arrivées', value: totals['arrivees']),
                _Metric(label: 'Sans départ', value: totals['sans_depart']),
                if ((totals['injoignables'] as num? ?? 0) > 0)
                  _Metric(
                    label: 'Injoignables',
                    value: totals['injoignables'],
                    alert: true,
                  ),
              ],
            ),
            const SizedBox(height: 10),
            if (establishments.isEmpty)
              const Text('Aucun établissement provisionné.')
            else
              for (final row in establishments) _TenantReportCard(row: row),
          ],
        ],
      ),
    );
  }
}

class _TenantReportCard extends StatelessWidget {
  const _TenantReportCard({required this.row});
  final Map row;
  @override
  Widget build(BuildContext context) {
    final unavailable = row['injoignable'] == true;
    final lines = (row['lignes'] as List? ?? const [])
        .whereType<Map>()
        .toList();
    return Card(
      child: ExpansionTile(
        leading: Icon(
          unavailable ? Icons.cloud_off_outlined : Icons.apartment_outlined,
          color: unavailable ? Colors.deepOrange : AuditronColors.brand700,
        ),
        title: Text(row['nom']?.toString() ?? ''),
        subtitle: Text(
          unavailable
              ? '${row['code']} · Base injoignable'
              : '${row['code']} · ${row['pointages']} pointage(s) · ${row['sans_depart']} sans départ',
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
        children: [
          if (unavailable)
            const Text('Impossible de joindre la base de cet établissement.')
          else if (row['lignes'] == null)
            const Text('Activez le détail nominatif pour afficher les lignes.')
          else if (lines.isEmpty)
            const Text('Aucun pointage pour cette période.')
          else ...[
            if (row['lignes_tronquees'] == true)
              const Text(
                'Liste limitée aux 500 premiers pointages.',
                style: TextStyle(color: Colors.deepOrange, fontSize: 11),
              ),
            for (final line in lines)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text(line['nom']?.toString() ?? 'Personnel'),
                subtitle: Text(
                  '${line['matricule'] ?? '—'} · ${line['section'] ?? '—'} · ${line['date'] ?? ''}',
                ),
                trailing: Text(
                  '${line['heure_arrivee'] ?? '—'}\n${line['heure_depart'] ?? '—'}',
                  textAlign: TextAlign.end,
                ),
              ),
          ],
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, this.alert = false});
  final String label;
  final dynamic value;
  final bool alert;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    decoration: BoxDecoration(
      color: alert ? Colors.orange.shade50 : AuditronColors.brand50,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Column(
      children: [
        Text(
          value?.toString() ?? '—',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: alert ? Colors.deepOrange : AuditronColors.brand800,
          ),
        ),
        Text(label, style: const TextStyle(fontSize: 10)),
      ],
    ),
  );
}

String _date(DateTime value) => DateFormat('dd/MM').format(value);
String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';
