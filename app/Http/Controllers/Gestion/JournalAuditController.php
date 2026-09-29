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
        $f = $request->validated();
        $peutVoirCartes = $request->user()->can(Permission::VoirCartes->value);
        $peutVoirPartenaires = $request->user()->can(Permission::VoirPartenaires->value);

        $requete = JournalAudit::query()->select('journaux_audit.*')->with('acteur.roles')
            ->when($f['du'] ?? null, fn ($q, string $du) => $q->where('cree_le', '>=', $du.' 00:00:00'))
            ->when($f['au'] ?? null, fn ($q, string $au) => $q->where('cree_le', '<=', $au.' 23:59:59'))
            ->when($f['action'] ?? null, fn ($q, string $action) => $q->where('action', $action))
            ->when($f['type_entite'] ?? null, fn ($q, string $type) => $q->where('type_entite', $type))
            ->when($f['acteur'] ?? null, function ($q, string $acteur): void {
                $texte = addcslashes(mb_strtolower(trim($acteur)), '%_\\');
                $q->whereHas('acteur', fn ($a) => $a->withTrashed()->where(fn ($a) => $a
                    ->where('nom_utilisateur', 'like', $texte.'%')
                    ->orWhere('nom', 'like', '%'.$texte.'%')));
            });

        return DataTables::eloquent($requete)
            ->editColumn('cree_le', fn (JournalAudit $j) => $j->cree_le->format('d/m/Y H:i:s'))
            ->addColumn('auteur', fn (JournalAudit $j) => $j->acteur?->libelleActeur()
                ?? ($j->type_acteur === 'systeme' ? 'Système (tâche planifiée)' : 'Anonyme'))
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
            ->addColumn('details', fn (JournalAudit $j) => $j->donnees
                ? '<details><summary class="small">Voir</summary><pre class="small mb-0 journal-details">'
                    .e(json_encode($j->donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    .'</pre></details>'
                : '—')
            // Colonnes HTML : tout contenu y est échappé ci-dessus (e()).
            ->rawColumns(['element', 'details'])
            ->filter(function ($query) use ($request): void {
                $recherche = addcslashes(trim((string) $request->input('search.value')), '%_\\');

                if ($recherche !== '') {
                    $query->where(fn ($q) => $q
                        ->where('action', 'like', "%{$recherche}%")
                        ->orWhere('adresse_ip', 'like', "{$recherche}%"));
                }
            }, true)
            ->toJson();
    }

    public function purger(PurgerJournalAuditRequest $request, PurgerJournalAudit $purger): RedirectResponse
    {
        $nombre = $purger($request->avant(), TypePurge::Manuelle, $request->user(), $request->validated('motif'));

        return redirect()->route('gestion.journal.index')
            ->with('succes', "{$nombre} entrée(s) antérieure(s) au {$request->avant()->format('d/m/Y')} supprimée(s). La purge est inscrite au registre.");
    }
}
