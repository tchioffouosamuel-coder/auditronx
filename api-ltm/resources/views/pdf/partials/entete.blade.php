{{--
    Entête officiel bilingue commun à tous les PDF générés (§4.4).
    Les libellés viennent de `config/etablissement.php` : aucun nom
    d'établissement ne doit être écrit en dur dans une vue.
--}}
<table style="border:none;margin-bottom:8px;width:100%">
    <tr>
        <td style="border:none;width:40%;text-align:center;font-size:8px">
            {{ config('etablissement.pays') }}<br>
            {{ config('etablissement.devise') }}<br>
            {{ config('etablissement.nom') }}
        </td>
        <td style="border:none;width:20%;text-align:center">
            @if (file_exists(public_path(config('etablissement.logo'))))
                <img src="{{ public_path(config('etablissement.logo')) }}" alt="Logo" style="height:40px">
            @endif
        </td>
        <td style="border:none;width:40%;text-align:center;font-size:8px">
            {{ config('etablissement.pays_en') }}<br>
            {{ config('etablissement.devise_en') }}<br>
            {{ config('etablissement.nom_en') }}
        </td>
    </tr>
</table>
