import 'dart:io';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../services/api_client.dart';
import '../../services/plateforme_api_client.dart';
import '../../theme.dart';

class PlateformeFacturesScreen extends StatefulWidget {
  const PlateformeFacturesScreen({super.key, required this.estSuperAdmin});
  final bool estSuperAdmin;

  @override
  State<PlateformeFacturesScreen> createState() =>
      _PlateformeFacturesScreenState();
}

class _PlateformeFacturesScreenState extends State<PlateformeFacturesScreen> {
  List<Map<String, dynamic>> _invoices = [];
  List<Map<String, dynamic>> _tenants = [];
  List<Map<String, dynamic>> _subscriptions = [];
  bool _loading = true;
  bool _busy = false;
  String? _error;

  Future<void> _load() async {
    try {
      final responses = await Future.wait([
        PlateformeApiClient.instance.get('/factures'),
        PlateformeApiClient.instance.get('/etablissements'),
        PlateformeApiClient.instance.get('/abonnements'),
      ]);
      List<Map<String, dynamic>> rows(dynamic result) =>
          ((result as Map<String, dynamic>)['data'] as List? ?? const [])
              .whereType<Map>()
              .map((row) => Map<String, dynamic>.from(row))
              .toList();
      if (mounted) {
        setState(() {
          _invoices = rows(responses[0]);
          _tenants = rows(responses[1]);
          _subscriptions = rows(responses[2]);
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

  Future<void> _emit() async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) =>
          _InvoiceDialog(tenants: _tenants, subscriptions: _subscriptions),
    );
    if (payload == null) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post('/factures', payload);
      await _load();
    });
  }

