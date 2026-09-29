<x-layouts.app titre="Partenaires" sous-titre="Back-office · Partenaires">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 fw-bold mb-0">Partenaires</h1>
        <x-menu-export liste="partenaires" formulaire="#filtres-partenaires" tableau="#tableau-partenaires" class="ms-auto" />
        @can('create', App\Models\Partenaire::class)
            <a href="{{ route('gestion.partenaires.create') }}" class="btn btn-or">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nouveau partenaire
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('gestion.partenaires.index') }}" id="filtres-partenaires"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres des partenaires">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="partenaire_id" class="form-label fw-semibold">Partenaire</label>
                <select id="partenaire_id" name="partenaire_id" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($partenaires as $choix)
                        <option value="{{ $choix->id }}" @selected((int) ($filtres['partenaire_id'] ?? 0) === $choix->id)>{{ $choix->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="statut" class="form-label fw-semibold">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="">Tous</option>
                    @foreach (App\Enums\StatutPartenaire::cases() as $statut)
                        <option value="{{ $statut->value }}" @selected(($filtres['statut'] ?? null) === $statut->value)>{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-partenaires"
                   data-source="{{ route('gestion.partenaires.donnees') }}" data-filtres="#filtres-partenaires" data-ordre="asc">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="nom" data-lien="lien">Nom</th>
                        <th scope="col" data-colonne="secteur">Secteur</th>
                        <th scope="col" data-colonne="localisation">Localisation</th>
                        <th scope="col" data-colonne="taux_reduction">Remise</th>
                        <th scope="col" data-colonne="statut_libelle" data-triable="false">Statut</th>
                        <th scope="col" data-colonne="operateurs_count">Utilisateurs</th>
                        <th scope="col" data-colonne="transactions_count">Passages</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-layouts.app>
