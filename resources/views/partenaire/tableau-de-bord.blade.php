<x-layouts.app titre="Tableau de bord" sous-titre="Espace partenaire">
    <h1 class="h3 fw-bold mb-3">Tableau de bord</h1>

    @if ($partenaire)
        <x-bandeau-partenaire :partenaire="$partenaire" />

        @can('effectuer-transaction')
            <a href="{{ route('partenaire.transaction.verifier') }}" class="btn btn-or btn-lg w-100 py-3 mb-4 fs-5">
                <i class="bi bi-upc-scan me-2" aria-hidden="true"></i>Nouvelle transaction
            </a>
        @endcan

        <div class="row g-3 mb-4">
            @foreach ([['Passages aujourd\'hui', $indicateurs['aujourd_hui']], ['Passages ce mois-ci', $indicateurs['ce_mois']]] as [$libelle, $valeur])
                <div class="col-6">
                    <div class="card border-0 shadow-sm fond-nuit h-100">
                        <div class="card-body">
                            <p class="small text-white-50 mb-1">{{ $libelle }}</p>
                            <p class="display-6 fw-bold texte-or mb-0">{{ $valeur }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @can('voir-historique-transactions')
            <a href="{{ route('partenaire.historique.index') }}" class="btn btn-outline-primary btn-lg w-100">
                <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Historique
            </a>
        @endcan
    @else
        <div class="alert alert-warning" role="alert">Aucun partenaire actif n'est associé à ce compte.</div>
    @endif
</x-layouts.app>
