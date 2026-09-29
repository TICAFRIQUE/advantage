<x-layouts.app titre="Cartes" sous-titre="Back-office">


    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h1 class="h3 fw-bold mb-0">Cartes</h1>
        <div class="d-flex flex-wrap gap-2">
            <x-menu-export liste="cartes" formulaire="#filtres-cartes" />
            @can('create', App\Models\Carte::class)
                <a href="{{ route('gestion.cartes.create') }}" class="btn btn-or">Activer une carte</a>
            @endcan
        </div>
    </div>

    {{-- Indicateurs du parc : chaque tuile de statut filtre la liste. --}}
    <div class="row g-3 mb-4">
        @foreach ([
            [request()->boolean('mes_activations') ? 'Mes cartes' : 'Cartes', $indicateurs['total'], 'bi-collection', null],
            ['Actives', $indicateurs['actives'], 'bi-check-circle', App\Enums\StatutCarte::Active],
            ['Expirent sous 30 jours', $indicateurs['expirent_sous_30_jours'], 'bi-hourglass-split', null],
            ['Suspendues', $indicateurs['suspendues'], 'bi-pause-circle', App\Enums\StatutCarte::Suspendue],
            ['Révoquées', $indicateurs['revoquees'], 'bi-x-octagon', App\Enums\StatutCarte::Revoquee],
            ['Expirées', $indicateurs['expirees'], 'bi-calendar-x', App\Enums\StatutCarte::Expiree],
        ] as [$libelle, $valeur, $icone, $statut])
            <div class="col-6 col-md-4 col-xl-2">
                @php($selectionne = $statut !== null && ($filtres['statut'] ?? null) === $statut->value)
                <div @class(['card border-0 shadow-sm fond-nuit h-100', 'border border-2 border-warning' => $selectionne])>
                    <div class="card-body">
                        <p class="small text-white-50 mb-1"><i class="bi {{ $icone }} me-1" aria-hidden="true"></i>{{ $libelle }}</p>
                        <p class="h2 fw-bold texte-or mb-0">
                            @if ($statut)
                                <a href="{{ route('gestion.cartes.index', array_filter(['statut' => $statut->value, 'mes_activations' => request()->boolean('mes_activations') ?: null])) }}"
                                   class="texte-or text-decoration-none stretched-link"
                                   @if ($selectionne) aria-current="true" @endif
                                   aria-label="{{ $valeur }} {{ mb_strtolower($libelle) }} : afficher ces cartes">{{ $valeur }}</a>
                            @else
                                {{ $valeur }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('gestion.cartes.index') }}" id="filtres-cartes" class="card card-body shadow-sm mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="recherche" class="form-label fw-semibold">Rechercher</label>
                <input type="search" id="recherche" name="recherche" value="{{ $filtres['recherche'] ?? '' }}"
                       class="form-control" maxlength="100" placeholder="N° de carte, téléphone, nom…">
            </div>
            <div class="col-6 col-md-3">
                <label for="statut" class="form-label fw-semibold">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="">Tous</option>
                    @foreach (App\Enums\StatutCarte::cases() as $statut)
                        @continue($statut === App\Enums\StatutCarte::NonActivee)
                        <option value="{{ $statut->value }}" @selected(($filtres['statut'] ?? null) === $statut->value)>{{ $statut->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="mes_activations" name="mes_activations"
                           @checked(request()->boolean('mes_activations'))>
                    <label class="form-check-label" for="mes_activations">Mes activations</label>
                </div>
            </div>
            <div class="col-12 col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Filtrer</button>
            </div>
        </div>
    </form>

    <p class="text-secondary small" role="status">{{ $cartes->total() }} carte(s)</p>

    <div class="row g-4">
        @forelse ($cartes as $carte)
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card h-100 border-0 shadow-sm" aria-labelledby="carte-{{ $carte->id }}">
                    <div class="card-body">
                        <x-carte-visuelle :carte="$carte" class="mb-3" />

                        <h2 class="h6 fw-bold mb-1" id="carte-{{ $carte->id }}">
                            {{ $carte->titulaire->nomComplet() }}
                        </h2>
                        <p class="small text-secondary mb-2">
                            {{ $carte->titulaire->telephoneFormate() }}<br>
                            N° {{ $carte->numeroFormate() }} · expire le {{ $carte->expire_le?->format('d/m/Y') }}
                        </p>
                        <p class="small mb-3">
                            Activée le {{ $carte->active_le?->format('d/m/Y à H:i') }}
                            par <strong>{{ $carte->activePar?->libelleActeur() ?? '—' }}</strong>
                            @if ($carte->modifiePar)
                                <br>Modifiée par <strong>{{ $carte->modifiePar->libelleActeur() }}</strong> le {{ $carte->updated_at->format('d/m/Y à H:i') }}
                            @endif
                        </p>

                        <x-actions-carte :carte="$carte" />
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border text-center" role="status">Aucune carte ne correspond à ces critères.</div>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $cartes->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
</x-layouts.app>
