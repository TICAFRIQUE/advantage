<x-layouts.app titre="Partenaires" sous-titre="Back-office · Partenaires">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 fw-bold mb-0">Partenaires</h1>
        @can('create', App\Models\Partenaire::class)
            <a href="{{ route('gestion.partenaires.create') }}" class="btn btn-or">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nouveau partenaire
            </a>
        @endcan
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-partenaires"
                   data-source="{{ route('gestion.partenaires.donnees') }}" data-ordre="asc">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="nom" data-lien="lien">Nom</th>
                        <th scope="col" data-colonne="secteur">Secteur</th>
                        <th scope="col" data-colonne="localisation">Localisation</th>
                        <th scope="col" data-colonne="taux_reduction">Remise</th>
                        <th scope="col" data-colonne="statut_libelle" data-triable="false">Statut</th>
                        <th scope="col" data-colonne="operateurs_count">Opérateurs</th>
                        <th scope="col" data-colonne="transactions_count">Passages</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-layouts.app>
