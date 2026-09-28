<x-layouts.app :titre="'Carte '.$carte->numeroFormate()" sous-titre="Back-office · Cartes">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.index') }}">Cartes</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $carte->numeroFormate() }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <x-carte-visuelle :carte="$carte" class="mb-4" />
            <x-actions-statut-carte :carte="$carte" class="mb-2" />
        </div>

        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-titulaire">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <h1 class="h5 fw-bold" id="titre-titulaire">{{ $carte->titulaire->nomComplet() }}</h1>
                        @if (auth()->user()->canAny(['modifierTitulaire', 'modifierTelephone'], $carte))
                            <a href="{{ route('gestion.cartes.titulaire.edit', $carte) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil me-1" aria-hidden="true"></i>Modifier le titulaire
                            </a>
                        @endif
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-5 col-sm-4">Téléphone</dt>
                        <dd class="col-7 col-sm-8">{{ $carte->titulaire->telephoneFormate() }}</dd>
                        <dt class="col-5 col-sm-4">Statut de la carte</dt>
                        <dd class="col-7 col-sm-8">
                            <span class="badge {{ $carte->statutEffectif() === App\Enums\StatutCarte::Active ? 'text-bg-success' : 'text-bg-danger' }}">
                                {{ $carte->statutEffectif()->libelle() }}
                            </span>
                            @if ($carte->motif_statut)
                                <span class="d-block text-secondary mt-1">{{ $carte->motif_statut }}</span>
                            @endif
                        </dd>
                        <dt class="col-5 col-sm-4">Activée le</dt>
                        <dd class="col-7 col-sm-8">{{ $carte->active_le?->format('d/m/Y à H:i') }} par <strong>{{ $carte->activePar?->libelleActeur() ?? '—' }}</strong></dd>
                        <dt class="col-5 col-sm-4">Expire le</dt>
                        <dd class="col-7 col-sm-8">{{ $carte->expire_le?->format('d/m/Y') }}</dd>
                        @if ($carte->modifiePar)
                            <dt class="col-5 col-sm-4">Dernière modification</dt>
                            <dd class="col-7 col-sm-8">{{ $carte->updated_at->format('d/m/Y à H:i') }} par <strong>{{ $carte->modifiePar->libelleActeur() }}</strong></dd>
                        @endif
                        <dt class="col-5 col-sm-4">Titulaire créé par</dt>
                        <dd class="col-7 col-sm-8">{{ $carte->titulaire->creePar?->libelleActeur() ?? '—' }} le {{ $carte->titulaire->created_at->format('d/m/Y') }}</dd>
                    </dl>
                </div>
            </section>

            <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-cartes">
                <div class="card-body">
                    <h2 class="h6 fw-bold" id="titre-cartes">Cartes du titulaire</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th scope="col">Numéro</th><th scope="col">Statut</th><th scope="col">Activée le</th><th scope="col">Expire le</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($carte->titulaire->cartes as $autre)
                                    <tr @if ($autre->is($carte)) class="table-warning" @endif>
                                        <td>
                                            <a href="{{ route('gestion.cartes.show', $autre) }}">{{ $autre->numeroFormate() }}</a>
                                        </td>
                                        <td>{{ $autre->statutEffectif()->libelle() }}</td>
                                        <td>{{ $autre->active_le?->format('d/m/Y') }}</td>
                                        <td>{{ $autre->expire_le?->format('d/m/Y') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="card border-0 shadow-sm" aria-labelledby="titre-historique">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h2 class="h6 fw-bold mb-0" id="titre-historique">
                            Historique des opérations
                            @if ($nombreOperations > $historique->count())
                                <span class="fw-normal text-secondary">({{ $historique->count() }} dernières sur {{ $nombreOperations }})</span>
                            @endif
                        </h2>
                        @can('voir-rapport-cartes')
                            <a href="{{ route('gestion.cartes.rapport', ['carte' => $carte->numero_carte]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-clock-history me-1" aria-hidden="true"></i>Voir tout l'historique
                            </a>
                        @endcan
                    </div>
                    <ul class="list-unstyled small mb-0">
                        @forelse ($historique as $entree)
                            <li class="border-start border-3 border-warning ps-2 mb-2">
                                <span class="fw-semibold"><i class="bi {{ $entree->type->icone() }} me-1" aria-hidden="true"></i>{{ $entree->type->libelle() }}</span>
                                — {{ $entree->libelleAuteur() }},
                                {{ $entree->effectuee_le->format('d/m/Y à H:i') }}
                                @if ($entree->motif)
                                    <span class="d-block text-secondary">{{ $entree->motif }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-secondary">Aucune opération enregistrée.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
