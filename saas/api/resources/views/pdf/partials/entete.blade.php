{{--
    En-tête officiel de l'établissement, commun à tous les PDF.

    Les lignes viennent de la configuration (§4.2) et non plus du code : le
    bandeau était écrit en dur, ce qui imprimait le nom d'un établissement sur
    les documents de tous les autres. Rien n'est imprimé tant que
    l'administration n'a rien renseigné — un cartouche vide vaut moins qu'un
    titre seul.
--}}
@php($entete = app(\App\Services\EnteteEtablissement::class)->colonnes())

@if ($entete['gauche'] || $entete['droite'])
    <table style="border:none;margin-bottom:8px">
        <tr>
            <td style="border:none;width:40%;text-align:center;font-size:8px">
                {!! implode('<br>', array_map('e', $entete['gauche'])) !!}
            </td>
            <td style="border:none;width:20%;text-align:center">
                @if (file_exists(public_path('logo.png')))
                    <img src="{{ public_path('logo.png') }}" alt="Logo" style="height:40px">
                @endif
            </td>
            <td style="border:none;width:40%;text-align:center;font-size:8px">
                {!! implode('<br>', array_map('e', $entete['droite'])) !!}
            </td>
        </tr>
    </table>
    @if ($entete['contact'] !== '')
        <div style="text-align:center;font-size:7px;margin-bottom:6px">{{ $entete['contact'] }}</div>
    @endif
@endif
