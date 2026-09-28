<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Carte;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableauDeBordController extends Controller
{
    public function __invoke(Request $request): View
    {
        $agentId = $request->user()->id;

        $indicateurs = Carte::query()
            ->selectRaw('SUM(active_par_id = ? AND active_le >= ?) AS mes_activations_du_jour', [$agentId, today()])
            ->selectRaw('SUM(active_par_id = ?) AS mes_activations', [$agentId])
            ->selectRaw('SUM(active_le >= ?) AS activations_du_jour', [today()])
            ->first();

        $dernieres = Carte::query()
            ->with(['titulaire', 'activePar'])
            ->where('active_par_id', $agentId)
            ->latest('active_le')
            ->limit(3)
            ->get();

        return view('agent.tableau-de-bord', [
            'indicateurs' => [
                'mes_activations_du_jour' => (int) $indicateurs->mes_activations_du_jour,
                'mes_activations' => (int) $indicateurs->mes_activations,
                'activations_du_jour' => (int) $indicateurs->activations_du_jour,
            ],
            'dernieres' => $dernieres,
        ]);
    }
}
