<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\LitPeriodeConseil;
use App\Http\Controllers\Controller;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Services\ImpressionBulletin;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Impression des bulletins et du PV de conseil (§bulletins APC, lot 6).
 *
 * Tout s'imprime depuis l'instantané du lot 5 — voir ImpressionBulletin. Un
 * brouillon s'imprime aussi, barré « BROUILLON » : le conseil relit sur
 * papier avant de valider.
 */
class BulletinPdfController extends Controller
{
    use LitPeriodeConseil;

    /** GET /api/bulletins/{bulletin}/pdf — un élève. */
    public function bulletin(Bulletin $bulletin)
    {
        $bulletin->loadMissing('inscription.eleve');
        $matricule = $bulletin->inscription?->eleve?->matricule ?? (string) $bulletin->inscription_id;

        return ImpressionBulletin::pdfBulletins(collect([$bulletin]))
            ->download(ImpressionBulletin::nomFichier('bulletin', $matricule, $bulletin->periode, $bulletin->annee_scolaire));
    }

    /** GET /api/bulletins/classe/{classe}/pdf?periode=&annee= — toute la classe, une page par élève. */
    public function classe(Request $request, Classe $classe)
    {
        [$annee, $periode] = $this->periodeEtAnnee($request);
        $bulletins = $this->courants($classe, $annee, $periode);

        if ($bulletins->isEmpty()) {
            return $this->rien($classe);
        }

        return ImpressionBulletin::pdfBulletins($bulletins)
            ->download(ImpressionBulletin::nomFichier('bulletins', $classe->code, $periode, $annee));
    }

    /** GET /api/bulletins/classe/{classe}/pv?periode=&annee= — procès-verbal du conseil. */
    public function pv(Request $request, Classe $classe)
    {
        [$annee, $periode] = $this->periodeEtAnnee($request);
        $bulletins = $this->courants($classe, $annee, $periode);

        if ($bulletins->isEmpty()) {
            return $this->rien($classe);
        }

        return ImpressionBulletin::pdfPv($classe, $annee, $periode, $bulletins)
            ->download(ImpressionBulletin::nomFichier('pv-conseil', $classe->code, $periode, $annee));
    }

    /**
     * GET /api/bulletins/export-zip?periode=&annee=
     *
     * Un dossier par classe ayant des bulletins **validés**, avec les
     * bulletins et le PV. Les brouillons n'y entrent pas : une archive de
     * fin de trimestre part aux familles et aux archives, pas en relecture.
     *
     * Rendu synchrone : une quarantaine de classes demande quelques minutes.
     * Au-delà, il faudra une file d'attente (voir BULLETINS-APC.md).
     */
    public function zip(Request $request)
    {
        [$annee, $periode] = $this->periodeEtAnnee($request);

        $parClasse = Bulletin::query()
            ->where('annee_scolaire', $annee)
            ->where('periode', $periode)
            ->courants()
            ->where('statut', '!=', Bulletin::STATUT_BROUILLON)
            ->with(['lignes', 'inscription.eleve', 'classe.specialiteApc'])
            ->get()
            ->groupBy('classe_id');

        if ($parClasse->isEmpty()) {
            return response()->json(['message' => 'Aucun bulletin validé pour cette période.'], 404);
        }

        @set_time_limit(0);

        $chemin = tempnam(sys_get_temp_dir(), 'bulletins');
        $zip = new ZipArchive;
        $zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($parClasse as $bulletins) {
            $classe = $bulletins->first()->classe;
            $code = $bulletins->first()->classe_code ?? $classe?->code ?? (string) $bulletins->first()->classe_id;
            $dossier = Str::slug($code) ?: 'classe-'.$bulletins->first()->classe_id;

            $zip->addFromString(
                $dossier.'/'.ImpressionBulletin::nomFichier('bulletins', $code, $periode, $annee),
                ImpressionBulletin::pdfBulletins($bulletins)->output(),
            );

            if ($classe !== null) {
                $zip->addFromString(
                    $dossier.'/'.ImpressionBulletin::nomFichier('pv-conseil', $code, $periode, $annee),
                    ImpressionBulletin::pdfPv($classe, $annee, $periode, $bulletins)->output(),
                );
            }
        }

        $zip->close();

        $nom = Str::slug('bulletins-'.($periode === Bulletin::PERIODE_ANNUELLE ? 'annuel' : 'T'.$periode).'-'.str_replace('/', '-', $annee)).'.zip';

        return response()->download($chemin, $nom, ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    /** @return Collection<int, Bulletin> */
    private function courants(Classe $classe, string $annee, string $periode): Collection
    {
        return Bulletin::duConseil($classe->id, $annee, $periode)->courants()->get();
    }

    private function rien(Classe $classe)
    {
        return response()->json([
            'message' => "Aucun bulletin calculé pour la classe {$classe->code} à cette période : passez d'abord par le conseil de classe.",
        ], 404);
    }
}
