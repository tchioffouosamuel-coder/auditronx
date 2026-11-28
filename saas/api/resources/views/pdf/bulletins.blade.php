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
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.4px; color: #111; }
        .page { position: relative; }
        .saut { page-break-after: always; }
        table { width: 100%; border-collapse: collapse; }
        .grille th, .grille td { border: 0.6px solid #222; padding: 2.4px 3px; }
        .grille th { font-weight: normal; font-size: 7.6px; text-align: center; vertical-align: top; }
        .grille th.g { text-align: left; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .i { font-style: italic; }
        .bloc td { background: #d9d9d9; font-weight: bold; }
        .total td { background: #d9d9d9; font-weight: bold; }
        .total td.normal { font-weight: normal; }
        .rouge { color: #c00000; }
        .manq { background: #fff1c2; }
        h1 { font-size: 13.5px; text-align: center; color: #1f3864; margin: 2px 0 5px; }
        h2 { font-size: 10px; color: #1f3864; margin: 6px 0 3px; }
        .identite td { border: none; padding: 1.8px 0; font-size: 9px; }
        .ligne { font-size: 9px; margin: 2px 0; }
        .legende td { border: none; padding: 1px 0; font-size: 7.6px; vertical-align: top; }
        .signatures { margin-top: 8px; }
        .signatures td { border: none; text-align: center; font-size: 8.6px; height: 46px; vertical-align: top; }
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
