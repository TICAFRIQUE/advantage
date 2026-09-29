<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\FormatExport;
use App\Exceptions\ExportTropVolumineuxException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\Concerns\DefinitListe;
use App\Http\Requests\Gestion\FiltrerCartesRequest;
use App\Http\Requests\Gestion\FiltrerJournalAuditRequest;
use App\Http\Requests\Gestion\FiltrerPartenairesRequest;
use App\Http\Requests\Gestion\FiltrerRapportCartesRequest;
use App\Http\Requests\Gestion\FiltrerRapportTransactionsRequest;
use App\Http\Requests\Gestion\FiltrerUtilisateursRequest;
use App\Services\Exports\Exporteur;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Export d'une liste. La Form Request de la liste est résolue ici : elle
 * vérifie la permission de consulter la liste et valide ses filtres, en plus
 * de la permission « exporter-donnees » portée par la route.
 */
class ExportController extends Controller
{
    /**
     * @var array<string, class-string<DefinitListe>>
     */
    public const LISTES = [
        'cartes' => FiltrerCartesRequest::class,
        'operations-cartes' => FiltrerRapportCartesRequest::class,
        'partenaires' => FiltrerPartenairesRequest::class,
        'transactions' => FiltrerRapportTransactionsRequest::class,
        'utilisateurs' => FiltrerUtilisateursRequest::class,
        'journal-audit' => FiltrerJournalAuditRequest::class,
    ];

    public function __invoke(Request $request, string $liste, FormatExport $format, Exporteur $exporteur): Response
    {
        /** @var DefinitListe $filtres */
        $filtres = app(self::LISTES[$liste]);

        try {
            return $exporteur->telecharger($filtres->liste(), $format, $request->user());
        } catch (ExportTropVolumineuxException $exception) {
            return back()->with('erreur', $exception->getMessage());
        }
    }
}
