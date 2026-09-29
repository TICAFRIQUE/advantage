<?php

namespace App\Http\Controllers\Gestion;

use App\Actions\Audit\PurgerJournalAudit;
use App\Enums\Permission;
use App\Enums\TypePurge;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\FiltrerJournalAuditRequest;
use App\Http\Requests\Gestion\PurgerJournalAuditRequest;
use App\Models\JournalAudit;
use App\Models\PurgeJournalAudit;
use App\Services\Listes\ListeJournalAudit;
use App\Support\LibellesAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Paramètres › Journal d'audit : consultation (lecture seule, champs
 * sensibles déjà retirés à l'écriture), registre des purges et purge
 * manuelle motivée (PurgerJournalAudit, seul chemin de suppression).
 */
class JournalAuditController extends Controller
{
    public function index(FiltrerJournalAuditRequest $request): View
    {
        return view('gestion.journal.index', [
            'filtres' => $request->validated(),
            'purges' => PurgeJournalAudit::query()->with('purgePar.roles')->latest('cree_le')->latest('id')
                ->paginate(10, pageName: 'page_purges'),
            'peutPurger' => $request->user()->can(Permission::PurgerJournalAudit->value),
            'retention' => (int) config('plateforme.journal_audit.retention_jours'),
        ]);
    }

    public function donnees(FiltrerJournalAuditRequest $request): JsonResponse
    {
        $peutVoirCartes = $request->user()->can(Permission::VoirCartes->value);
        $peutVoirPartenaires = $request->user()->can(Permission::VoirPartenaires->value);

        $liste = $request->liste();

        return DataTables::eloquent($liste->requete())
            ->editColumn('cree_le', fn (JournalAudit $j) => $j->cree_le->format('d/m/Y H:i:s'))
            ->addColumn('auteur', fn (JournalAudit $j) => ListeJournalAudit::auteur($j))
            ->addColumn('action_libelle', fn (JournalAudit $j) => LibellesAudit::action($j->action))
            ->addColumn('element', function (JournalAudit $j) use ($peutVoirCartes, $peutVoirPartenaires): string {
                $libelle = LibellesAudit::entite($j->type_entite);

                if ($libelle === null) {
                    return '—';
                }

                $texte = e("{$libelle} #{$j->entite_id}");
                $lien = match (true) {
                    $j->type_entite === 'Carte' && $peutVoirCartes => route('gestion.cartes.show', $j->entite_id),
                    $j->type_entite === 'Partenaire' && $peutVoirPartenaires => route('gestion.partenaires.show', $j->entite_id),
                    default => null,
                };

                return $lien ? '<a href="'.e($lien).'">'.$texte.'</a>' : $texte;
            })
            ->editColumn('adresse_ip', fn (JournalAudit $j) => $j->adresse_ip ?? '—')
            ->addColumn('details', fn (JournalAudit $j) => self::details($j))
            // Colonnes HTML : tout contenu y est échappé ci-dessus (e()).
            ->rawColumns(['element', 'details'])
            ->filter(function ($query) use ($request, $liste): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche !== '') {
                    $liste->rechercher($query, $recherche);
                }
            }, true)
            ->toJson();
    }

    /**
     * Détail lisible (champ : valeur, ou avant → après), toutes valeurs échappées.
     */
    private static function details(JournalAudit $entree): string
    {
        $lignes = LibellesAudit::details($entree->donnees);

        if ($lignes === []) {
            return '—';
        }

        $html = '<dl class="journal-details mb-0">';

        foreach ($lignes as $ligne) {
            $html .= '<dt>'.e($ligne[0]).'</dt><dd>'.(count($ligne) === 3
                ? '<span class="journal-details__avant">'.e($ligne[1]).'</span> <i class="bi bi-arrow-right" aria-label="devient"></i> <span class="journal-details__apres">'.e($ligne[2]).'</span>'
                : e($ligne[1])).'</dd>';
        }

        return $html.'</dl>';
    }

    public function purger(PurgerJournalAuditRequest $request, PurgerJournalAudit $purger): RedirectResponse
    {
        $nombre = $purger($request->avant(), TypePurge::Manuelle, $request->user(), $request->validated('motif'));

        return redirect()->route('gestion.journal.index')
            ->with('succes', "{$nombre} entrée(s) antérieure(s) au {$request->avant()->format('d/m/Y')} supprimée(s). La purge est inscrite au registre.");
    }
}
