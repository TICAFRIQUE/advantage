<x-layouts.app titre="Historique" sous-titre="Espace partenaire">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <x-bandeau-partenaire :partenaire="$partenaire" />

    <h1 class="h3 fw-bold mb-3">Historique</h1>

    <form class="card card-body shadow-sm border-0 mb-3" id="filtres-transactions" aria-label="Filtrer par période">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="du" class="form-label fw-semibold">Du</label>
                <input type="date" id="du" name="du" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label for="au" class="form-label fw-semibold">Au</label>
                <input type="date" id="au" name="au" class="form-control">
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-transactions"
                   data-source="{{ route('partenaire.historique.donnees') }}" data-filtres="#filtres-transactions">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="validee_le">Date</th>
                        <th scope="col" data-colonne="carte" data-triable="false">Carte</th>
                        <th scope="col" data-colonne="titulaire" data-triable="false">Titulaire</th>
                        <th scope="col" data-colonne="taux_applique">Remise</th>
                        <th scope="col" data-colonne="operateur" data-triable="false">Validée par</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-layouts.app>
