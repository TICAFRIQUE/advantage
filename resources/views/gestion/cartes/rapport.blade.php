<x-layouts.app titre="Rapport des cartes" sous-titre="Back-office · Cartes">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <div class="mb-3">
        <h1 class="h3 fw-bold mb-0">Rapport des cartes</h1>
        <p class="text-secondary mb-0">Historique des opérations : activations, suspensions, réactivations, révocations, expirations et modifications du titulaire.</p>
    </div>

    <form method="GET" action="{{ route('gestion.cartes.rapport') }}" id="filtres-rapport-cartes" data-soumission="page"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres du rapport">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="du" class="form-label fw-semibold">Opérations du</label>
                <input type="date" id="du" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control @error('du') is-invalid @enderror">
            </div>
            <div class="col-6 col-md-2">
                <label for="au" class="form-label fw-semibold">au</label>
                <input type="date" id="au" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control @error('au') is-invalid @enderror">
                @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="type" class="form-label fw-semibold">Opération</label>
                <select id="type" name="type" class="form-select @error('type') is-invalid @enderror">
                    <option value="">Toutes</option>
                    @foreach (App\Enums\TypeOperationCarte::cases() as $type)
                        <option value="{{ $type->value }}" @selected(($filtres['type'] ?? null) === $type->value)>{{ $type->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="agent_id" class="form-label fw-semibold">Effectuée par</label>
                <select id="agent_id" name="agent_id" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((int) ($filtres['agent_id'] ?? 0) === $agent->id)>{{ $agent->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="mes_operations" name="mes_operations" @checked($filtres['mes_operations'])>
                    <label class="form-check-label small" for="mes_operations">Mes opérations</label>
                </div>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl">
            <div class="card border-0 shadow-sm fond-nuit h-100">
                <div class="card-body">
                    <p class="small text-white-50 mb-1"><i class="bi bi-activity me-1" aria-hidden="true"></i>Opérations</p>
                    <p class="h2 fw-bold texte-or mb-0">{{ $indicateurs['total'] }}</p>
                </div>
            </div>
        </div>
        @foreach (App\Enums\TypeOperationCarte::cases() as $type)
            <div class="col-6 col-md-4 col-xl">
                <div class="card border-0 shadow-sm fond-nuit h-100">
                    <div class="card-body">
                        <p class="small text-white-50 mb-1"><i class="bi {{ $type->icone() }} me-1" aria-hidden="true"></i>{{ $type->libelle() }}</p>
                        <p class="h2 fw-bold texte-or mb-0">{{ $indicateurs[$type->value] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

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
