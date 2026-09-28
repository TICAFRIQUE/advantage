<x-layouts.app titre="Rapport des transactions" sous-titre="Back-office · Partenaires">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <h1 class="h3 fw-bold mb-3">Rapport des transactions</h1>

    <form method="GET" action="{{ route('gestion.transactions.rapport') }}" id="filtres-rapport-transactions"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres du rapport">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="du" class="form-label fw-semibold">Du</label>
                <input type="date" id="du" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="au" class="form-label fw-semibold">Au</label>
                <input type="date" id="au" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control @error('au') is-invalid @enderror">
                @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-2">
                <label for="carte" class="form-label fw-semibold">N° de carte</label>
                <input type="text" id="carte" name="carte" value="{{ $filtres['carte'] ?? '' }}" inputmode="numeric" maxlength="9"
                       placeholder="0000000" class="form-control font-monospace @error('carte') is-invalid @enderror">
                @error('carte')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-3">
                <label for="partenaire_id" class="form-label fw-semibold">Partenaire</label>
                <select id="partenaire_id" name="partenaire_id" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($partenaires as $choix)
                        <option value="{{ $choix->id }}" @selected((int) ($filtres['partenaire_id'] ?? 0) === $choix->id)>{{ $choix->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 d-grid">
                <button type="submit" class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Passages', $indicateurs['passages'], 'bi-receipt'],
            ['Cartes distinctes', $indicateurs['cartes_distinctes'], 'bi-wallet2'],
            ['Partenaires concernés', $indicateurs['partenaires_distincts'], 'bi-shop'],
            ['Remise moyenne', $indicateurs['taux_moyen'], 'bi-percent'],
        ] as [$libelle, $valeur, $icone])
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm fond-nuit h-100">
                    <div class="card-body">
                        <p class="small text-white-50 mb-1"><i class="bi {{ $icone }} me-1" aria-hidden="true"></i>{{ $libelle }}</p>
                        <p class="h2 fw-bold texte-or mb-0">{{ $valeur }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-rapport-transactions"
                   data-source="{{ route('gestion.transactions.rapport.donnees') }}" data-filtres="#filtres-rapport-transactions">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="validee_le">Date</th>
                        <th scope="col" data-colonne="partenaire" data-triable="false">Partenaire</th>
                        <th scope="col" data-colonne="carte" data-triable="false" data-lien="lien_carte">Carte</th>
                        <th scope="col" data-colonne="titulaire" data-triable="false">Titulaire</th>
                        <th scope="col" data-colonne="taux_applique">Remise</th>
                        <th scope="col" data-colonne="valide_par" data-triable="false">Validée par</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-layouts.app>
