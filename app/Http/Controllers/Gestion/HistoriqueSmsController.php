<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\FiltrerHistoriqueSmsRequest;
use App\Models\MessageSms;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Système › Historique des SMS (superadmin) : tous les envois (codes de
 * validation, alertes d'expiration, tests) avec leur statut, pour vérifier
 * que la file d'attente et le fournisseur fonctionnent. Lecture seule ; le
 * contenu des messages n'est jamais affiché.
 */
class HistoriqueSmsController extends Controller
{
    /**
     * Au-delà, un SMS encore « en attente » signale un worker arrêté.
     */
    public const ATTENTE_ANORMALE_MINUTES = 5;

    public function index(FiltrerHistoriqueSmsRequest $request): View
    {
        return view('gestion.outils.historique-sms', [
            'filtres' => $request->validated(),
            'types' => TypeSms::cases(),
            'statuts' => StatutLivraison::cases(),
            'indicateurs' => $this->indicateurs(),
            'file' => $this->fileAttente(),
            'conserver' => (int) config('plateforme.retention.messages_sms_conserver', 50),
            'pilote' => (string) config('plateforme.sms.driver'),
        ]);
    }

    public function donnees(FiltrerHistoriqueSmsRequest $request): JsonResponse
    {
        $liste = $request->liste();

        return DataTables::eloquent($liste->requete())
            ->editColumn('created_at', fn (MessageSms $m) => $m->created_at->format('d/m/Y H:i:s'))
            ->editColumn('telephone', fn (MessageSms $m) => $m->telephoneFormate())
            ->editColumn('type', fn (MessageSms $m) => $m->type->libelle())
            ->editColumn('statut', fn (MessageSms $m) => self::statut($m))
            ->editColumn('envoye_le', fn (MessageSms $m) => $m->envoye_le?->format('d/m/Y H:i:s') ?? '—')
            // Jamais transmis au navigateur (chiffré en base, code OTP compris).
            ->removeColumn('contenu')
            // Colonne HTML : tout contenu y est échappé (e()).
            ->rawColumns(['statut'])
            ->filter(function ($query) use ($request, $liste): void {
                $recherche = trim((string) $request->input('search.value'));

                if ($recherche !== '') {
                    $liste->rechercher($query, $recherche);
                }
            }, true)
            ->toJson();
    }

    /**
     * Pastille de statut, suivie de l'erreur ou de la référence du fournisseur.
     */
    private static function statut(MessageSms $message): string
    {
        $classe = match ($message->statut) {
            StatutLivraison::Envoyee => 'text-bg-success',
            StatutLivraison::Echec => 'text-bg-danger',
            StatutLivraison::EnAttente => 'text-bg-secondary',
        };

        $html = '<span class="badge '.$classe.'">'.e($message->statut->libelle()).'</span>';

        if (filled($message->erreur)) {
            return $html.'<span class="d-block small text-danger text-break">'.e($message->erreur).'</span>';
        }

        return filled($message->reference_fournisseur)
            ? $html.'<span class="d-block small text-secondary font-monospace text-break">'.e($message->reference_fournisseur).'</span>'
            : $html;
    }

    /**
     * @return array{en_attente: int, bloques: int, envoyes: int, echecs: int}
     */
    private function indicateurs(): array
    {
        $depuis = now()->subDay();

        $ligne = MessageSms::query()
            ->selectRaw('SUM(CASE WHEN statut = ? THEN 1 ELSE 0 END) AS en_attente', [StatutLivraison::EnAttente->value])
            ->selectRaw('SUM(CASE WHEN statut = ? AND created_at < ? THEN 1 ELSE 0 END) AS bloques', [StatutLivraison::EnAttente->value, now()->subMinutes(self::ATTENTE_ANORMALE_MINUTES)])
            ->selectRaw('SUM(CASE WHEN statut = ? AND created_at >= ? THEN 1 ELSE 0 END) AS envoyes', [StatutLivraison::Envoyee->value, $depuis])
            ->selectRaw('SUM(CASE WHEN statut = ? AND created_at >= ? THEN 1 ELSE 0 END) AS echecs', [StatutLivraison::Echec->value, $depuis])
            ->toBase()->first();

        return [
            'en_attente' => (int) $ligne->en_attente,
            'bloques' => (int) $ligne->bloques,
            'envoyes' => (int) $ligne->envoyes,
            'echecs' => (int) $ligne->echecs,
        ];
    }

    /**
     * Tâches en file et tâches en échec (file « database » uniquement).
     *
     * @return array{en_file: int, en_echec: int}|null
     */
    private function fileAttente(): ?array
    {
        if (config('queue.default') !== 'database') {
            return null;
        }

        return [
            'en_file' => DB::table(config('queue.connections.database.table', 'jobs'))->count(),
            'en_echec' => DB::table(config('queue.failed.table', 'failed_jobs'))->count(),
        ];
    }
}
