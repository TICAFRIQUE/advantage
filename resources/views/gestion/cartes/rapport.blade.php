<x-layouts.app titre="Rapport des cartes" sous-titre="Back-office · Cartes">
    @push('scripts')
        @vite('resources/js/tableaux.js')
    @endpush

    <h1 class="h3 fw-bold mb-3">Rapport des cartes</h1>

    <form method="GET" action="{{ route('gestion.cartes.rapport') }}" id="filtres-rapport-cartes"
          class="card card-body shadow-sm border-0 mb-4" aria-label="Filtres du rapport">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="du" class="form-label fw-semibold">Activées du</label>
                <input type="date" id="du" name="du" value="{{ $filtres['du'] ?? '' }}" class="form-control @error('du') is-invalid @enderror">
            </div>
            <div class="col-6 col-md-2">
                <label for="au" class="form-label fw-semibold">au</label>
                <input type="date" id="au" name="au" value="{{ $filtres['au'] ?? '' }}" class="form-control @error('au') is-invalid @enderror">
                @error('au')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label for="agent_id" class="form-label fw-semibold">Activée par</label>
                <select id="agent_id" name="agent_id" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}" @selected((int) ($filtres['agent_id'] ?? 0) === $agent->id)>{{ $agent->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="statut" class="form-label fw-semibold">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="">Tous</option>
                    @foreach (App\Enums\StatutCarte::cases() as $statut)
                        @continue($statut === App\Enums\StatutCarte::NonActivee)
                        <option value="{{ $statut->value }}" @selected(($filtres['statut'] ?? null) === $statut->value)>{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="mes_activations" name="mes_activations" @checked($filtres['mes_activations'])>
                    <label class="form-check-label small" for="mes_activations">Mes activations</label>
                </div>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Appliquer</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Cartes (périmètre)', $indicateurs['total'], 'bi-collection'],
            ['Actives', $indicateurs['actives'], 'bi-check-circle'],
            ['Expirent sous 30 jours', $indicateurs['expirent_sous_30_jours'], 'bi-hourglass-split'],
            ['Suspendues', $indicateurs['suspendues'], 'bi-pause-circle'],
            ['Révoquées', $indicateurs['revoquees'], 'bi-x-octagon'],
            ['Expirées', $indicateurs['expirees'], 'bi-calendar-x'],
        ] as [$libelle, $valeur, $icone])
            <div class="col-6 col-md-4 col-xl-2">
                <div class="card border-0 shadow-sm fond-nuit h-100">
                    <div class="card-body">
                        <p class="small text-white-50 mb-1"><i class="bi {{ $icone }} me-1" aria-hidden="true"></i>{{ $libelle }}</p>
                        <p class="h2 fw-bold texte-or mb-0">{{ $valeur }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-3">
            <section class="card border-0 shadow-sm h-100" aria-labelledby="titre-par-agent">
                <div class="card-body">
                    <h2 class="h6 fw-bold" id="titre-par-agent">Activations par agent</h2>
                    <ol class="list-unstyled mb-0 small">
                        @forelse ($parAgent as $ligne)
                            <li class="d-flex justify-content-between border-bottom py-2">
                                <span class="text-truncate me-2">{{ $ligne->agent }}</span>
                                <span class="fw-bold">{{ $ligne->total }}</span>
                            </li>
                        @empty
                            <li class="text-secondary">Aucune activation sur ce périmètre.</li>
                        @endforelse
                    </ol>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-9">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <table class="table table-striped align-middle w-100" id="tableau-rapport-cartes"
                           data-source="{{ route('gestion.cartes.rapport.donnees') }}" data-filtres="#filtres-rapport-cartes">
                        <thead>
                            <tr>
                                <th scope="col" data-colonne="numero" data-triable="false" data-lien="lien">Carte</th>
                                <th scope="col" data-colonne="titulaire" data-triable="false">Titulaire</th>
                                <th scope="col" data-colonne="telephone" data-triable="false">Téléphone</th>
                                <th scope="col" data-colonne="statut_libelle" data-triable="false">Statut</th>
                                <th scope="col" data-colonne="active_le">Activée le</th>
                                <th scope="col" data-colonne="expire_le">Expire le</th>
                                <th scope="col" data-colonne="active_par" data-triable="false">Activée par</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
