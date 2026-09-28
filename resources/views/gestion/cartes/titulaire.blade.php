<x-layouts.app titre="Modifier le titulaire" sous-titre="Back-office · Cartes">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.index') }}">Cartes</a></li>
            <li class="breadcrumb-item"><a href="{{ route('gestion.cartes.show', $carte) }}">{{ $carte->numeroFormate() }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Titulaire</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-7 col-xl-6">
            <h1 class="h3 fw-bold mb-3">Modifier le titulaire</h1>

            @can('modifierTitulaire', $carte)
                <form method="POST" action="{{ route('gestion.cartes.titulaire.update', $carte) }}" class="card border-0 shadow-sm mb-4" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold texte-or-fonce">Identité</h2>
                        <div class="row g-3">
                            <div class="col-12 col-sm-5">
                                <label for="nom" class="form-label fw-semibold">Nom</label>
                                <input type="text" id="nom" name="nom" value="{{ old('nom', $carte->titulaire->nom) }}"
                                       class="form-control form-control-lg text-uppercase @error('nom') is-invalid @enderror"
                                       required minlength="2" maxlength="100" autocomplete="off">
                                @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-sm-7">
                                <label for="prenom" class="form-label fw-semibold">Prénoms</label>
                                <input type="text" id="prenom" name="prenom" value="{{ old('prenom', $carte->titulaire->prenom) }}"
                                       class="form-control form-control-lg @error('prenom') is-invalid @enderror"
                                       required minlength="2" maxlength="150" autocomplete="off">
                                @error('prenom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">Enregistrer</button>
                    </div>
                </form>
            @endcan

            <section class="card border-0 shadow-sm" aria-labelledby="titre-telephone">
                <div class="card-body p-4">
                    <h2 class="h6 fw-bold texte-or-fonce" id="titre-telephone">Téléphone</h2>
                    <p class="fs-5 mb-1">{{ $carte->titulaire->telephoneFormate() }}</p>
                    <p class="small text-secondary">Les codes de validation sont envoyés sur ce numéro.</p>

                    @can('modifierTelephone', $carte)
                        <a href="{{ route('gestion.cartes.titulaire.telephone.edit', $carte) }}" class="btn btn-outline-danger w-100">
                            <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Changer le numéro (confirmation du PIN requise)
                        </a>
                    @else
                        <p class="small mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>Vous n'avez pas le droit de modifier le téléphone.</p>
                    @endcan
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
