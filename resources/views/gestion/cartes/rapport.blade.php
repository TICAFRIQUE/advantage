<x-layouts.app titre="Rapport des cartes" sous-titre="Back-office · Cartes">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 fw-bold mb-0">Rapport des cartes</h1>
            <p class="text-secondary mb-0">Historique des opérations : activations, suspensions, réactivations, révocations, expirations et modifications du titulaire.</p>
        </div>
        <x-menu-export liste="operations-cartes" formulaire="#filtres-rapport-cartes" tableau="#tableau-rapport-cartes" />
    </div>

    <form method="GET" action="{{ route('gestion.cartes.rapport') }}" id="filtres-rapport-cartes" data-soumission="page"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres du rapport">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-lg-2">
                <label for="du" class="form-label fw-semibold">Opérations du</label>
                <input type="date" id="du" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control @error('du') is-invalid @enderror">
            </div>
            <div class="col-6 col-lg-2">
                <label for="au" class="form-label fw-semibold">au</label>
                <input type="date" id="au" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control @error('au') is-invalid @enderror">
                @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-lg-2">
                <label for="carte" class="form-label fw-semibold">N° de carte</label>
                <input type="text" id="carte" name="carte" value="{{ $filtres['carte'] ?? '' }}" inputmode="numeric" maxlength="9"
                       placeholder="0000000" class="form-control font-monospace @error('carte') is-invalid @enderror">
                @error('carte')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-lg-2">
                <label for="type" class="form-label fw-semibold">Opération</label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                    <option value="">Toutes</option>
                    @foreach (App\Enums\TypeOperationCarte::cases() as $type)
                        <option value="{{ $type->value }}" @selected(($filtres['type'] ?? null) === $type->value)>{{ $type->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="agent_id" class="form-label fw-semibold">Effectuée par</label>
                <select id="agent_id" name="agent_id" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((int) ($filtres['agent_id'] ?? 0) === $agent->id)>{{ $agent->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="mes_operations" name="mes_operations" @checked($filtres['mes_operations'])>
                    <label class="form-check-label small" for="mes_operations">Mes opérations</label>
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary px-4">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-striped align-middle w-100" id="tableau-rapport-cartes"
                   data-source="{{ route('gestion.cartes.rapport.donnees') }}" data-filtres="#filtres-rapport-cartes">
                <thead>
                    <tr>
                        <th scope="col" data-colonne="effectuee_le">Date</th>
                        <th scope="col" data-colonne="operation" data-triable="false">Opération</th>
                        <th scope="col" data-colonne="numero" data-triable="false" data-lien="lien">Carte</th>
                        <th scope="col" data-colonne="titulaire" data-triable="false">Titulaire</th>
                        <th scope="col" data-colonne="telephone" data-triable="false">Téléphone</th>
                        <th scope="col" data-colonne="effectuee_par" data-triable="false">Effectuée par</th>
                        <th scope="col" data-colonne="motif" data-triable="false">Motif / détail</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</x-layouts.app>
