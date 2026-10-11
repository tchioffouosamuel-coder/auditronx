<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Facture {{ $facture->numero }}</title>
    <style>
        body { color: #172b25; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { border-bottom: 2px solid #0f6e49; margin-bottom: 28px; padding-bottom: 14px; }
        .brand { color: #0f6e49; font-size: 22px; font-weight: bold; }
        .muted { color: #64748b; }
        .grid { width: 100%; margin: 24px 0; }
        .grid td { vertical-align: top; width: 50%; }
        table.lines { border-collapse: collapse; margin-top: 32px; width: 100%; }
        .lines th, .lines td { border-bottom: 1px solid #dbe4df; padding: 12px 8px; text-align: left; }
        .lines th { background: #f1f5f3; }
        .amount { font-size: 16px; font-weight: bold; text-align: right !important; }
        .footer { border-top: 1px solid #dbe4df; color: #64748b; margin-top: 48px; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Auditron</div>
        <div class="muted">Facture {{ $facture->numero }}</div>
    </div>

    <table class="grid">
        <tr>
            <td>
                <strong>Facturé à</strong><br>
                {{ $facture->etablissement->nom }}<br>
                {{ $facture->etablissement->ville }}<br>
                {{ $facture->etablissement->contact_email }}
            </td>
            <td style="text-align: right">
                <strong>Date d’émission :</strong> {{ $facture->emise_le?->format('d/m/Y') ?? '—' }}<br>
                <strong>Date d’échéance :</strong> {{ $facture->echeance_le?->format('d/m/Y') ?? '—' }}<br>
                <strong>Statut :</strong> {{ ucfirst($facture->statut) }}
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th>Référence</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Abonnement Auditron{{ $facture->abonnement?->plan?->nom ? ' — ' . $facture->abonnement->plan->nom : '' }}</td>
                <td>{{ $facture->abonnement?->periodicite ?? '—' }}</td>
                <td>{{ number_format((float) $facture->montant, 0, ',', ' ') }} {{ $facture->devise }}</td>
            </tr>
            <tr>
                <td colspan="2" class="amount">Total</td>
                <td class="amount">{{ number_format((float) $facture->montant, 0, ',', ' ') }} {{ $facture->devise }}</td>
            </tr>
        </tbody>
    </table>

    @if ($facture->moyen_paiement || $facture->reference_paiement)
        <p>
            <strong>Paiement :</strong> {{ $facture->moyen_paiement ?? '—' }}
            @if ($facture->reference_paiement) · Référence {{ $facture->reference_paiement }} @endif
            @if ($facture->payee_le) · Reçu le {{ $facture->payee_le->format('d/m/Y') }} @endif
        </p>
    @endif

    @if ($facture->notes)
        <p><strong>Note :</strong> {{ $facture->notes }}</p>
    @endif

    <div class="footer">Document généré par Auditron.</div>
</body>
</html>