  Future<void> _markPaid(Map<String, dynamic> invoice) async {
    final payment = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _PaymentDialog(invoice: invoice),
    );
    if (payment == null) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post(
        '/factures/${invoice['id']}/payee',
        payment,
      );
      await _load();
    });
  }

  Future<void> _cancel(Map<String, dynamic> invoice) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Annuler la facture'),
        content: Text(
          'Annuler ${invoice['numero']} ? Une facture déjà réglée ne peut pas être annulée.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Retour'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Annuler la facture'),
          ),
        ],
      ),
    );
    if (confirm != true) return;
    await _perform(() async {
      await PlateformeApiClient.instance.post(
        '/factures/${invoice['id']}/annule',
      );
      await _load();
    });
  }

  Future<void> _download(Map<String, dynamic> invoice) async {
    await _perform(() async {
      final bytes = await PlateformeApiClient.instance.facturePdf(
        invoice['id'].toString(),
      );
      final directory = await getTemporaryDirectory();
      final file = File('${directory.path}/facture-${invoice['numero']}.pdf');
      await file.writeAsBytes(bytes, flush: true);
      await Share.shareXFiles([
        XFile(file.path, mimeType: 'application/pdf'),
      ], subject: 'Facture ${invoice['numero']}');
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
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
                  '${_invoices.length} facture(s)',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              if (widget.estSuperAdmin)
                FilledButton.icon(
                  onPressed: _busy ? null : _emit,
                  icon: const Icon(Icons.add),
                  label: const Text('Émettre'),
                ),
            ],
          ),
          if (_busy) const LinearProgressIndicator(),
          if (_invoices.isEmpty)
            const Padding(
              padding: EdgeInsets.all(28),
              child: Text('Aucune facture.'),
            ),
          for (final invoice in _invoices)
            Card(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            invoice['numero']?.toString() ?? 'Facture',
                            style: const TextStyle(fontWeight: FontWeight.w800),
                          ),
                        ),
                        _StatusChip(
                          status: invoice['statut']?.toString() ?? '',
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(invoice['etablissement']?['nom']?.toString() ?? '—'),
                    const SizedBox(height: 4),
                    Text(
                      '${_money(invoice['montant'])} ${invoice['devise'] ?? 'XAF'}',
                      style: const TextStyle(
                        color: AuditronColors.brand700,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      'Émise le ${_date(invoice['emise_le'])}'
                      '${invoice['echeance_le'] == null ? '' : ' · échéance ${_date(invoice['echeance_le'])}'}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: AuditronColors.ink500,
                      ),
                    ),
                    if (invoice['statut'] == 'payee')
                      Text(
                        'Réglée le ${_date(invoice['payee_le'])}'
                        '${invoice['moyen_paiement'] == null ? '' : ' · ${_paymentLabel(invoice['moyen_paiement'])}'}'
                        '${invoice['reference_paiement'] == null ? '' : ' · Réf. ${invoice['reference_paiement']}'}',
                        style: const TextStyle(
                          fontSize: 11,
                          color: AuditronColors.ink500,
                        ),
                      ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 4,
                      children: [
                        TextButton.icon(
                          onPressed: _busy ? null : () => _download(invoice),
                          icon: const Icon(
                            Icons.picture_as_pdf_outlined,
                            size: 18,
                          ),
                          label: const Text('PDF'),
                        ),
                        if (widget.estSuperAdmin &&
                            invoice['statut'] == 'emise') ...[
                          TextButton.icon(
                            onPressed: _busy ? null : () => _markPaid(invoice),
                            icon: const Icon(Icons.payments_outlined, size: 18),
                            label: const Text('Règlement'),
                          ),
                          TextButton.icon(
                            onPressed: _busy ? null : () => _cancel(invoice),
                            icon: const Icon(Icons.cancel_outlined, size: 18),
                            label: const Text('Annuler'),
                            style: TextButton.styleFrom(
                              foregroundColor: Colors.red,
                            ),
                          ),
                        ],
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

class _InvoiceDialog extends StatefulWidget {
  const _InvoiceDialog({required this.tenants, required this.subscriptions});
  final List<Map<String, dynamic>> tenants;
  final List<Map<String, dynamic>> subscriptions;

  @override
  State<_InvoiceDialog> createState() => _InvoiceDialogState();
}

class _InvoiceDialogState extends State<_InvoiceDialog> {
  final _amount = TextEditingController();
  String? _tenantId;
  String? _subscriptionId;
  DateTime? _dueDate;
  String _currency = 'XAF';

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Émettre une facture'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _tenantId,
            decoration: const InputDecoration(labelText: 'Établissement'),
            items: [
              for (final tenant in widget.tenants)
                DropdownMenuItem(
                  value: tenant['id'].toString(),
                  child: Text(
                    '${tenant['nom']} (${tenant['code']})',
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
            ],
            onChanged: (value) => setState(() {
              _tenantId = value;
              _subscriptionId = null;
            }),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: _subscriptionId,
            decoration: const InputDecoration(
              labelText: 'Abonnement (facultatif)',
            ),
            items: [
              for (final subscription in widget.subscriptions)
                if (_tenantId == null ||
                    subscription['etablissement_id']?.toString() == _tenantId)
                  DropdownMenuItem(
                    value: subscription['id'].toString(),
                    child: Text(
                      '${subscription['etablissement']?['nom'] ?? 'Établissement'} · ${subscription['plan']?['nom'] ?? 'Plan'}',
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
            ],
            onChanged: (value) => setState(() => _subscriptionId = value),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(labelText: 'Montant'),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: _currency,
            decoration: const InputDecoration(labelText: 'Devise'),
            items: const [
              DropdownMenuItem(value: 'XAF', child: Text('XAF')),
              DropdownMenuItem(value: 'EUR', child: Text('EUR')),
              DropdownMenuItem(value: 'USD', child: Text('USD')),
            ],
            onChanged: (value) => setState(() => _currency = value ?? 'XAF'),
          ),
          TextButton.icon(
            onPressed: () async {
              final date = await showDatePicker(
                context: context,
                initialDate:
                    _dueDate ?? DateTime.now().add(const Duration(days: 30)),
                firstDate: DateTime(2020),
                lastDate: DateTime(2100),
                locale: const Locale('fr'),
              );
              if (date != null) setState(() => _dueDate = date);
            },
            icon: const Icon(Icons.event_outlined),
            label: Text(
              _dueDate == null
                  ? 'Ajouter une échéance'
                  : 'Échéance : ${_date(_dueDate)}',
            ),
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Retour'),
      ),
      FilledButton(
        onPressed: () {
          final amount = double.tryParse(_amount.text.replaceAll(',', '.'));
          if (_tenantId == null || amount == null || amount < 0) return;
          Navigator.pop(context, {
            'etablissement_id': _tenantId,
            if (_subscriptionId != null) 'abonnement_id': _subscriptionId,
            'montant': amount,
            'devise': _currency,
            if (_dueDate != null) 'echeance_le': _isoDate(_dueDate!),
          });
        },
        child: const Text('Émettre'),
      ),
    ],
  );
}

class _PaymentDialog extends StatefulWidget {
  const _PaymentDialog({required this.invoice});
  final Map<String, dynamic> invoice;

  @override
  State<_PaymentDialog> createState() => _PaymentDialogState();
}

class _PaymentDialogState extends State<_PaymentDialog> {
  final _reference = TextEditingController();
  DateTime _paidAt = DateTime.now();
  String _method = 'mobile_money';

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text('Règlement ${widget.invoice['numero']}'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          '${_money(widget.invoice['montant'])} ${widget.invoice['devise']} · '
          '${widget.invoice['etablissement']?['nom'] ?? ''}',
        ),
        const SizedBox(height: 10),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Date du règlement'),
          subtitle: Text(_date(_paidAt)),
          trailing: const Icon(Icons.calendar_month_outlined),
          onTap: () async {
            final selected = await showDatePicker(
              context: context,
              initialDate: _paidAt,
              firstDate: DateTime(2020),
              lastDate: DateTime.now().add(const Duration(days: 1)),
              locale: const Locale('fr'),
            );
            if (selected != null) setState(() => _paidAt = selected);
          },
        ),
        DropdownButtonFormField<String>(
          initialValue: _method,
          decoration: const InputDecoration(labelText: 'Moyen de paiement'),
          items: const [
            DropdownMenuItem(
              value: 'mobile_money',
              child: Text('Mobile Money'),
            ),
            DropdownMenuItem(
              value: 'virement',
              child: Text('Virement bancaire'),
            ),
            DropdownMenuItem(value: 'especes', child: Text('Espèces')),
            DropdownMenuItem(value: 'cheque', child: Text('Chèque')),
          ],
          onChanged: (value) => setState(() => _method = value ?? _method),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _reference,
          decoration: const InputDecoration(
            labelText: 'Référence de transaction (facultatif)',
          ),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Retour'),
      ),
      FilledButton(
        onPressed: () => Navigator.pop(context, {
          'payee_le': _isoDate(_paidAt),
          'moyen_paiement': _method,
          if (_reference.text.trim().isNotEmpty)
            'reference_paiement': _reference.text.trim(),
        }),
        child: const Text('Confirmer'),
      ),
    ],
  );
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;
  @override
  Widget build(BuildContext context) {
    final color = switch (status) {
      'payee' => Colors.green,
      'annulee' => Colors.red,
      _ => AuditronColors.gold600,
    };
    return Chip(
      label: Text(status),
      side: BorderSide.none,
      backgroundColor: color.withValues(alpha: .12),
      labelStyle: TextStyle(color: color, fontSize: 11),
      visualDensity: VisualDensity.compact,
    );
  }
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

String _money(dynamic value) => NumberFormat(
  '#,##0.##',
  'fr_FR',
).format(value is num ? value : num.tryParse(value?.toString() ?? '') ?? 0);

String _date(dynamic value) {
  if (value == null) return '—';
  final date = value is DateTime ? value : DateTime.tryParse(value.toString());
  return date == null
      ? value.toString()
      : DateFormat('dd/MM/yyyy').format(date);
}

String _isoDate(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-'
    '${date.month.toString().padLeft(2, '0')}-'
    '${date.day.toString().padLeft(2, '0')}';

String _paymentLabel(dynamic method) => switch (method) {
  'mobile_money' => 'Mobile Money',
  'virement' => 'Virement',
  'especes' => 'Espèces',
  'cheque' => 'Chèque',
  _ => method.toString(),
};
