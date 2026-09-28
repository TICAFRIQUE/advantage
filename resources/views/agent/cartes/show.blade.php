<x-layouts.app :titre="'Carte '.$carte->numeroFormate()" sous-titre="Espace Agent">

    @error('motif')
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span>Déclaration de perte : {{ $message }}</span>
        </div>
    @enderror

    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('agent.cartes.index') }}">Cartes</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $carte->numeroFormate() }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <x-carte-visuelle :carte="$carte" class="mb-4" />
            <x-actions-carte :carte="$carte" :detail="false" />
        </div>

        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-titulaire">
                <div class="card-body">
                    <h1 class="h5 fw-bold" id="titre-titulaire">{{ $carte->titulaire->nomComplet() }}</h1>
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
                                            <a href="{{ route('agent.cartes.show', $autre) }}">{{ $autre->numeroFormate() }}</a>
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
                    <h2 class="h6 fw-bold" id="titre-historique">Historique des opérations</h2>
                    <ul class="list-unstyled small mb-0">
                        @forelse ($historique as $entree)
                            <li class="border-start border-3 border-warning ps-2 mb-2">
                                <span class="fw-semibold">{{ __('audit.'.$entree->action) }}</span>
                                — {{ $entree->acteur?->libelleActeur() ?? 'Système' }},
                                {{ $entree->cree_le->format('d/m/Y à H:i') }}
                                @if (isset($entree->donnees['apres']['motif_statut']))
                                    <span class="d-block text-secondary">{{ $entree->donnees['apres']['motif_statut'] }}</span>
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
