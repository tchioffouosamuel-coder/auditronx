{{--
    Bulletins de compétences (§bulletins APC, lot 6) — un élève par page.

    Les données arrivent mises en forme par ImpressionBulletin::page() : la
    vue n'arrondit, ne compte et ne compare rien. Ce qui s'imprime est ce qui
    a été figé au calcul.

    DejaVu Sans plutôt qu'Arial : dompdf remplace Arial par Helvetica, qui n'a
    ni « ≥ », ni les cases ☐ ☒ de la synthèse.
--}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Bulletins de compétences</title>
    <style>
        @page { margin: 9mm 9mm 8mm 9mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.6px; color: #111; }
        .page { position: relative; }
        .saut { page-break-after: always; }
        table { width: 100%; border-collapse: collapse; }
        .grille th, .grille td { border: 0.6px solid #333; padding: 2.6px 2.5px; }
        .grille th { background: #e4e8ee; font-size: 7.6px; text-align: center; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .bloc td { background: #f1f3f6; font-weight: bold; font-size: 8px; }
        .total td { background: #e4e8ee; font-weight: bold; }
        .rouge { color: #b00020; }
        .manq { background: #fff1c2; }
        h1 { font-size: 13px; text-align: center; margin: 2px 0 1px; letter-spacing: .3px; }
        .sous-titre { text-align: center; font-size: 8.5px; margin-bottom: 4px; }
        .identite td { border: none; padding: 1.5px 2px; font-size: 9px; }
        .cadre { border: 0.6px solid #333; padding: 3px 4px; vertical-align: top; }
        .case { font-size: 9px; }
        .legende td { border: none; padding: 0 4px 0 0; font-size: 7.2px; }
        .signatures td { border: none; text-align: center; font-size: 8.6px; height: 48px; vertical-align: top; }
        .filigrane {
            position: absolute; top: 330px; left: 40px; width: 620px;
            text-align: center; font-size: 64px; font-weight: bold;
            color: #c8102e; opacity: 0.13; transform: rotate(-35deg);
        }
    </style>
</head>

<body>
    @foreach ($pages as $page)
        <div class="page {{ $loop->last ? '' : 'saut' }}">
            @if ($page['filigrane'])
                <div class="filigrane">{{ $page['filigrane'] }}</div>
            @endif

            @include('pdf.partials.entete')
            @include('pdf.partials.bulletin-identite', ['page' => $page])

            @if ($page['annuel'])
                @include('pdf.bulletin-annuel', ['page' => $page])
            @else
                @include('pdf.bulletin-trimestre', ['page' => $page])
            @endif

            @include('pdf.partials.bulletin-synthese', ['page' => $page])
        </div>
    @endforeach
</body>

</html>
